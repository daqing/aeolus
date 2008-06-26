<?php
class HTMLPurifier
{
    public $version = '3.1.0';
    const VERSION = '3.1.0';
    public $config;
    private $filters = array();
    private static $instance;
    protected $strategy, $generator;
    public $context;
    public function __construct($config = null) {
        $this->config = HTMLPurifier_Config::create($config);
        $this->strategy     = new HTMLPurifier_Strategy_Core();
    }
    public function addFilter($filter) {
        trigger_error('HTMLPurifier->addFilter() is deprecated, use configuration directives in the Filter namespace or Filter.Custom', E_USER_WARNING);
        $this->filters[] = $filter;
    }
    public function purify($html, $config = null) {
        $config = $config ? HTMLPurifier_Config::create($config) : $this->config;
        $lexer = HTMLPurifier_Lexer::create($config);
        $context = new HTMLPurifier_Context();
        $this->generator = new HTMLPurifier_Generator($config, $context);
        $context->register('Generator', $this->generator);
        if ($config->get('Core', 'CollectErrors')) {
            $language_factory = HTMLPurifier_LanguageFactory::instance();
            $language = $language_factory->create($config, $context);
            $context->register('Locale', $language);
            $error_collector = new HTMLPurifier_ErrorCollector($context);
            $context->register('ErrorCollector', $error_collector);
        }
        $id_accumulator = HTMLPurifier_IDAccumulator::build($config, $context);
        $context->register('IDAccumulator', $id_accumulator);
        $html = HTMLPurifier_Encoder::convertToUTF8($html, $config, $context);
        $filter_flags = $config->getBatch('Filter');
        $custom_filters = $filter_flags['Custom'];
        unset($filter_flags['Custom']);
        $filters = array();
        foreach ($filter_flags as $filter => $flag) {
            if (!$flag) continue;
            $class = "HTMLPurifier_Filter_$filter";
            $filters[] = new $class;
        }
        foreach ($custom_filters as $filter) {
            $filters[] = $filter;
        }
        $filters = array_merge($filters, $this->filters);
        for ($i = 0, $filter_size = count($filters); $i < $filter_size; $i++) {
            $html = $filters[$i]->preFilter($html, $config, $context);
        }
        $html = 
            $this->generator->generateFromTokens(
                $this->strategy->execute(
                    $lexer->tokenizeHTML(
                        $html, $config, $context
                    ),
                    $config, $context
                )
            );
        for ($i = $filter_size - 1; $i >= 0; $i--) {
            $html = $filters[$i]->postFilter($html, $config, $context);
        }
        $html = HTMLPurifier_Encoder::convertFromUTF8($html, $config, $context);
        $this->context =& $context;
        return $html;
    }
    public function purifyArray($array_of_html, $config = null) {
        $context_array = array();
        foreach ($array_of_html as $key => $html) {
            $array_of_html[$key] = $this->purify($html, $config);
            $context_array[$key] = $this->context;
        }
        $this->context = $context_array;
        return $array_of_html;
    }
    public static function instance($prototype = null) {
        if (!self::$instance || $prototype) {
            if ($prototype instanceof HTMLPurifier) {
                self::$instance = $prototype;
            } elseif ($prototype) {
                self::$instance = new HTMLPurifier($prototype);
            } else {
                self::$instance = new HTMLPurifier();
            }
        }
        return self::$instance;
    }
    public static function getInstance($prototype = null) {
        return HTMLPurifier::instance($prototype);
    }
}
class HTMLPurifier_AttrCollections
{
    public $info = array();
    public function __construct($attr_types, $modules) {
        foreach ($modules as $module) {
            foreach ($module->attr_collections as $coll_i => $coll) {
                if (!isset($this->info[$coll_i])) {
                    $this->info[$coll_i] = array();
                }
                foreach ($coll as $attr_i => $attr) {
                    if ($attr_i === 0 && isset($this->info[$coll_i][$attr_i])) {
                        $this->info[$coll_i][$attr_i] = array_merge(
                            $this->info[$coll_i][$attr_i], $attr);
                        continue;
                    }
                    $this->info[$coll_i][$attr_i] = $attr;
                }
            }
        }
        foreach ($this->info as $name => $attr) {
            $this->performInclusions($this->info[$name]);
            $this->expandIdentifiers($this->info[$name], $attr_types);
        }
    }
    public function performInclusions(&$attr) {
        if (!isset($attr[0])) return;
        $merge = $attr[0];
        $seen  = array(); 
        for ($i = 0; isset($merge[$i]); $i++) {
            if (isset($seen[$merge[$i]])) continue;
            $seen[$merge[$i]] = true;
            if (!isset($this->info[$merge[$i]])) continue;
            foreach ($this->info[$merge[$i]] as $key => $value) {
                if (isset($attr[$key])) continue; 
                $attr[$key] = $value;
            }
            if (isset($this->info[$merge[$i]][0])) {
                $merge = array_merge($merge, $this->info[$merge[$i]][0]);
            }
        }
        unset($attr[0]);
    }
    public function expandIdentifiers(&$attr, $attr_types) {
        $processed = array();
        foreach ($attr as $def_i => $def) {
            if ($def_i === 0) continue;
            if (isset($processed[$def_i])) continue;
            if ($required = (strpos($def_i, '*') !== false)) {
                unset($attr[$def_i]);
                $def_i = trim($def_i, '*');
                $attr[$def_i] = $def;
            }
            $processed[$def_i] = true;
            if (is_object($def)) {
                $attr[$def_i]->required = ($required || $attr[$def_i]->required);
                continue;
            }
            if ($def === false) {
                unset($attr[$def_i]);
                continue;
            }
            if ($t = $attr_types->get($def)) {
                $attr[$def_i] = $t;
                $attr[$def_i]->required = $required;
            } else {
                unset($attr[$def_i]);
            }
        }
    }
}
abstract class HTMLPurifier_AttrDef
{
    public $minimized = false;
    public $required = false;
    abstract public function validate($string, $config, $context);
    public function parseCDATA($string) {
        $string = trim($string);
        $string = str_replace("\n", '', $string);
        $string = str_replace(array("\r", "\t"), ' ', $string);
        return $string;
    }
    public function make($string) {
        return $this;
    }
    protected function mungeRgb($string) {
        return preg_replace('/rgb\((\d+)\s*,\s*(\d+)\s*,\s*(\d+)\)/', 'rgb(\1,\2,\3)', $string);
    }
}
abstract class HTMLPurifier_AttrTransform
{
    abstract public function transform($attr, $config, $context);
    public function prependCSS(&$attr, $css) {
        $attr['style'] = isset($attr['style']) ? $attr['style'] : '';
        $attr['style'] = $css . $attr['style'];
    }
    public function confiscateAttr(&$attr, $key) {
        if (!isset($attr[$key])) return null;
        $value = $attr[$key];
        unset($attr[$key]);
        return $value;
    }
}
class HTMLPurifier_AttrTypes
{
    protected $info = array();
    public function __construct() {
        $this->info['Enum']    = new HTMLPurifier_AttrDef_Enum();
        $this->info['Bool']    = new HTMLPurifier_AttrDef_HTML_Bool();
        $this->info['CDATA']    = new HTMLPurifier_AttrDef_Text();
        $this->info['ID']       = new HTMLPurifier_AttrDef_HTML_ID();
        $this->info['Length']   = new HTMLPurifier_AttrDef_HTML_Length();
        $this->info['MultiLength'] = new HTMLPurifier_AttrDef_HTML_MultiLength();
        $this->info['NMTOKENS'] = new HTMLPurifier_AttrDef_HTML_Nmtokens();
        $this->info['Pixels']   = new HTMLPurifier_AttrDef_HTML_Pixels();
        $this->info['Text']     = new HTMLPurifier_AttrDef_Text();
        $this->info['URI']      = new HTMLPurifier_AttrDef_URI();
        $this->info['LanguageCode'] = new HTMLPurifier_AttrDef_Lang();
        $this->info['Color']    = new HTMLPurifier_AttrDef_HTML_Color();
        $this->info['ContentType'] = new HTMLPurifier_AttrDef_Text();
        $this->info['Number']   = new HTMLPurifier_AttrDef_Integer(false, false, true);
    }
    public function get($type) {
        if (strpos($type, '#') !== false) list($type, $string) = explode('#', $type, 2);
        else $string = '';
        if (!isset($this->info[$type])) {
            trigger_error('Cannot retrieve undefined attribute type ' . $type, E_USER_ERROR);
            return;
        }
        return $this->info[$type]->make($string);
    }
    public function set($type, $impl) {
        $this->info[$type] = $impl;
    }
}
class HTMLPurifier_AttrValidator
{
    public function validateToken(&$token, &$config, $context) {
        $definition = $config->getHTMLDefinition();
        $e =& $context->get('ErrorCollector', true);
        $ok =& $context->get('IDAccumulator', true);
        if (!$ok) {
            $id_accumulator = HTMLPurifier_IDAccumulator::build($config, $context);
            $context->register('IDAccumulator', $id_accumulator);
        }
        $current_token =& $context->get('CurrentToken', true);
        if (!$current_token) $context->register('CurrentToken', $token);
        if (
          !$token instanceof HTMLPurifier_Token_Start &&
          !$token instanceof HTMLPurifier_Token_Empty
        ) return $token;
        $d_defs = $definition->info_global_attr;
        $attr =& $token->attr;
        foreach ($definition->info_attr_transform_pre as $transform) {
            $attr = $transform->transform($o = $attr, $config, $context);
            if ($e && ($attr != $o)) $e->send(E_NOTICE, 'AttrValidator: Attributes transformed', $o, $attr);
        }
        foreach ($definition->info[$token->name]->attr_transform_pre as $transform) {
            $attr = $transform->transform($o = $attr, $config, $context);
            if ($e && ($attr != $o)) $e->send(E_NOTICE, 'AttrValidator: Attributes transformed', $o, $attr);
        }
        $defs = $definition->info[$token->name]->attr;
        $attr_key = false;
        $context->register('CurrentAttr', $attr_key);
        foreach ($attr as $attr_key => $value) {
            if ( isset($defs[$attr_key]) ) {
                if ($defs[$attr_key] === false) {
                    $result = false;
                } else {
                    $result = $defs[$attr_key]->validate(
                                    $value, $config, $context
                               );
                }
            } elseif ( isset($d_defs[$attr_key]) ) {
                $result = $d_defs[$attr_key]->validate(
                                $value, $config, $context
                           );
            } else {
                $result = false;
            }
            if ($result === false || $result === null) {
                if ($e) $e->send(E_ERROR, 'AttrValidator: Attribute removed');
                unset($attr[$attr_key]);
            } elseif (is_string($result)) {
                $attr[$attr_key] = $result;
            }
        }
        $context->destroy('CurrentAttr');
        foreach ($definition->info_attr_transform_post as $transform) {
            $attr = $transform->transform($o = $attr, $config, $context);
            if ($e && ($attr != $o)) $e->send(E_NOTICE, 'AttrValidator: Attributes transformed', $o, $attr);
        }
        foreach ($definition->info[$token->name]->attr_transform_post as $transform) {
            $attr = $transform->transform($o = $attr, $config, $context);
            if ($e && ($attr != $o)) $e->send(E_NOTICE, 'AttrValidator: Attributes transformed', $o, $attr);
        }
        if (!$current_token) $context->destroy('CurrentToken');
    }
}
if (!defined('HTMLPURIFIER_PREFIX')) {
    define('HTMLPURIFIER_PREFIX', dirname(__FILE__));
    set_include_path(HTMLPURIFIER_PREFIX . PATH_SEPARATOR . get_include_path());
}
if (!defined('PHP_EOL')) {
    switch (strtoupper(substr(PHP_OS, 0, 3))) {
        case 'WIN':
            define('PHP_EOL', "\r\n");
            break;
        case 'DAR':
            define('PHP_EOL', "\r");
            break;
        default:
            define('PHP_EOL', "\n");
    }
}
class HTMLPurifier_Bootstrap
{
    public static function autoload($class) {
        $file = HTMLPurifier_Bootstrap::getPath($class);
        if (!$file) return false;
        require HTMLPURIFIER_PREFIX . '/' . $file;
        return true;
    }
    public static function getPath($class) {
        if (strncmp('HTMLPurifier', $class, 12) !== 0) return false;
        if (strncmp('HTMLPurifier_Language_', $class, 22) === 0) {
            $code = str_replace('_', '-', substr($class, 22));
            $file = 'HTMLPurifier/Language/classes/' . $code . '.php';
        } else {
            $file = str_replace('_', '/', $class) . '.php';
        }
        if (!file_exists(HTMLPURIFIER_PREFIX . '/' . $file)) return false;
        return $file;
    }
    public static function registerAutoload() {
        $autoload = array('HTMLPurifier_Bootstrap', 'autoload');
        if ( ($funcs = spl_autoload_functions()) === false ) {
            spl_autoload_register($autoload);
        } elseif (function_exists('spl_autoload_unregister')) {
            $compat = version_compare(PHP_VERSION, '5.1.2', '<=') &&
                      version_compare(PHP_VERSION, '5.1.0', '>=');
            foreach ($funcs as $func) {
                if (is_array($func)) {
                    $reflector = new ReflectionMethod($func[0], $func[1]);
                    if (!$reflector->isStatic()) {
                        throw new Exception('
                            HTML Purifier autoloader registrar is not compatible
                            with non-static object methods due to PHP Bug #44144;
                            Please do not use HTMLPurifier.autoload.php (or any
                            file that includes this file); instead, place the code:
                            spl_autoload_register(array(\'HTMLPurifier_Bootstrap\', \'autoload\'))
                            after your own autoloaders.
                        ');
                    }
                    if ($compat) $func = implode('::', $func);
                }
                spl_autoload_unregister($func);
            }
            spl_autoload_register($autoload);
            foreach ($funcs as $func) spl_autoload_register($func);
        }
    }
}
abstract class HTMLPurifier_Definition
{
    public $setup = false;
    public $type;
    abstract protected function doSetup($config);
    public function setup($config) {
        if ($this->setup) return;
        $this->setup = true;
        $this->doSetup($config);
    }
}
class HTMLPurifier_CSSDefinition extends HTMLPurifier_Definition
{
    public $type = 'CSS';
    public $info = array();
    protected function doSetup($config) {
        $this->info['text-align'] = new HTMLPurifier_AttrDef_Enum(
            array('left', 'right', 'center', 'justify'), false);
        $border_style =
        $this->info['border-bottom-style'] = 
        $this->info['border-right-style'] = 
        $this->info['border-left-style'] = 
        $this->info['border-top-style'] =  new HTMLPurifier_AttrDef_Enum(
            array('none', 'hidden', 'dotted', 'dashed', 'solid', 'double',
            'groove', 'ridge', 'inset', 'outset'), false);
        $this->info['border-style'] = new HTMLPurifier_AttrDef_CSS_Multiple($border_style);
        $this->info['clear'] = new HTMLPurifier_AttrDef_Enum(
            array('none', 'left', 'right', 'both'), false);
        $this->info['float'] = new HTMLPurifier_AttrDef_Enum(
            array('none', 'left', 'right'), false);
        $this->info['font-style'] = new HTMLPurifier_AttrDef_Enum(
            array('normal', 'italic', 'oblique'), false);
        $this->info['font-variant'] = new HTMLPurifier_AttrDef_Enum(
            array('normal', 'small-caps'), false);
        $uri_or_none = new HTMLPurifier_AttrDef_CSS_Composite(
            array(
                new HTMLPurifier_AttrDef_Enum(array('none')),
                new HTMLPurifier_AttrDef_CSS_URI()
            )
        );
        $this->info['list-style-position'] = new HTMLPurifier_AttrDef_Enum(
            array('inside', 'outside'), false);
        $this->info['list-style-type'] = new HTMLPurifier_AttrDef_Enum(
            array('disc', 'circle', 'square', 'decimal', 'lower-roman',
            'upper-roman', 'lower-alpha', 'upper-alpha', 'none'), false);
        $this->info['list-style-image'] = $uri_or_none;
        $this->info['list-style'] = new HTMLPurifier_AttrDef_CSS_ListStyle($config);
        $this->info['text-transform'] = new HTMLPurifier_AttrDef_Enum(
            array('capitalize', 'uppercase', 'lowercase', 'none'), false);
        $this->info['color'] = new HTMLPurifier_AttrDef_CSS_Color();
        $this->info['background-image'] = $uri_or_none;
        $this->info['background-repeat'] = new HTMLPurifier_AttrDef_Enum(
            array('repeat', 'repeat-x', 'repeat-y', 'no-repeat')
        );
        $this->info['background-attachment'] = new HTMLPurifier_AttrDef_Enum(
            array('scroll', 'fixed')
        );
        $this->info['background-position'] = new HTMLPurifier_AttrDef_CSS_BackgroundPosition();
        $border_color = 
        $this->info['border-top-color'] = 
        $this->info['border-bottom-color'] = 
        $this->info['border-left-color'] = 
        $this->info['border-right-color'] = 
        $this->info['background-color'] = new HTMLPurifier_AttrDef_CSS_Composite(array(
            new HTMLPurifier_AttrDef_Enum(array('transparent')),
            new HTMLPurifier_AttrDef_CSS_Color()
        ));
        $this->info['background'] = new HTMLPurifier_AttrDef_CSS_Background($config);
        $this->info['border-color'] = new HTMLPurifier_AttrDef_CSS_Multiple($border_color);
        $border_width = 
        $this->info['border-top-width'] = 
        $this->info['border-bottom-width'] = 
        $this->info['border-left-width'] = 
        $this->info['border-right-width'] = new HTMLPurifier_AttrDef_CSS_Composite(array(
            new HTMLPurifier_AttrDef_Enum(array('thin', 'medium', 'thick')),
            new HTMLPurifier_AttrDef_CSS_Length(true) 
        ));
        $this->info['border-width'] = new HTMLPurifier_AttrDef_CSS_Multiple($border_width);
        $this->info['letter-spacing'] = new HTMLPurifier_AttrDef_CSS_Composite(array(
            new HTMLPurifier_AttrDef_Enum(array('normal')),
            new HTMLPurifier_AttrDef_CSS_Length()
        ));
        $this->info['word-spacing'] = new HTMLPurifier_AttrDef_CSS_Composite(array(
            new HTMLPurifier_AttrDef_Enum(array('normal')),
            new HTMLPurifier_AttrDef_CSS_Length()
        ));
        $this->info['font-size'] = new HTMLPurifier_AttrDef_CSS_Composite(array(
            new HTMLPurifier_AttrDef_Enum(array('xx-small', 'x-small',
                'small', 'medium', 'large', 'x-large', 'xx-large',
                'larger', 'smaller')),
            new HTMLPurifier_AttrDef_CSS_Percentage(),
            new HTMLPurifier_AttrDef_CSS_Length()
        ));
        $this->info['line-height'] = new HTMLPurifier_AttrDef_CSS_Composite(array(
            new HTMLPurifier_AttrDef_Enum(array('normal')),
            new HTMLPurifier_AttrDef_CSS_Number(true), 
            new HTMLPurifier_AttrDef_CSS_Length(true),
            new HTMLPurifier_AttrDef_CSS_Percentage(true)
        ));
        $margin =
        $this->info['margin-top'] = 
        $this->info['margin-bottom'] = 
        $this->info['margin-left'] = 
        $this->info['margin-right'] = new HTMLPurifier_AttrDef_CSS_Composite(array(
            new HTMLPurifier_AttrDef_CSS_Length(),
            new HTMLPurifier_AttrDef_CSS_Percentage(),
            new HTMLPurifier_AttrDef_Enum(array('auto'))
        ));
        $this->info['margin'] = new HTMLPurifier_AttrDef_CSS_Multiple($margin);
        $padding =
        $this->info['padding-top'] = 
        $this->info['padding-bottom'] = 
        $this->info['padding-left'] = 
        $this->info['padding-right'] = new HTMLPurifier_AttrDef_CSS_Composite(array(
            new HTMLPurifier_AttrDef_CSS_Length(true),
            new HTMLPurifier_AttrDef_CSS_Percentage(true)
        ));
        $this->info['padding'] = new HTMLPurifier_AttrDef_CSS_Multiple($padding);
        $this->info['text-indent'] = new HTMLPurifier_AttrDef_CSS_Composite(array(
            new HTMLPurifier_AttrDef_CSS_Length(),
            new HTMLPurifier_AttrDef_CSS_Percentage()
        ));
        $this->info['width'] =
        $this->info['height'] =
        new HTMLPurifier_AttrDef_CSS_DenyElementDecorator(
        new HTMLPurifier_AttrDef_CSS_Composite(array(
            new HTMLPurifier_AttrDef_CSS_Length(true),
            new HTMLPurifier_AttrDef_CSS_Percentage(true),
            new HTMLPurifier_AttrDef_Enum(array('auto'))
        )), 'img');
        $this->info['text-decoration'] = new HTMLPurifier_AttrDef_CSS_TextDecoration();
        $this->info['font-family'] = new HTMLPurifier_AttrDef_CSS_FontFamily();
        $this->info['font-weight'] = new HTMLPurifier_AttrDef_Enum(
            array('normal', 'bold', 'bolder', 'lighter', '100', '200', '300',
            '400', '500', '600', '700', '800', '900'), false);
        $this->info['font'] = new HTMLPurifier_AttrDef_CSS_Font($config);
        $this->info['border'] =
        $this->info['border-bottom'] = 
        $this->info['border-top'] = 
        $this->info['border-left'] = 
        $this->info['border-right'] = new HTMLPurifier_AttrDef_CSS_Border($config);
        $this->info['border-collapse'] = new HTMLPurifier_AttrDef_Enum(array(
            'collapse', 'separate'));
        $this->info['caption-side'] = new HTMLPurifier_AttrDef_Enum(array(
            'top', 'bottom'));
        $this->info['table-layout'] = new HTMLPurifier_AttrDef_Enum(array(
            'auto', 'fixed'));
        $this->info['vertical-align'] = new HTMLPurifier_AttrDef_CSS_Composite(array(
            new HTMLPurifier_AttrDef_Enum(array('baseline', 'sub', 'super',
                'top', 'text-top', 'middle', 'bottom', 'text-bottom')),
            new HTMLPurifier_AttrDef_CSS_Length(),
            new HTMLPurifier_AttrDef_CSS_Percentage()
        ));
        $this->info['border-spacing'] = new HTMLPurifier_AttrDef_CSS_Multiple(new HTMLPurifier_AttrDef_CSS_Length(), 2);
        $this->info['white-space'] = new HTMLPurifier_AttrDef_Enum(array('nowrap'));
        if ($config->get('CSS', 'Proprietary')) {
            $this->doSetupProprietary($config);
        }
        if ($config->get('CSS', 'AllowTricky')) {
            $this->doSetupTricky($config);
        }
        $allow_important = $config->get('CSS', 'AllowImportant');
        foreach ($this->info as $k => $v) {
            $this->info[$k] = new HTMLPurifier_AttrDef_CSS_ImportantDecorator($v, $allow_important);
        }
        $this->setupConfigStuff($config);
    }
    protected function doSetupProprietary($config) {
        $this->info['scrollbar-arrow-color']        = new HTMLPurifier_AttrDef_CSS_Color();
        $this->info['scrollbar-base-color']         = new HTMLPurifier_AttrDef_CSS_Color();
        $this->info['scrollbar-darkshadow-color']   = new HTMLPurifier_AttrDef_CSS_Color();
        $this->info['scrollbar-face-color']         = new HTMLPurifier_AttrDef_CSS_Color();
        $this->info['scrollbar-highlight-color']    = new HTMLPurifier_AttrDef_CSS_Color();
        $this->info['scrollbar-shadow-color']       = new HTMLPurifier_AttrDef_CSS_Color();
        $this->info['opacity']          = new HTMLPurifier_AttrDef_CSS_AlphaValue();
        $this->info['-moz-opacity']     = new HTMLPurifier_AttrDef_CSS_AlphaValue();
        $this->info['-khtml-opacity']   = new HTMLPurifier_AttrDef_CSS_AlphaValue();
        $this->info['filter'] = new HTMLPurifier_AttrDef_CSS_Filter();
    }
    protected function doSetupTricky($config) {
        $this->info['display'] = new HTMLPurifier_AttrDef_Enum(array(
            'inline', 'block', 'list-item', 'run-in', 'compact',
            'marker', 'table', 'inline-table', 'table-row-group',
            'table-header-group', 'table-footer-group', 'table-row',
            'table-column-group', 'table-column', 'table-cell', 'table-caption', 'none'
        ));
        $this->info['visibility'] = new HTMLPurifier_AttrDef_Enum(array(
            'visible', 'hidden', 'collapse'
        ));
    }
    protected function setupConfigStuff($config) {
        $support = "(for information on implementing this, see the ".
                   "support forums) ";
        $allowed_attributes = $config->get('CSS', 'AllowedProperties');
        if ($allowed_attributes !== null) {
            foreach ($this->info as $name => $d) {
                if(!isset($allowed_attributes[$name])) unset($this->info[$name]);
                unset($allowed_attributes[$name]);
            }
            foreach ($allowed_attributes as $name => $d) {
                $name = htmlspecialchars($name);
                trigger_error("Style attribute '$name' is not supported $support", E_USER_WARNING);
            }
        }
    }
}
abstract class HTMLPurifier_ChildDef
{
    public $type;
    public $allow_empty;
    public $elements = array();
    abstract public function validateChildren($tokens_of_children, $config, $context);
}
class HTMLPurifier_Config
{
    public $version = '3.1.0';
    public $autoFinalize = true;
    protected $serials = array();
    protected $serial;
    protected $conf;
    protected $parser;
    public $def;
    protected $definitions;
    protected $finalized = false;
    public function __construct($definition) {
        $this->conf = $definition->defaults; 
        $this->def  = $definition; 
        $this->parser = new HTMLPurifier_VarParser_Flexible();
    }
    public static function create($config, $schema = null) {
        if ($config instanceof HTMLPurifier_Config) {
            return $config;
        }
        if (!$schema) {
            $ret = HTMLPurifier_Config::createDefault();
        } else {
            $ret = new HTMLPurifier_Config($schema);
        }
        if (is_string($config)) $ret->loadIni($config);
        elseif (is_array($config)) $ret->loadArray($config);
        return $ret;
    }
    public static function createDefault() {
        $definition = HTMLPurifier_ConfigSchema::instance();
        $config = new HTMLPurifier_Config($definition);
        return $config;
    }
    public function get($namespace, $key) {
        if (!$this->finalized && $this->autoFinalize) $this->finalize();
        if (!isset($this->def->info[$namespace][$key])) {
            trigger_error('Cannot retrieve value of undefined directive ' . htmlspecialchars("$namespace.$key"),
                E_USER_WARNING);
            return;
        }
        if ($this->def->info[$namespace][$key]->class == 'alias') {
            $d = $this->def->info[$namespace][$key];
            trigger_error('Cannot get value from aliased directive, use real name ' . $d->namespace . '.' . $d->name,
                E_USER_ERROR);
            return;
        }
        return $this->conf[$namespace][$key];
    }
    public function getBatch($namespace) {
        if (!$this->finalized && $this->autoFinalize) $this->finalize();
        if (!isset($this->def->info[$namespace])) {
            trigger_error('Cannot retrieve undefined namespace ' . htmlspecialchars($namespace),
                E_USER_WARNING);
            return;
        }
        return $this->conf[$namespace];
    }
    public function getBatchSerial($namespace) {
        if (empty($this->serials[$namespace])) {
            $batch = $this->getBatch($namespace);
            unset($batch['DefinitionRev']);
            $this->serials[$namespace] = md5(serialize($batch));
        }
        return $this->serials[$namespace];
    }
    public function getSerial() {
        if (empty($this->serial)) {
            $this->serial = md5(serialize($this->getAll()));
        }
        return $this->serial;
    }
    public function getAll() {
        if (!$this->finalized && $this->autoFinalize) $this->finalize();
        return $this->conf;
    }
    public function set($namespace, $key, $value, $from_alias = false) {
        if ($this->isFinalized('Cannot set directive after finalization')) return;
        if (!isset($this->def->info[$namespace][$key])) {
            trigger_error('Cannot set undefined directive ' . htmlspecialchars("$namespace.$key") . ' to value',
                E_USER_WARNING);
            return;
        }
        if ($this->def->info[$namespace][$key]->class == 'alias') {
            if ($from_alias) {
                trigger_error('Double-aliases not allowed, please fix '.
                    'ConfigSchema bug with' . "$namespace.$key", E_USER_ERROR);
                return;
            }
            $this->set($new_ns = $this->def->info[$namespace][$key]->namespace,
                       $new_dir = $this->def->info[$namespace][$key]->name,
                       $value, true);
            trigger_error("$namespace.$key is an alias, preferred directive name is $new_ns.$new_dir", E_USER_NOTICE);
            return;
        }
        try {
            $value = $this->parser->parse(
                        $value,
                        $type = $this->def->info[$namespace][$key]->type,
                        $this->def->info[$namespace][$key]->allow_null
                     );
        } catch (HTMLPurifier_VarParserException $e) {
            trigger_error('Value for ' . "$namespace.$key" . ' is of invalid type, should be ' . $type, E_USER_WARNING);
            return;
        }
        if (is_string($value)) {
            if (isset($this->def->info[$namespace][$key]->aliases[$value])) {
                $value = $this->def->info[$namespace][$key]->aliases[$value];
            }
            if ($this->def->info[$namespace][$key]->allowed !== true) {
                if (!isset($this->def->info[$namespace][$key]->allowed[$value])) {
                    trigger_error('Value not supported, valid values are: ' .
                        $this->_listify($this->def->info[$namespace][$key]->allowed), E_USER_WARNING);
                    return;
                }
            }
        }
        $this->conf[$namespace][$key] = $value;
        if ($namespace == 'HTML' || $namespace == 'CSS') {
            $this->definitions[$namespace] = null;
        }
        $this->serials[$namespace] = false;
    }
    private function _listify($lookup) {
        $list = array();
        foreach ($lookup as $name => $b) $list[] = $name;
        return implode(', ', $list);
    }
    public function getHTMLDefinition($raw = false) {
        return $this->getDefinition('HTML', $raw);
    }
    public function getCSSDefinition($raw = false) {
        return $this->getDefinition('CSS', $raw);
    }
    public function getDefinition($type, $raw = false) {
        if (!$this->finalized && $this->autoFinalize) $this->finalize();
        $factory = HTMLPurifier_DefinitionCacheFactory::instance();
        $cache = $factory->create($type, $this);
        if (!$raw) {
            if (!empty($this->definitions[$type])) {
                if (!$this->definitions[$type]->setup) {
                    $this->definitions[$type]->setup($this);
                    $cache->set($this->definitions[$type], $this);
                }
                return $this->definitions[$type];
            }
            $this->definitions[$type] = $cache->get($this);
            if ($this->definitions[$type]) {
                return $this->definitions[$type];
            }
        } elseif (
            !empty($this->definitions[$type]) &&
            !$this->definitions[$type]->setup
        ) {
            return $this->definitions[$type];
        }
        if ($type == 'HTML') {
            $this->definitions[$type] = new HTMLPurifier_HTMLDefinition();
        } elseif ($type == 'CSS') {
            $this->definitions[$type] = new HTMLPurifier_CSSDefinition();
        } elseif ($type == 'URI') {
            $this->definitions[$type] = new HTMLPurifier_URIDefinition();
        } else {
            throw new HTMLPurifier_Exception("Definition of $type type not supported");
        }
        if ($raw) {
            if (is_null($this->get($type, 'DefinitionID'))) {
                throw new HTMLPurifier_Exception("Cannot retrieve raw version without specifying %$type.DefinitionID");
            }
            return $this->definitions[$type];
        }
        $this->definitions[$type]->setup($this);
        $cache->set($this->definitions[$type], $this);
        return $this->definitions[$type];
    }
    public function loadArray($config_array) {
        if ($this->isFinalized('Cannot load directives after finalization')) return;
        foreach ($config_array as $key => $value) {
            $key = str_replace('_', '.', $key);
            if (strpos($key, '.') !== false) {
                list($namespace, $directive) = explode('.', $key);
                $this->set($namespace, $directive, $value);
            } else {
                $namespace = $key;
                $namespace_values = $value;
                foreach ($namespace_values as $directive => $value) {
                    $this->set($namespace, $directive, $value);
                }
            }
        }
    }
    public static function getAllowedDirectivesForForm($allowed, $schema = null) {
        if (!$schema) {
            $schema = HTMLPurifier_ConfigSchema::instance();
        }
        if ($allowed !== true) {
             if (is_string($allowed)) $allowed = array($allowed);
             $allowed_ns = array();
             $allowed_directives = array();
             $blacklisted_directives = array();
             foreach ($allowed as $ns_or_directive) {
                 if (strpos($ns_or_directive, '.') !== false) {
                     if ($ns_or_directive[0] == '-') {
                         $blacklisted_directives[substr($ns_or_directive, 1)] = true;
                     } else {
                         $allowed_directives[$ns_or_directive] = true;
                     }
                 } else {
                     $allowed_ns[$ns_or_directive] = true;
                 }
             }
        }
        $ret = array();
        foreach ($schema->info as $ns => $keypairs) {
            foreach ($keypairs as $directive => $def) {
                if ($allowed !== true) {
                    if (isset($blacklisted_directives["$ns.$directive"])) continue;
                    if (!isset($allowed_directives["$ns.$directive"]) && !isset($allowed_ns[$ns])) continue;
                }
                if ($def->class == 'alias') continue;
                if ($directive == 'DefinitionID' || $directive == 'DefinitionRev') continue;
                $ret[] = array($ns, $directive);
            }
        }
        return $ret;
    }
    public static function loadArrayFromForm($array, $index = false, $allowed = true, $mq_fix = true, $schema = null) {
        $ret = HTMLPurifier_Config::prepareArrayFromForm($array, $index, $allowed, $mq_fix, $schema);
        $config = HTMLPurifier_Config::create($ret, $schema);
        return $config;
    }
    public function mergeArrayFromForm($array, $index = false, $allowed = true, $mq_fix = true) {
         $ret = HTMLPurifier_Config::prepareArrayFromForm($array, $index, $allowed, $mq_fix, $this->def);
         $this->loadArray($ret);
    }
    public static function prepareArrayFromForm($array, $index = false, $allowed = true, $mq_fix = true, $schema = null) {
        if ($index !== false) $array = (isset($array[$index]) && is_array($array[$index])) ? $array[$index] : array();
        $mq = $mq_fix && function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc();
        $allowed = HTMLPurifier_Config::getAllowedDirectivesForForm($allowed, $schema);
        $ret = array();
        foreach ($allowed as $key) {
            list($ns, $directive) = $key;
            $skey = "$ns.$directive";
            if (!empty($array["Null_$skey"])) {
                $ret[$ns][$directive] = null;
                continue;
            }
            if (!isset($array[$skey])) continue;
            $value = $mq ? stripslashes($array[$skey]) : $array[$skey];
            $ret[$ns][$directive] = $value;
        }
        return $ret;
    }
    public function loadIni($filename) {
        if ($this->isFinalized('Cannot load directives after finalization')) return;
        $array = parse_ini_file($filename, true);
        $this->loadArray($array);
    }
    public function isFinalized($error = false) {
        if ($this->finalized && $error) {
            trigger_error($error, E_USER_ERROR);
        }
        return $this->finalized;
    }
    public function autoFinalize() {
        if (!$this->finalized && $this->autoFinalize) $this->finalize();
    }
    public function finalize() {
        $this->finalized = true;
    }
}
abstract class HTMLPurifier_ConfigDef {
    public $class = false;
}
class HTMLPurifier_ConfigSchema {
    public $defaults = array();
    public $info = array();
    static protected $singleton;
    protected $parser;
    public function __construct() {
        $this->parser = new HTMLPurifier_VarParser_Flexible();
    }
    public static function makeFromSerial() {
        return unserialize(file_get_contents(HTMLPURIFIER_PREFIX . '/HTMLPurifier/ConfigSchema/schema.ser'));
    }
    public static function instance($prototype = null) {
        if ($prototype !== null) {
            HTMLPurifier_ConfigSchema::$singleton = $prototype;
        } elseif (HTMLPurifier_ConfigSchema::$singleton === null || $prototype === true) {
            HTMLPurifier_ConfigSchema::$singleton = HTMLPurifier_ConfigSchema::makeFromSerial();
        }
        return HTMLPurifier_ConfigSchema::$singleton;
    }
    public function add($namespace, $name, $default, $type, $allow_null) {
        $default = $this->parser->parse($default, $type, $allow_null);
        $this->info[$namespace][$name] = new HTMLPurifier_ConfigDef_Directive();
        $this->info[$namespace][$name]->type = $type;
        $this->info[$namespace][$name]->allow_null = $allow_null;
        $this->defaults[$namespace][$name]   = $default;
    }
    public function addNamespace($namespace) {
        $this->info[$namespace] = array();
        $this->defaults[$namespace] = array();
    }
    public function addValueAliases($namespace, $name, $aliases) {
        foreach ($aliases as $alias => $real) {
            $this->info[$namespace][$name]->aliases[$alias] = $real;
        }
    }
    public function addAllowedValues($namespace, $name, $allowed) {
        $type = $this->info[$namespace][$name]->type;
        $this->info[$namespace][$name]->allowed = $allowed;
    }
    public function addAlias($namespace, $name, $new_namespace, $new_name) {
        $this->info[$namespace][$name] = new HTMLPurifier_ConfigDef_DirectiveAlias($new_namespace, $new_name);
    }
    /** @see HTMLPurifier_ConfigSchema->set() */
    public static function define($namespace, $name, $default, $type, $description) {
        HTMLPurifier_ConfigSchema::deprecated(__METHOD__);
        $type_values = explode('/', $type, 2);
        $type = $type_values[0];
        $modifier = isset($type_values[1]) ? $type_values[1] : false;
        $allow_null = ($modifier === 'null');
        $def = HTMLPurifier_ConfigSchema::instance();
        $def->add($namespace, $name, $default, $type, $allow_null);
    }
    /** @see HTMLPurifier_ConfigSchema->addNamespace() */
    public static function defineNamespace($namespace, $description) {
        HTMLPurifier_ConfigSchema::deprecated(__METHOD__);
        $def = HTMLPurifier_ConfigSchema::instance();
        $def->addNamespace($namespace);
    }
    /** @see HTMLPurifier_ConfigSchema->addValueAliases() */
    public static function defineValueAliases($namespace, $name, $aliases) {
        HTMLPurifier_ConfigSchema::deprecated(__METHOD__);
        $def = HTMLPurifier_ConfigSchema::instance();
        $def->addValueAliases($namespace, $name, $aliases);
    }
    /** @see HTMLPurifier_ConfigSchema->addAllowedValues() */
    public static function defineAllowedValues($namespace, $name, $allowed_values) {
        HTMLPurifier_ConfigSchema::deprecated(__METHOD__);
        $allowed = array();
        foreach ($allowed_values as $value) {
            $allowed[$value] = true;
        }
        $def = HTMLPurifier_ConfigSchema::instance();
        $def->addAllowedValues($namespace, $name, $allowed);
    }
    /** @see HTMLPurifier_ConfigSchema->addAlias() */
    public static function defineAlias($namespace, $name, $new_namespace, $new_name) {
        HTMLPurifier_ConfigSchema::deprecated(__METHOD__);
        $def = HTMLPurifier_ConfigSchema::instance();
        $def->addAlias($namespace, $name, $new_namespace, $new_name);
    }
    /** @deprecated, use HTMLPurifier_VarParser->parse() */
    public function validate($a, $b, $c = false) {
        trigger_error("HTMLPurifier_ConfigSchema->validate deprecated, use HTMLPurifier_VarParser->parse instead", E_USER_NOTICE);
        return $this->parser->parse($a, $b, $c);
    }
    private static function deprecated($method) {
        trigger_error("Static HTMLPurifier_ConfigSchema::$method deprecated, use add*() method instead", E_USER_NOTICE);
    }
}
class HTMLPurifier_ContentSets
{
    public $info = array();
    public $lookup = array();
    protected $keys = array();
    protected $values = array();
    public function __construct($modules) {
        if (!is_array($modules)) $modules = array($modules);
        foreach ($modules as $module_i => $module) {
            foreach ($module->content_sets as $key => $value) {
                if (isset($this->info[$key])) {
                    $this->info[$key] = $this->info[$key] . ' | ' . $value;
                } else {
                    $this->info[$key] = $value;
                }
            }
        }
        $this->keys = array_keys($this->info);
        foreach ($this->info as $i => $set) {
            $this->info[$i] =
                str_replace(
                    $this->keys,
                    array_values($this->info),
                $set);
        }
        $this->values = array_values($this->info);
        foreach ($this->info as $name => $set) {
            $this->lookup[$name] = $this->convertToLookup($set);
        }
    }
    public function generateChildDef(&$def, $module) {
        if (!empty($def->child)) return; 
        $content_model = $def->content_model;
        if (is_string($content_model)) {
            $def->content_model = str_replace(
                $this->keys, $this->values, $content_model);
        }
        $def->child = $this->getChildDef($def, $module);
    }
    public function getChildDef($def, $module) {
        $value = $def->content_model;
        if (is_object($value)) {
            trigger_error(
                'Literal object child definitions should be stored in '.
                'ElementDef->child not ElementDef->content_model',
                E_USER_NOTICE
            );
            return $value;
        }
        switch ($def->content_model_type) {
            case 'required':
                return new HTMLPurifier_ChildDef_Required($value);
            case 'optional':
                return new HTMLPurifier_ChildDef_Optional($value);
            case 'empty':
                return new HTMLPurifier_ChildDef_Empty();
            case 'custom':
                return new HTMLPurifier_ChildDef_Custom($value);
        }
        $return = false;
        if ($module->defines_child_def) { 
            $return = $module->getChildDef($def);
        }
        if ($return !== false) return $return;
        trigger_error(
            'Could not determine which ChildDef class to instantiate',
            E_USER_ERROR
        );
        return false;
    }
    protected function convertToLookup($string) {
        $array = explode('|', str_replace(' ', '', $string));
        $ret = array();
        foreach ($array as $i => $k) {
            $ret[$k] = true;
        }
        return $ret;
    }
}
class HTMLPurifier_Context
{
    private $_storage = array();
    public function register($name, &$ref) {
        if (isset($this->_storage[$name])) {
            trigger_error("Name $name produces collision, cannot re-register",
                          E_USER_ERROR);
            return;
        }
        $this->_storage[$name] =& $ref;
    }
    public function &get($name, $ignore_error = false) {
        if (!isset($this->_storage[$name])) {
            if (!$ignore_error) {
                trigger_error("Attempted to retrieve non-existent variable $name",
                              E_USER_ERROR);
            }
            $var = null; 
            return $var;
        }
        return $this->_storage[$name];
    }
    public function destroy($name) {
        if (!isset($this->_storage[$name])) {
            trigger_error("Attempted to destroy non-existent variable $name",
                          E_USER_ERROR);
            return;
        }
        unset($this->_storage[$name]);
    }
    public function exists($name) {
        return isset($this->_storage[$name]);
    }
    public function loadArray($context_array) {
        foreach ($context_array as $key => $discard) {
            $this->register($key, $context_array[$key]);
        }
    }
}
abstract class HTMLPurifier_DefinitionCache
{
    public $type;
    public function __construct($type) {
        $this->type = $type;
    }
    public function generateKey($config) {
        return $config->version . ',' . 
               $config->getBatchSerial($this->type) . ',' .
               $config->get($this->type, 'DefinitionRev');
    }
    public function isOld($key, $config) {
        if (substr_count($key, ',') < 2) return true;
        list($version, $hash, $revision) = explode(',', $key, 3);
        $compare = version_compare($version, $config->version);
        if ($compare != 0) return true;
        if (
            $hash == $config->getBatchSerial($this->type) &&
            $revision < $config->get($this->type, 'DefinitionRev')
        ) return true;
        return false;
    }
    public function checkDefType($def) {
        if ($def->type !== $this->type) {
            trigger_error("Cannot use definition of type {$def->type} in cache for {$this->type}");
            return false;
        }
        return true;
    }
    abstract public function add($def, $config);
    abstract public function set($def, $config);
    abstract public function replace($def, $config);
    abstract public function get($config);
    abstract public function remove($config);
    abstract public function flush($config);
    abstract public function cleanup($config);
}
class HTMLPurifier_DefinitionCacheFactory
{
    protected $caches = array('Serializer' => array());
    protected $implementations = array();
    protected $decorators = array();
    public function setup() {
        $this->addDecorator('Cleanup');
    }
    public static function instance($prototype = null) {
        static $instance;
        if ($prototype !== null) {
            $instance = $prototype;
        } elseif ($instance === null || $prototype === true) {
            $instance = new HTMLPurifier_DefinitionCacheFactory();
            $instance->setup();
        }
        return $instance;
    }
    public function register($short, $long) {
        $this->implementations[$short] = $long;
    }
    public function create($type, $config) {
        $method = $config->get('Cache', 'DefinitionImpl');
        if ($method === null) {
            return new HTMLPurifier_DefinitionCache_Null($type);
        }
        if (!empty($this->caches[$method][$type])) {
            return $this->caches[$method][$type];
        }
        if (
          isset($this->implementations[$method]) &&
          class_exists($class = $this->implementations[$method], false)
        ) {
            $cache = new $class($type);
        } else {
            if ($method != 'Serializer') {
                trigger_error("Unrecognized DefinitionCache $method, using Serializer instead", E_USER_WARNING);
            }
            $cache = new HTMLPurifier_DefinitionCache_Serializer($type);
        }
        foreach ($this->decorators as $decorator) {
            $new_cache = $decorator->decorate($cache);
            unset($cache);
            $cache = $new_cache;
        }
        $this->caches[$method][$type] = $cache;
        return $this->caches[$method][$type];
    }
    public function addDecorator($decorator) {
        if (is_string($decorator)) {
            $class = "HTMLPurifier_DefinitionCache_Decorator_$decorator";
            $decorator = new $class;
        }
        $this->decorators[$decorator->name] = $decorator;
    }
}
class HTMLPurifier_Doctype
{
    public $name;
    public $modules = array();
    public $tidyModules = array();
    public $xml = true;
    public $aliases = array();
    public $dtdPublic;
    public $dtdSystem;
    public function __construct($name = null, $xml = true, $modules = array(),
        $tidyModules = array(), $aliases = array(), $dtd_public = null, $dtd_system = null
    ) {
        $this->name         = $name;
        $this->xml          = $xml;
        $this->modules      = $modules;
        $this->tidyModules  = $tidyModules;
        $this->aliases      = $aliases;
        $this->dtdPublic    = $dtd_public;
        $this->dtdSystem    = $dtd_system;
    }
}
class HTMLPurifier_DoctypeRegistry
{
    protected $doctypes;
    protected $aliases;
    public function register($doctype, $xml = true, $modules = array(),
        $tidy_modules = array(), $aliases = array(), $dtd_public = null, $dtd_system = null
    ) {
        if (!is_array($modules)) $modules = array($modules);
        if (!is_array($tidy_modules)) $tidy_modules = array($tidy_modules);
        if (!is_array($aliases)) $aliases = array($aliases);
        if (!is_object($doctype)) {
            $doctype = new HTMLPurifier_Doctype(
                $doctype, $xml, $modules, $tidy_modules, $aliases, $dtd_public, $dtd_system
            );
        }
        $this->doctypes[$doctype->name] = $doctype;
        $name = $doctype->name;
        foreach ($doctype->aliases as $alias) {
            if (isset($this->doctypes[$alias])) continue;
            $this->aliases[$alias] = $name;
        }
        if (isset($this->aliases[$name])) unset($this->aliases[$name]);
        return $doctype;
    }
    public function get($doctype) {
        if (isset($this->aliases[$doctype])) $doctype = $this->aliases[$doctype];
        if (!isset($this->doctypes[$doctype])) {
            trigger_error('Doctype ' . htmlspecialchars($doctype) . ' does not exist', E_USER_ERROR);
            $anon = new HTMLPurifier_Doctype($doctype);
            return $anon;
        }
        return $this->doctypes[$doctype];
    }
    public function make($config) {
        return clone $this->get($this->getDoctypeFromConfig($config));
    }
    public function getDoctypeFromConfig($config) {
        $doctype = $config->get('HTML', 'Doctype');
        if (!empty($doctype)) return $doctype;
        $doctype = $config->get('HTML', 'CustomDoctype');
        if (!empty($doctype)) return $doctype;
        if ($config->get('HTML', 'XHTML')) {
            $doctype = 'XHTML 1.0';
        } else {
            $doctype = 'HTML 4.01';
        }
        if ($config->get('HTML', 'Strict')) {
            $doctype .= ' Strict';
        } else {
            $doctype .= ' Transitional';
        }
        return $doctype;
    }
}
class HTMLPurifier_ElementDef
{
    public $standalone = true;
    public $attr = array();
    public $attr_transform_pre = array();
    public $attr_transform_post = array();
    public $child;
    public $content_model;
    public $content_model_type;
    public $descendants_are_inline = false;
    public $required_attr = array();
    public $excludes = array();
    public static function create($content_model, $content_model_type, $attr) {
        $def = new HTMLPurifier_ElementDef();
        $def->content_model = $content_model;
        $def->content_model_type = $content_model_type;
        $def->attr = $attr;
        return $def;
    }
    public function mergeIn($def) {
        foreach($def->attr as $k => $v) {
            if ($k === 0) {
                foreach ($v as $v2) {
                    $this->attr[0][] = $v2;
                }
                continue;
            }
            if ($v === false) {
                if (isset($this->attr[$k])) unset($this->attr[$k]);
                continue;
            }
            $this->attr[$k] = $v;
        }
        $this->_mergeAssocArray($this->attr_transform_pre, $def->attr_transform_pre);
        $this->_mergeAssocArray($this->attr_transform_post, $def->attr_transform_post);
        $this->_mergeAssocArray($this->excludes, $def->excludes);
        if(!empty($def->content_model)) {
            $this->content_model .= ' | ' . $def->content_model;
            $this->child = false;
        }
        if(!empty($def->content_model_type)) {
            $this->content_model_type = $def->content_model_type;
            $this->child = false;
        }
        if(!is_null($def->child)) $this->child = $def->child;
        if($def->descendants_are_inline) $this->descendants_are_inline = $def->descendants_are_inline;
    }
    private function _mergeAssocArray(&$a1, $a2) {
        foreach ($a2 as $k => $v) {
            if ($v === false) {
                if (isset($a1[$k])) unset($a1[$k]);
                continue;
            }
            $a1[$k] = $v;
        }
    }
}
class HTMLPurifier_Encoder
{
    private function __construct() {
        trigger_error('Cannot instantiate encoder, call methods statically', E_USER_ERROR);
    }
    private static function muteErrorHandler() {}
    public static function cleanUTF8($str, $force_php = false) {
        static $non_sgml_chars = array();
        if (empty($non_sgml_chars)) {
            for ($i = 0; $i <= 31; $i++) {
                if ($i == 9 || $i == 13 || $i == 10) continue;
                $non_sgml_chars[chr($i)] = '';
            }
            for ($i = 127; $i <= 159; $i++) {
                $non_sgml_chars[HTMLPurifier_Encoder::unichr($i)] = '';
            }
        }
        static $iconv = null;
        if ($iconv === null) $iconv = function_exists('iconv');
        if (preg_match('/^.{1}/us', $str)) {
            return strtr($str, $non_sgml_chars);
        }
        if ($iconv && !$force_php) {
            set_error_handler(array('HTMLPurifier_Encoder', 'muteErrorHandler'));
            $str = iconv('UTF-8', 'UTF-8//IGNORE', $str);
            restore_error_handler();
            return strtr($str, $non_sgml_chars);
        }
        $mState = 0;  
        $mUcs4  = 0;  
        $mBytes = 1;  
        $out = '';
        $char = '';
        $len = strlen($str);
        for($i = 0; $i < $len; $i++) {
            $in = ord($str{$i});
            $char .= $str[$i];  
            if (0 == $mState) {
                if (0 == (0x80 & ($in))) {
                    if (($in <= 31 || $in == 127) && 
                        !($in == 9 || $in == 13 || $in == 10)  
                    ) {
                    } else {
                        $out .= $char;
                    }
                    $char = '';
                    $mBytes = 1;
                } elseif (0xC0 == (0xE0 & ($in))) {
                    $mUcs4 = ($in);
                    $mUcs4 = ($mUcs4 & 0x1F) << 6;
                    $mState = 1;
                    $mBytes = 2;
                } elseif (0xE0 == (0xF0 & ($in))) {
                    $mUcs4 = ($in);
                    $mUcs4 = ($mUcs4 & 0x0F) << 12;
                    $mState = 2;
                    $mBytes = 3;
                } elseif (0xF0 == (0xF8 & ($in))) {
                    $mUcs4 = ($in);
                    $mUcs4 = ($mUcs4 & 0x07) << 18;
                    $mState = 3;
                    $mBytes = 4;
                } elseif (0xF8 == (0xFC & ($in))) {
                    $mUcs4 = ($in);
                    $mUcs4 = ($mUcs4 & 0x03) << 24;
                    $mState = 4;
                    $mBytes = 5;
                } elseif (0xFC == (0xFE & ($in))) {
                    $mUcs4 = ($in);
                    $mUcs4 = ($mUcs4 & 1) << 30;
                    $mState = 5;
                    $mBytes = 6;
                } else {
                    $mState = 0;
                    $mUcs4  = 0;
                    $mBytes = 1;
                    $char = '';
                }
            } else {
                if (0x80 == (0xC0 & ($in))) {
                    $shift = ($mState - 1) * 6;
                    $tmp = $in;
                    $tmp = ($tmp & 0x0000003F) << $shift;
                    $mUcs4 |= $tmp;
                    if (0 == --$mState) {
                        if (((2 == $mBytes) && ($mUcs4 < 0x0080)) ||
                            ((3 == $mBytes) && ($mUcs4 < 0x0800)) ||
                            ((4 == $mBytes) && ($mUcs4 < 0x10000)) ||
                            (4 < $mBytes) ||
                            (($mUcs4 & 0xFFFFF800) == 0xD800) ||
                            ($mUcs4 > 0x10FFFF)
                        ) {
                        } elseif (0xFEFF != $mUcs4 &&  
                            !($mUcs4 >= 128 && $mUcs4 <= 159)  
                        ) {
                            $out .= $char;
                        }
                        $mState = 0;
                        $mUcs4  = 0;
                        $mBytes = 1;
                        $char = '';
                    }
                } else {
                    $mState = 0;
                    $mUcs4  = 0;
                    $mBytes = 1;
                    $char ='';
                }
            }
        }
        return $out;
    }
    public static function unichr($code) {
        if($code > 1114111 or $code < 0 or
          ($code >= 55296 and $code <= 57343) ) {
            return '';
        }
        $x = $y = $z = $w = 0; 
        if ($code < 128) {
            $x = $code;
        } else {
            $x = ($code & 63) | 128;
            if ($code < 2048) {
                $y = (($code & 2047) >> 6) | 192;
            } else {
                $y = (($code & 4032) >> 6) | 128;
                if($code < 65536) {
                    $z = (($code >> 12) & 15) | 224;
                } else {
                    $z = (($code >> 12) & 63) | 128;
                    $w = (($code >> 18) & 7)  | 240;
                }
            } 
        }
        $ret = '';
        if($w) $ret .= chr($w);
        if($z) $ret .= chr($z);
        if($y) $ret .= chr($y);
        $ret .= chr($x); 
        return $ret;
    }
    public static function convertToUTF8($str, $config, $context) {
        static $iconv = null;
        if ($iconv === null) $iconv = function_exists('iconv');
        $encoding = $config->get('Core', 'Encoding');
        if ($encoding === 'utf-8') return $str;
        if ($iconv && !$config->get('Test', 'ForceNoIconv')) {
            set_error_handler(array('HTMLPurifier_Encoder', 'muteErrorHandler'));
            $str = iconv($encoding, 'utf-8//IGNORE', $str);
            restore_error_handler();
            return $str;
        } elseif ($encoding === 'iso-8859-1') {
            set_error_handler(array('HTMLPurifier_Encoder', 'muteErrorHandler'));
            $str = utf8_encode($str);
            restore_error_handler();
            return $str;
        }
        trigger_error('Encoding not supported', E_USER_ERROR);
    }
    public static function convertFromUTF8($str, $config, $context) {
        static $iconv = null;
        if ($iconv === null) $iconv = function_exists('iconv');
        $encoding = $config->get('Core', 'Encoding');
        if ($encoding === 'utf-8') return $str;
        if ($config->get('Core', 'EscapeNonASCIICharacters')) {
            $str = HTMLPurifier_Encoder::convertToASCIIDumbLossless($str);
        }
        if ($iconv && !$config->get('Test', 'ForceNoIconv')) {
            set_error_handler(array('HTMLPurifier_Encoder', 'muteErrorHandler'));
            $str = iconv('utf-8', $encoding . '//IGNORE', $str);
            restore_error_handler();
            return $str;
        } elseif ($encoding === 'iso-8859-1') {
            set_error_handler(array('HTMLPurifier_Encoder', 'muteErrorHandler'));
            $str = utf8_decode($str);
            restore_error_handler();
            return $str;
        }
        trigger_error('Encoding not supported', E_USER_ERROR);
    }
    public static function convertToASCIIDumbLossless($str) {
        $bytesleft = 0;
        $result = '';
        $working = 0;
        $len = strlen($str);
        for( $i = 0; $i < $len; $i++ ) {
            $bytevalue = ord( $str[$i] );
            if( $bytevalue <= 0x7F ) { 
                $result .= chr( $bytevalue );
                $bytesleft = 0;
            } elseif( $bytevalue <= 0xBF ) { 
                $working = $working << 6;
                $working += ($bytevalue & 0x3F);
                $bytesleft--;
                if( $bytesleft <= 0 ) {
                    $result .= "&#" . $working . ";";
                }
            } elseif( $bytevalue <= 0xDF ) { 
                $working = $bytevalue & 0x1F;
                $bytesleft = 1;
            } elseif( $bytevalue <= 0xEF ) { 
                $working = $bytevalue & 0x0F;
                $bytesleft = 2;
            } else { 
                $working = $bytevalue & 0x07;
                $bytesleft = 3;
            }
        }
        return $result;
    }
}
class HTMLPurifier_EntityLookup {
    public $table;
    public function setup($file = false) {
        if (!$file) {
            $file = HTMLPURIFIER_PREFIX . '/HTMLPurifier/EntityLookup/entities.ser';
        }
        $this->table = unserialize(file_get_contents($file));
    }
    public static function instance($prototype = false) {
        static $instance = null;
        if ($prototype) {
            $instance = $prototype;
        } elseif (!$instance) {
            $instance = new HTMLPurifier_EntityLookup();
            $instance->setup();
        }
        return $instance;
    }
}
class HTMLPurifier_EntityParser
{
    protected $_entity_lookup;
    protected $_substituteEntitiesRegex =
'/&(?:[#]x([a-fA-F0-9]+)|[#]0*(\d+)|([A-Za-z_:][A-Za-z0-9.\-_:]*));?/';
    protected $_special_dec2str =
            array(
                    34 => '"',
                    38 => '&',
                    39 => "'",
                    60 => '<',
                    62 => '>'
            );
    protected $_special_ent2dec =
            array(
                    'quot' => 34,
                    'amp'  => 38,
                    'lt'   => 60,
                    'gt'   => 62
            );
    public function substituteNonSpecialEntities($string) {
        return preg_replace_callback(
            $this->_substituteEntitiesRegex,
            array($this, 'nonSpecialEntityCallback'),
            $string
            );
    }
    protected function nonSpecialEntityCallback($matches) {
        $entity = $matches[0];
        $is_num = (@$matches[0][1] === '#');
        if ($is_num) {
            $is_hex = (@$entity[2] === 'x');
            $code = $is_hex ? hexdec($matches[1]) : (int) $matches[2];
            if (isset($this->_special_dec2str[$code]))  return $entity;
            return HTMLPurifier_Encoder::unichr($code);
        } else {
            if (isset($this->_special_ent2dec[$matches[3]])) return $entity;
            if (!$this->_entity_lookup) {
                $this->_entity_lookup = HTMLPurifier_EntityLookup::instance();
            }
            if (isset($this->_entity_lookup->table[$matches[3]])) {
                return $this->_entity_lookup->table[$matches[3]];
            } else {
                return $entity;
            }
        }
    }
    public function substituteSpecialEntities($string) {
        return preg_replace_callback(
            $this->_substituteEntitiesRegex,
            array($this, 'specialEntityCallback'),
            $string);
    }
    protected function specialEntityCallback($matches) {
        $entity = $matches[0];
        $is_num = (@$matches[0][1] === '#');
        if ($is_num) {
            $is_hex = (@$entity[2] === 'x');
            $int = $is_hex ? hexdec($matches[1]) : (int) $matches[2];
            return isset($this->_special_dec2str[$int]) ?
                $this->_special_dec2str[$int] :
                $entity;
        } else {
            return isset($this->_special_ent2dec[$matches[3]]) ?
                $this->_special_ent2dec[$matches[3]] :
                $entity;
        }
    }
}
class HTMLPurifier_ErrorCollector
{
    protected $errors = array();
    protected $locale;
    protected $generator;
    protected $context;
    public function __construct($context) {
        $this->locale    =& $context->get('Locale');
        $this->generator =& $context->get('Generator');
        $this->context   = $context;
    }
    public function send($severity, $msg) {
        $args = array();
        if (func_num_args() > 2) {
            $args = func_get_args();
            array_shift($args);
            unset($args[0]);
        }
        $token = $this->context->get('CurrentToken', true);
        $line  = $token ? $token->line : $this->context->get('CurrentLine', true);
        $attr  = $this->context->get('CurrentAttr', true);
        $subst = array();
        if (!is_null($token)) {
            $args['CurrentToken'] = $token;
        }
        if (!is_null($attr)) {
            $subst['$CurrentAttr.Name'] = $attr;
            if (isset($token->attr[$attr])) $subst['$CurrentAttr.Value'] = $token->attr[$attr];
        }
        if (empty($args)) {
            $msg = $this->locale->getMessage($msg);
        } else {
            $msg = $this->locale->formatMessage($msg, $args);
        }
        if (!empty($subst)) $msg = strtr($msg, $subst);
        $this->errors[] = array($line, $severity, $msg);
    }
    public function getRaw() {
        return $this->errors;
    }
    public function getHTMLFormatted($config) {
        $ret = array();
        $errors = $this->errors;
        if ($config->get('Core', 'MaintainLineNumbers') !== false) {
            $has_line       = array();
            $lines          = array();
            $original_order = array();
            foreach ($errors as $i => $error) {
                $has_line[] = (int) (bool) $error[0];
                $lines[] = $error[0];
                $original_order[] = $i;
            }
            array_multisort($has_line, SORT_DESC, $lines, SORT_ASC, $original_order, SORT_ASC, $errors);
        }
        foreach ($errors as $error) {
            list($line, $severity, $msg) = $error;
            $string = '';
            $string .= '<strong>' . $this->locale->getErrorName($severity) . '</strong>: ';
            $string .= $this->generator->escape($msg); 
            if ($line) {
                $string .= $this->locale->formatMessage(
                    'ErrorCollector: At line', array('line' => $line));
            }
            $ret[] = $string;
        }
        if (empty($errors)) {
            return '<p>' . $this->locale->getMessage('ErrorCollector: No errors') . '</p>';
        } else {
            return '<ul><li>' . implode('</li><li>', $ret) . '</li></ul>';
        }
    }
}
class HTMLPurifier_Exception extends Exception
{
}
class HTMLPurifier_Filter
{
    public $name;
    public function preFilter($html, $config, $context) {
        return $html;
    }
    public function postFilter($html, $config, $context) {
        return $html;
    }
}
class HTMLPurifier_Generator
{
    private $_xhtml = true;
    private $_scriptFix = false;
    private $_def;
    protected $config;
    public function __construct($config = null, $context = null) {
        if (!$config) $config = HTMLPurifier_Config::createDefault();
        $this->config = $config;
        $this->_scriptFix = $config->get('Output', 'CommentScriptContents');
        $this->_def = $config->getHTMLDefinition();
        $this->_xhtml = $this->_def->doctype->xml;
    }
    public function generateFromTokens($tokens) {
        if (!$tokens) return '';
        $html = '';
        for ($i = 0, $size = count($tokens); $i < $size; $i++) {
            if ($this->_scriptFix && $tokens[$i]->name === 'script'
                && $i + 2 < $size && $tokens[$i+2] instanceof HTMLPurifier_Token_End) {
                $html .= $this->generateFromToken($tokens[$i++]);
                $html .= $this->generateScriptFromToken($tokens[$i++]);
            }
            $html .= $this->generateFromToken($tokens[$i]);
        }
        if (extension_loaded('tidy') && $this->config->get('Output', 'TidyFormat')) {
            $tidy = new Tidy;
            $tidy->parseString($html, array(
               'indent'=> true,
               'output-xhtml' => $this->_xhtml,
               'show-body-only' => true,
               'indent-spaces' => 2,
               'wrap' => 68,
            ), 'utf8');
            $tidy->cleanRepair();
            $html = (string) $tidy;  
        }
        $nl = $this->config->get('Output', 'Newline');
        if ($nl === null) $nl = PHP_EOL;
        if ($nl !== "\n") $html = str_replace("\n", $nl, $html);
        return $html;
    }
    public function generateFromToken($token) {
        if (!$token instanceof HTMLPurifier_Token) {
            trigger_error('Cannot generate HTML from non-HTMLPurifier_Token object', E_USER_WARNING);
            return '';
        } elseif ($token instanceof HTMLPurifier_Token_Start) {
            $attr = $this->generateAttributes($token->attr, $token->name);
            return '<' . $token->name . ($attr ? ' ' : '') . $attr . '>';
        } elseif ($token instanceof HTMLPurifier_Token_End) {
            return '</' . $token->name . '>';
        } elseif ($token instanceof HTMLPurifier_Token_Empty) {
            $attr = $this->generateAttributes($token->attr, $token->name);
             return '<' . $token->name . ($attr ? ' ' : '') . $attr .
                ( $this->_xhtml ? ' /': '' )  
                . '>';
        } elseif ($token instanceof HTMLPurifier_Token_Text) {
            return $this->escape($token->data, ENT_NOQUOTES);
        } elseif ($token instanceof HTMLPurifier_Token_Comment) {
            return '<!--' . $token->data . '-->';
        } else {
            return '';
        }
    }
    public function generateScriptFromToken($token) {
        if (!$token instanceof HTMLPurifier_Token_Text) return $this->generateFromToken($token);
        $data = preg_replace('#//\s*$#', '', $token->data);
        return '<!--//--><![CDATA[//><!--' . "\n" . trim($data) . "\n" . '//--><!]]>';
    }
    public function generateAttributes($assoc_array_of_attributes, $element = false) {
        $html = '';
        foreach ($assoc_array_of_attributes as $key => $value) {
            if (!$this->_xhtml) {
                if (strpos($key, ':') !== false) continue;
                if ($element && !empty($this->_def->info[$element]->attr[$key]->minimized)) {
                    $html .= $key . ' ';
                    continue;
                }
            }
            $html .= $key.'="'.$this->escape($value).'" ';
        }
        return rtrim($html);
    }
    public function escape($string, $quote = ENT_COMPAT) {
        return htmlspecialchars($string, $quote, 'UTF-8');
    }
}
class HTMLPurifier_HTMLDefinition extends HTMLPurifier_Definition
{
    public $info = array();
    public $info_global_attr = array();
    public $info_parent = 'div';
    public $info_parent_def;
    public $info_block_wrapper = 'p';
    public $info_tag_transform = array();
    public $info_attr_transform_pre = array();
    public $info_attr_transform_post = array();
    public $info_content_sets = array();
    public $doctype;
    public function addAttribute($element_name, $attr_name, $def) {
        $module = $this->getAnonymousModule();
        if (!isset($module->info[$element_name])) {
            $element = $module->addBlankElement($element_name);
        } else {
            $element = $module->info[$element_name];
        }
        $element->attr[$attr_name] = $def;
    }
    public function addElement($element_name, $type, $contents, $attr_collections, $attributes) {
        $module = $this->getAnonymousModule();
        $element = $module->addElement($element_name, $type, $contents, $attr_collections, $attributes);
        return $element;
    }
    public function addBlankElement($element_name) {
        $module  = $this->getAnonymousModule();
        $element = $module->addBlankElement($element_name);
        return $element;
    }
    public function getAnonymousModule() {
        if (!$this->_anonModule) {
            $this->_anonModule = new HTMLPurifier_HTMLModule();
            $this->_anonModule->name = 'Anonymous';
        }
        return $this->_anonModule;
    }
    private $_anonModule;
    public $type = 'HTML';
    public $manager; /**< Instance of HTMLPurifier_HTMLModuleManager */
    public function __construct() {
        $this->manager = new HTMLPurifier_HTMLModuleManager();
    }
    protected function doSetup($config) {
        $this->processModules($config);
        $this->setupConfigStuff($config);
        unset($this->manager);
        foreach ($this->info as $k => $v) {
            unset($this->info[$k]->content_model);
            unset($this->info[$k]->content_model_type);
        }
    }
    protected function processModules($config) {
        if ($this->_anonModule) {
            $this->manager->addModule($this->_anonModule);
            unset($this->_anonModule);
        }
        $this->manager->setup($config);
        $this->doctype = $this->manager->doctype;
        foreach ($this->manager->modules as $module) {
            foreach($module->info_tag_transform         as $k => $v) {
                if ($v === false) unset($this->info_tag_transform[$k]);
                else $this->info_tag_transform[$k] = $v;
            }
            foreach($module->info_attr_transform_pre    as $k => $v) {
                if ($v === false) unset($this->info_attr_transform_pre[$k]);
                else $this->info_attr_transform_pre[$k] = $v;
            }
            foreach($module->info_attr_transform_post   as $k => $v) {
                if ($v === false) unset($this->info_attr_transform_post[$k]);
                else $this->info_attr_transform_post[$k] = $v;
            }
        }
        $this->info = $this->manager->getElements();
        $this->info_content_sets = $this->manager->contentSets->lookup;
    }
    protected function setupConfigStuff($config) {
        $block_wrapper = $config->get('HTML', 'BlockWrapper');
        if (isset($this->info_content_sets['Block'][$block_wrapper])) {
            $this->info_block_wrapper = $block_wrapper;
        } else {
            trigger_error('Cannot use non-block element as block wrapper',
                E_USER_ERROR);
        }
        $parent = $config->get('HTML', 'Parent');
        $def = $this->manager->getElement($parent, true);
        if ($def) {
            $this->info_parent = $parent;
            $this->info_parent_def = $def;
        } else {
            trigger_error('Cannot use unrecognized element as parent',
                E_USER_ERROR);
            $this->info_parent_def = $this->manager->getElement($this->info_parent, true);
        }
        $support = "(for information on implementing this, see the ".
                   "support forums) ";
        $allowed_elements = $config->get('HTML', 'AllowedElements');
        $allowed_attributes = $config->get('HTML', 'AllowedAttributes');  
        if (!is_array($allowed_elements) && !is_array($allowed_attributes)) {
            $allowed = $config->get('HTML', 'Allowed');
            if (is_string($allowed)) {
                list($allowed_elements, $allowed_attributes) = $this->parseTinyMCEAllowedList($allowed);
            }
        }
        if (is_array($allowed_elements)) {
            foreach ($this->info as $name => $d) {
                if(!isset($allowed_elements[$name])) unset($this->info[$name]);
                unset($allowed_elements[$name]);
            }
            foreach ($allowed_elements as $element => $d) {
                $element = htmlspecialchars($element);  
                trigger_error("Element '$element' is not supported $support", E_USER_WARNING);
            }
        }
        $allowed_attributes_mutable = $allowed_attributes;  
        if (is_array($allowed_attributes)) {
            foreach ($this->info_global_attr as $attr => $x) {
                $keys = array($attr, "*@$attr", "*.$attr");
                $delete = true;
                foreach ($keys as $key) {
                    if ($delete && isset($allowed_attributes[$key])) {
                        $delete = false;
                    }
                    if (isset($allowed_attributes_mutable[$key])) {
                        unset($allowed_attributes_mutable[$key]);
                    }
                }
                if ($delete) unset($this->info_global_attr[$attr]);
            }
            foreach ($this->info as $tag => $info) {
                foreach ($info->attr as $attr => $x) {
                    $keys = array("$tag@$attr", $attr, "*@$attr", "$tag.$attr", "*.$attr");
                    $delete = true;
                    foreach ($keys as $key) {
                        if ($delete && isset($allowed_attributes[$key])) {
                            $delete = false;
                        }
                        if (isset($allowed_attributes_mutable[$key])) {
                            unset($allowed_attributes_mutable[$key]);
                        }
                    }
                    if ($delete) unset($this->info[$tag]->attr[$attr]);
                }
            }
            foreach ($allowed_attributes_mutable as $elattr => $d) {
                $bits = preg_split('/[.@]/', $elattr, 2);
                $c = count($bits);
                switch ($c) {
                    case 2:
                        if ($bits[0] !== '*') {
                            $element = htmlspecialchars($bits[0]);
                            $attribute = htmlspecialchars($bits[1]);
                            if (!isset($this->info[$element])) {
                                trigger_error("Cannot allow attribute '$attribute' if element '$element' is not allowed/supported $support");
                            } else {
                                trigger_error("Attribute '$attribute' in element '$element' not supported $support",
                                    E_USER_WARNING);
                            }
                            break;
                        }
                    case 1:
                        $attribute = htmlspecialchars($bits[0]);
                        trigger_error("Global attribute '$attribute' is not ".
                            "supported in any elements $support",
                            E_USER_WARNING);
                        break;
                }
            }
        }
        $forbidden_elements   = $config->get('HTML', 'ForbiddenElements');
        $forbidden_attributes = $config->get('HTML', 'ForbiddenAttributes');
        foreach ($this->info as $tag => $info) {
            if (isset($forbidden_elements[$tag])) {
                unset($this->info[$tag]);
                continue;
            }
            foreach ($info->attr as $attr => $x) {
                if (
                    isset($forbidden_attributes["$tag@$attr"]) ||
                    isset($forbidden_attributes["*@$attr"]) ||
                    isset($forbidden_attributes[$attr])
                ) {
                    unset($this->info[$tag]->attr[$attr]);
                    continue;
                }  
                elseif (isset($forbidden_attributes["$tag.$attr"])) {
                    trigger_error("Error with $tag.$attr: tag.attr syntax not supported for HTML.ForbiddenAttributes; use tag@attr instead", E_USER_WARNING);
                }
            }
        }
        foreach ($forbidden_attributes as $key => $v) {
            if (strlen($key) < 2) continue;
            if ($key[0] != '*') continue;
            if ($key[1] == '.') {
                trigger_error("Error with $key: *.attr syntax not supported for HTML.ForbiddenAttributes; use attr instead", E_USER_WARNING);
            }
        }
    }
    public function parseTinyMCEAllowedList($list) {
        $list = str_replace(array(' ', "\t"), '', $list);
        $elements = array();
        $attributes = array();
        $chunks = preg_split('/(,|[\n\r]+)/', $list);
        foreach ($chunks as $chunk) {
            if (empty($chunk)) continue;
            if (!strpos($chunk, '[')) {
                $element = $chunk;
                $attr = false;
            } else {
                list($element, $attr) = explode('[', $chunk);
            }
            if ($element !== '*') $elements[$element] = true;
            if (!$attr) continue;
            $attr = substr($attr, 0, strlen($attr) - 1);  
            $attr = explode('|', $attr);
            foreach ($attr as $key) {
                $attributes["$element.$key"] = true;
            }
        }
        return array($elements, $attributes);
    }
}
class HTMLPurifier_HTMLModule
{
    public $name;
    public $elements = array();
    public $info = array();
    public $content_sets = array();
    public $attr_collections = array();
    public $info_tag_transform = array();
    public $info_attr_transform_pre = array();
    public $info_attr_transform_post = array();
    public $defines_child_def = false;
    public $safe = true;
    public function getChildDef($def) {return false;}
    public function addElement($element, $type, $contents, $attr_includes = array(), $attr = array()) {
        $this->elements[] = $element;
        list($content_model_type, $content_model) = $this->parseContents($contents);
        $this->mergeInAttrIncludes($attr, $attr_includes);
        if ($type) $this->addElementToContentSet($element, $type);
        $this->info[$element] = HTMLPurifier_ElementDef::create(
            $content_model, $content_model_type, $attr
        );
        if (!is_string($contents)) $this->info[$element]->child = $contents;
        return $this->info[$element];
    }
    public function addBlankElement($element) {
        if (!isset($this->info[$element])) {
            $this->elements[] = $element;
            $this->info[$element] = new HTMLPurifier_ElementDef();
            $this->info[$element]->standalone = false;
        } else {
            trigger_error("Definition for $element already exists in module, cannot redefine");
        }
        return $this->info[$element];
    }
    public function addElementToContentSet($element, $type) {
        if (!isset($this->content_sets[$type])) $this->content_sets[$type] = '';
        else $this->content_sets[$type] .= ' | ';
        $this->content_sets[$type] .= $element;
    }
    public function parseContents($contents) {
        if (!is_string($contents)) return array(null, null);  
        switch ($contents) {
            case 'Empty':
                return array('empty', '');
            case 'Inline':
                return array('optional', 'Inline | #PCDATA');
            case 'Flow':
                return array('optional', 'Flow | #PCDATA');
        }
        list($content_model_type, $content_model) = explode(':', $contents);
        $content_model_type = strtolower(trim($content_model_type));
        $content_model = trim($content_model);
        return array($content_model_type, $content_model);
    }
    public function mergeInAttrIncludes(&$attr, $attr_includes) {
        if (!is_array($attr_includes)) {
            if (empty($attr_includes)) $attr_includes = array();
            else $attr_includes = array($attr_includes);
        }
        $attr[0] = $attr_includes;
    }
    public function makeLookup($list) {
        if (is_string($list)) $list = func_get_args();
        $ret = array();
        foreach ($list as $value) {
            if (is_null($value)) continue;
            $ret[$value] = true;
        }
        return $ret;
    }
}
class HTMLPurifier_HTMLModuleManager
{
    public $doctypes;
    public $doctype;
    public $attrTypes;
    public $modules = array();
    public $registeredModules = array();
    public $userModules = array();
    public $elementLookup = array();
    /** List of prefixes we should use for registering small names */
    public $prefixes = array('HTMLPurifier_HTMLModule_');
    public $contentSets;     /**< Instance of HTMLPurifier_ContentSets */
    public $attrCollections; /**< Instance of HTMLPurifier_AttrCollections */
    /** If set to true, unsafe elements and attributes will be allowed */
    public $trusted = false;
    public function __construct() {
        $this->attrTypes = new HTMLPurifier_AttrTypes();
        $this->doctypes  = new HTMLPurifier_DoctypeRegistry();
        $common = array(
            'CommonAttributes', 'Text', 'Hypertext', 'List',
            'Presentation', 'Edit', 'Bdo', 'Tables', 'Image',
            'StyleAttribute', 'Scripting', 'Object'
        );
        $transitional = array('Legacy', 'Target');
        $xml = array('XMLCommonAttributes');
        $non_xml = array('NonXMLCommonAttributes');
        $this->doctypes->register(
            'HTML 4.01 Transitional', false,
            array_merge($common, $transitional, $non_xml),
            array('Tidy_Transitional', 'Tidy_Proprietary'),
            array(),
            '-//W3C//DTD HTML 4.01 Transitional//EN',
            'http://www.w3.org/TR/html4/loose.dtd'
        );
        $this->doctypes->register(
            'HTML 4.01 Strict', false,
            array_merge($common, $non_xml),
            array('Tidy_Strict', 'Tidy_Proprietary'),
            array(),
            '-//W3C//DTD HTML 4.01//EN',
            'http://www.w3.org/TR/html4/strict.dtd'
        );
        $this->doctypes->register(
            'XHTML 1.0 Transitional', true,
            array_merge($common, $transitional, $xml, $non_xml),
            array('Tidy_Transitional', 'Tidy_XHTML', 'Tidy_Proprietary'),
            array(),
            '-//W3C//DTD XHTML 1.0 Transitional//EN',
            'http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd'
        );
        $this->doctypes->register(
            'XHTML 1.0 Strict', true,
            array_merge($common, $xml, $non_xml),
            array('Tidy_Strict', 'Tidy_XHTML', 'Tidy_Strict', 'Tidy_Proprietary'),
            array(),
            '-//W3C//DTD XHTML 1.0 Strict//EN',
            'http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd'
        );
        $this->doctypes->register(
            'XHTML 1.1', true,
            array_merge($common, $xml, array('Ruby')),
            array('Tidy_Strict', 'Tidy_XHTML', 'Tidy_Proprietary', 'Tidy_Strict'),  
            array(),
            '-//W3C//DTD XHTML 1.1//EN',
            'http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd'
        );
    }
    public function registerModule($module, $overload = false) {
        if (is_string($module)) {
            $original_module = $module;
            $ok = false;
            foreach ($this->prefixes as $prefix) {
                $module = $prefix . $original_module;
                if (class_exists($module)) {
                    $ok = true;
                    break;
                }
            }
            if (!$ok) {
                $module = $original_module;
                if (!class_exists($module)) {
                    trigger_error($original_module . ' module does not exist',
                        E_USER_ERROR);
                    return;
                }
            }
            $module = new $module();
        }
        if (empty($module->name)) {
            trigger_error('Module instance of ' . get_class($module) . ' must have name');
            return;
        }
        if (!$overload && isset($this->registeredModules[$module->name])) {
            trigger_error('Overloading ' . $module->name . ' without explicit overload parameter', E_USER_WARNING);
        }
        $this->registeredModules[$module->name] = $module;
    }
    public function addModule($module) {
        $this->registerModule($module);
        if (is_object($module)) $module = $module->name;
        $this->userModules[] = $module;
    }
    public function addPrefix($prefix) {
        $this->prefixes[] = $prefix;
    }
    public function setup($config) {
        $this->trusted = $config->get('HTML', 'Trusted');
        $this->doctype = $this->doctypes->make($config);
        $modules = $this->doctype->modules;
        $lookup = $config->get('HTML', 'AllowedModules');
        $special_cases = $config->get('HTML', 'CoreModules');
        if (is_array($lookup)) {
            foreach ($modules as $k => $m) {
                if (isset($special_cases[$m])) continue;
                if (!isset($lookup[$m])) unset($modules[$k]);
            }
        }
        $modules = array_merge($modules, $this->userModules);
        if ($config->get('HTML', 'Proprietary')) {
            $modules[] = 'Proprietary';
        }
        foreach ($modules as $module) {
            $this->processModule($module);
        }
        foreach ($this->doctype->tidyModules as $module) {
            $this->processModule($module);
            if (method_exists($this->modules[$module], 'construct')) {
                $this->modules[$module]->construct($config);
            }
        }
        foreach ($this->modules as $module) {
            foreach ($module->info as $name => $def) {
                if (!isset($this->elementLookup[$name])) {
                    $this->elementLookup[$name] = array();
                }
                $this->elementLookup[$name][] = $module->name;
            }
        }
        $this->contentSets = new HTMLPurifier_ContentSets(
            $this->modules
        );
        $this->attrCollections = new HTMLPurifier_AttrCollections(
            $this->attrTypes,
            $this->modules
        );
    }
    public function processModule($module) {
        if (!isset($this->registeredModules[$module]) || is_object($module)) {
            $this->registerModule($module);
        }
        $this->modules[$module] = $this->registeredModules[$module];
    }
    public function getElements() {
        $elements = array();
        foreach ($this->modules as $module) {
            if (!$this->trusted && !$module->safe) continue;
            foreach ($module->info as $name => $v) {
                if (isset($elements[$name])) continue;
                $elements[$name] = $this->getElement($name);
            }
        }
        foreach ($elements as $n => $v) {
            if ($v === false) unset($elements[$n]);
        }
        return $elements;
    }
    public function getElement($name, $trusted = null) {
        if (!isset($this->elementLookup[$name])) {
            return false;
        }
        $def = false;
        if ($trusted === null) $trusted = $this->trusted;
        foreach($this->elementLookup[$name] as $module_name) {
            $module = $this->modules[$module_name];
            if (!$trusted && !$module->safe) {
                continue;
            }
            $new_def = clone $module->info[$name];
            if (!$def && $new_def->standalone) {
                $def = $new_def;
            } elseif ($def) {
                $def->mergeIn($new_def);
            } else {
                continue;
            }
            $this->attrCollections->performInclusions($def->attr);
            $this->attrCollections->expandIdentifiers($def->attr, $this->attrTypes);
            if (is_string($def->content_model) &&
                strpos($def->content_model, 'Inline') !== false) {
                if ($name != 'del' && $name != 'ins') {
                    $def->descendants_are_inline = true;
                }
            }
            $this->contentSets->generateChildDef($def, $module);
        }
        foreach ($def->attr as $attr_name => $attr_def) {
            if ($attr_def->required) {
                $def->required_attr[] = $attr_name;
            }
        }
        return $def;
    }
}
class HTMLPurifier_IDAccumulator
{
    public $ids = array();
    public static function build($config, $context) {
        $id_accumulator = new HTMLPurifier_IDAccumulator();
        $id_accumulator->load($config->get('Attr', 'IDBlacklist'));
        return $id_accumulator;
    }
    public function add($id) {
        if (isset($this->ids[$id])) return false;
        return $this->ids[$id] = true;
    }
    public function load($array_of_ids) {
        foreach ($array_of_ids as $id) {
            $this->ids[$id] = true;
        }
    }
}
abstract class HTMLPurifier_Injector
{
    public $name;
    public $skip = 1;
    protected $htmlDefinition;
    protected $currentNesting;
    protected $inputTokens;
    protected $inputIndex;
    public $needed = array();
    public function prepare($config, $context) {
        $this->htmlDefinition = $config->getHTMLDefinition();
        foreach ($this->needed as $element => $attributes) {
            if (is_int($element)) $element = $attributes;
            if (!isset($this->htmlDefinition->info[$element])) return $element;
            if (!is_array($attributes)) continue;
            foreach ($attributes as $name) {
                if (!isset($this->htmlDefinition->info[$element]->attr[$name])) return "$element.$name";
            }
        }
        $this->currentNesting =& $context->get('CurrentNesting');
        $this->inputTokens    =& $context->get('InputTokens');
        $this->inputIndex     =& $context->get('InputIndex');
        return false;
    }
    public function allowsElement($name) {
        if (!empty($this->currentNesting)) {
            $parent_token = array_pop($this->currentNesting);
            $this->currentNesting[] = $parent_token;
            $parent = $this->htmlDefinition->info[$parent_token->name];
        } else {
            $parent = $this->htmlDefinition->info_parent_def;
        }
        if (!isset($parent->child->elements[$name]) || isset($parent->excludes[$name])) {
            return false;
        }
        return true;
    }
    public function handleText(&$token) {}
    public function handleElement(&$token) {}
    public function notifyEnd($token) {}
}
class HTMLPurifier_Language
{
    public $code = 'en';
    public $fallback = false;
    public $messages = array();
    public $errorNames = array();
    public $error = false;
    public $_loaded = false;
    protected $config, $context;
    public function __construct($config, $context) {
        $this->config  = $config;
        $this->context = $context;
    }
    public function load() {
        if ($this->_loaded) return;
        $factory = HTMLPurifier_LanguageFactory::instance();
        $factory->loadLanguage($this->code);
        foreach ($factory->keys as $key) {
            $this->$key = $factory->cache[$this->code][$key];
        }
        $this->_loaded = true;
    }
    public function getMessage($key) {
        if (!$this->_loaded) $this->load();
        if (!isset($this->messages[$key])) return "[$key]";
        return $this->messages[$key];
    }
    public function getErrorName($int) {
        if (!$this->_loaded) $this->load();
        if (!isset($this->errorNames[$int])) return "[Error: $int]";
        return $this->errorNames[$int];
    }
    public function listify($array) {
        $sep      = $this->getMessage('Item separator');
        $sep_last = $this->getMessage('Item separator last');
        $ret = '';
        for ($i = 0, $c = count($array); $i < $c; $i++) {
            if ($i == 0) {
            } elseif ($i + 1 < $c) {
                $ret .= $sep;
            } else {
                $ret .= $sep_last;
            }
            $ret .= $array[$i];
        }
        return $ret;
    }
    public function formatMessage($key, $args = array()) {
        if (!$this->_loaded) $this->load();
        if (!isset($this->messages[$key])) return "[$key]";
        $raw = $this->messages[$key];
        $subst = array();
        $generator = false;
        foreach ($args as $i => $value) {
            if (is_object($value)) {
                if ($value instanceof HTMLPurifier_Token) {
                    if (!$generator) $generator = $this->context->get('Generator');
                    if (isset($value->name)) $subst['$'.$i.'.Name'] = $value->name;
                    if (isset($value->data)) $subst['$'.$i.'.Data'] = $value->data;
                    $subst['$'.$i.'.Compact'] = 
                    $subst['$'.$i.'.Serialized'] = $generator->generateFromToken($value);
                    if (!empty($value->attr)) {
                        $stripped_token = clone $value;
                        $stripped_token->attr = array();
                        $subst['$'.$i.'.Compact'] = $generator->generateFromToken($stripped_token);
                    }
                    $subst['$'.$i.'.Line'] = $value->line ? $value->line : 'unknown';
                }
                continue;
            } elseif (is_array($value)) {
                $keys = array_keys($value);
                if (array_keys($keys) === $keys) {
                    $subst['$'.$i] = $this->listify($value);
                } else {
                    $subst['$'.$i.'.Keys'] = $this->listify($keys);
                    $subst['$'.$i.'.Values'] = $this->listify(array_values($value));
                }
                continue;
            }
            $subst['$' . $i] = $value;
        }
        return strtr($raw, $subst);
    }
}
class HTMLPurifier_LanguageFactory
{
    public $cache;
    public $keys = array('fallback', 'messages', 'errorNames');
    protected $validator;
    protected $dir;
    protected $mergeable_keys_map = array('messages' => true, 'errorNames' => true);
    protected $mergeable_keys_list = array();
    public static function instance($prototype = null) {
        static $instance = null;
        if ($prototype !== null) {
            $instance = $prototype;
        } elseif ($instance === null || $prototype == true) {
            $instance = new HTMLPurifier_LanguageFactory();
            $instance->setup();
        }
        return $instance;
    }
    public function setup() {
        $this->validator = new HTMLPurifier_AttrDef_Lang();
        $this->dir = HTMLPURIFIER_PREFIX . '/HTMLPurifier';
    }
    public function create($config, $context, $code = false) {
        if ($code === false) {
            $code = $this->validator->validate(
              $config->get('Core', 'Language'), $config, $context
            );
        } else {
            $code = $this->validator->validate($code, $config, $context);
        }
        if ($code === false) $code = 'en';  
        $pcode = str_replace('-', '_', $code);  
        static $depth = 0;  
        if ($code == 'en') {
            $lang = new HTMLPurifier_Language($config, $context);
        } else {
            $class = 'HTMLPurifier_Language_' . $pcode;
            $file  = $this->dir . '/Language/classes/' . $code . '.php';
            if (file_exists($file) || class_exists($class, false)) {
                $lang = new $class($config, $context);
            } else {
                $raw_fallback = $this->getFallbackFor($code);
                $fallback = $raw_fallback ? $raw_fallback : 'en';
                $depth++;
                $lang = $this->create($config, $context, $fallback);
                if (!$raw_fallback) {
                    $lang->error = true;
                }
                $depth--;
            }
        }
        $lang->code = $code;
        return $lang;
    }
    public function getFallbackFor($code) {
        $this->loadLanguage($code);
        return $this->cache[$code]['fallback'];
    }
    public function loadLanguage($code) {
        static $languages_seen = array();  
        if (isset($this->cache[$code])) return;
        $filename = $this->dir . '/Language/messages/' . $code . '.php';
        $fallback = ($code != 'en') ? 'en' : false;
        if (!file_exists($filename)) {
            $filename = $this->dir . '/Language/messages/en.php';
            $cache = array();
        } else {
            include $filename;
            $cache = compact($this->keys);
        }
        if (!empty($fallback)) {
            if (isset($languages_seen[$code])) {
                trigger_error('Circular fallback reference in language ' .
                    $code, E_USER_ERROR);
                $fallback = 'en';
            }
            $language_seen[$code] = true;
            $this->loadLanguage($fallback);
            $fallback_cache = $this->cache[$fallback];
            foreach ( $this->keys as $key ) {
                if (isset($cache[$key]) && isset($fallback_cache[$key])) {
                    if (isset($this->mergeable_keys_map[$key])) {
                        $cache[$key] = $cache[$key] + $fallback_cache[$key];
                    } elseif (isset($this->mergeable_keys_list[$key])) {
                        $cache[$key] = array_merge( $fallback_cache[$key], $cache[$key] );
                    }
                } else {
                    $cache[$key] = $fallback_cache[$key];
                }
            }
        }
        $this->cache[$code] = $cache;
        return;
    }
}
class HTMLPurifier_Lexer
{
    public static function create($config) {
        if (!($config instanceof HTMLPurifier_Config)) {
            $lexer = $config;
            trigger_error("Passing a prototype to 
              HTMLPurifier_Lexer::create() is deprecated, please instead
              use %Core.LexerImpl", E_USER_WARNING);
        } else {
            $lexer = $config->get('Core', 'LexerImpl');
        }
        if (is_object($lexer)) {
            return $lexer;
        }
        if (is_null($lexer)) { do {
            $line_numbers = $config->get('Core', 'MaintainLineNumbers');
            if (
                $line_numbers === true ||
                ($line_numbers === null && $config->get('Core', 'CollectErrors'))
            ) {
                $lexer = 'DirectLex';
                break;
            }
            if (class_exists('DOMDocument')) {
                $lexer = 'DOMLex';
            } else {
                $lexer = 'DirectLex';
            }
        } while(0); }  
        switch ($lexer) {
            case 'DOMLex':
                return new HTMLPurifier_Lexer_DOMLex();
            case 'DirectLex':
                return new HTMLPurifier_Lexer_DirectLex();
            case 'PH5P':
                return new HTMLPurifier_Lexer_PH5P();
            default:
                trigger_error("Cannot instantiate unrecognized Lexer type " . htmlspecialchars($lexer), E_USER_ERROR);
        }
    }
    public function __construct() {
        $this->_entity_parser = new HTMLPurifier_EntityParser();
    }
    protected $_special_entity2str =
            array(
                    '&quot;' => '"',
                    '&amp;'  => '&',
                    '&lt;'   => '<',
                    '&gt;'   => '>',
                    '&#39;'  => "'",
                    '&#039;' => "'",
                    '&#x27;' => "'"
            );
    public function parseData($string) {
        if ($string === '') return '';
        $num_amp = substr_count($string, '&') - substr_count($string, '& ') -
            ($string[strlen($string)-1] === '&' ? 1 : 0);
        if (!$num_amp) return $string;  
        $num_esc_amp = substr_count($string, '&amp;');
        $string = strtr($string, $this->_special_entity2str);
        $num_amp_2 = substr_count($string, '&') - substr_count($string, '& ') -
            ($string[strlen($string)-1] === '&' ? 1 : 0);
        if ($num_amp_2 <= $num_esc_amp) return $string;
        $string = $this->_entity_parser->substituteSpecialEntities($string);
        return $string;
    }
    public function tokenizeHTML($string, $config, $context) {
        trigger_error('Call to abstract class', E_USER_ERROR);
    }
    protected static function escapeCDATA($string) {
        return preg_replace_callback(
            '/<!\[CDATA\[(.+?)\]\]>/s',
            array('HTMLPurifier_Lexer', 'CDATACallback'),
            $string
        );
    }
    protected static function escapeCommentedCDATA($string) {
        return preg_replace_callback(
            '#<!--//--><!\[CDATA\[//><!--(.+?)//--><!\]\]>#s',
            array('HTMLPurifier_Lexer', 'CDATACallback'),
            $string
        );
    }
    protected static function CDATACallback($matches) {
        return htmlspecialchars($matches[1], ENT_COMPAT, 'UTF-8');
    }
    public function normalize($html, $config, $context) {
        if ($config->get('Core', 'ConvertDocumentToFragment')) {
            $html = $this->extractBody($html);
        }
        $html = str_replace("\r\n", "\n", $html);
        $html = str_replace("\r", "\n", $html);
        if ($config->get('HTML', 'Trusted')) {
            $html = $this->escapeCommentedCDATA($html);
        }
        $html = $this->escapeCDATA($html);
        $html = $this->_entity_parser->substituteNonSpecialEntities($html);
        $html = HTMLPurifier_Encoder::cleanUTF8($html);
        return $html;
    }
    public function extractBody($html) {
        $matches = array();
        $result = preg_match('!<body[^>]*>(.+?)</body>!is', $html, $matches);
        if ($result) {
            return $matches[1];
        } else {
            return $html;
        }
    }
}
class HTMLPurifier_PercentEncoder
{
    protected $preserve = array();
    public function __construct($preserve = false) {
        for ($i = 48; $i <= 57;  $i++) $this->preserve[$i] = true;  
        for ($i = 65; $i <= 90;  $i++) $this->preserve[$i] = true;  
        for ($i = 97; $i <= 122; $i++) $this->preserve[$i] = true;  
        $this->preserve[45] = true;  
        $this->preserve[46] = true;  
        $this->preserve[95] = true;  
        $this->preserve[126]= true;  
        if ($preserve !== false) {
            for ($i = 0, $c = strlen($preserve); $i < $c; $i++) {
                $this->preserve[ord($preserve[$i])] = true;
            }
        }
    }
    public function encode($string) {
        $ret = '';
        for ($i = 0, $c = strlen($string); $i < $c; $i++) {
            if ($string[$i] !== '%' && !isset($this->preserve[$int = ord($string[$i])]) ) {
                $ret .= '%' . sprintf('%02X', $int);
            } else {
                $ret .= $string[$i];
            }
        }
        return $ret;
    }
    public function normalize($string) {
        if ($string == '') return '';
        $parts = explode('%', $string);
        $ret = array_shift($parts);
        foreach ($parts as $part) {
            $length = strlen($part);
            if ($length < 2) {
                $ret .= '%25' . $part;
                continue;
            }
            $encoding = substr($part, 0, 2);
            $text     = substr($part, 2);
            if (!ctype_xdigit($encoding)) {
                $ret .= '%25' . $part;
                continue;
            }
            $int = hexdec($encoding);
            if (isset($this->preserve[$int])) {
                $ret .= chr($int) . $text;
                continue;
            }
            $encoding = strtoupper($encoding);
            $ret .= '%' . $encoding . $text;
        }
        return $ret;
    }
}
abstract class HTMLPurifier_Strategy
{
    abstract public function execute($tokens, $config, $context);
}
class HTMLPurifier_StringHash extends ArrayObject
{
    protected $accessed = array();
    public function offsetGet($index) {
        $this->accessed[$index] = true;
        return parent::offsetGet($index);
    }
    public function getAccessed() {
        return $this->accessed;
    }
    public function resetAccessed() {
        $this->accessed = array();
    }
}
class HTMLPurifier_StringHashParser
{
    public $default = 'ID';
    public function parseFile($file) {
        if (!file_exists($file)) return false;
        $fh = fopen($file, 'r');
        if (!$fh) return false;
        $ret = $this->parseHandle($fh);
        fclose($fh);
        return $ret;
    }
    public function parseMultiFile($file) {
        if (!file_exists($file)) return false;
        $ret = array();
        $fh = fopen($file, 'r');
        if (!$fh) return false;
        while (!feof($fh)) {
            $ret[] = $this->parseHandle($fh);
        }
        fclose($fh);
        return $ret;
    }
    protected function parseHandle($fh) {
        $state   = false;
        $single  = false;
        $ret     = array();
        do {
            $line = fgets($fh);
            if ($line === false) break;
            $line = rtrim($line, "\n\r");
            if (!$state && $line === '') continue;
            if ($line === '----') break;
            if (strncmp('--', $line, 2) === 0) {
                $state = trim($line, '- ');
                continue;
            } elseif (!$state) {
                $single = true;
                if (strpos($line, ':') !== false) {
                    list($state, $line) = explode(': ', $line, 2);
                } else {
                    $state  = $this->default;
                }
            }
            if ($single) {
                $ret[$state] = $line;
                $single = false;
                $state  = false;
            } else {
                if (!isset($ret[$state])) $ret[$state] = '';
                $ret[$state] .= "$line\n";
            }
        } while (!feof($fh));
        return $ret;
    }
}
abstract class HTMLPurifier_TagTransform
{
    public $transform_to;
    abstract public function transform($tag, $config, $context);
    protected function prependCSS(&$attr, $css) {
        $attr['style'] = isset($attr['style']) ? $attr['style'] : '';
        $attr['style'] = $css . $attr['style'];
    }
}
class HTMLPurifier_Token {
    public $type; /**< Type of node to bypass <tt>is_a()</tt>. */
    public $line; /**< Line number node was on in source document. Null if unknown. */
    public $armor = array();
    public function __get($n) {
      if ($n === 'type') {
        trigger_error('Deprecated type property called; use instanceof', E_USER_NOTICE);
        switch (get_class($this)) {
          case 'HTMLPurifier_Token_Start': return 'start';
          case 'HTMLPurifier_Token_Empty': return 'empty';
          case 'HTMLPurifier_Token_End': return 'end';
          case 'HTMLPurifier_Token_Text': return 'text';
          case 'HTMLPurifier_Token_Comment': return 'comment';
          default: return null;
        }
      }
    }
}
class HTMLPurifier_TokenFactory
{
    private $p_start, $p_end, $p_empty, $p_text, $p_comment;
    public function __construct() {
        $this->p_start  = new HTMLPurifier_Token_Start('', array());
        $this->p_end    = new HTMLPurifier_Token_End('');
        $this->p_empty  = new HTMLPurifier_Token_Empty('', array());
        $this->p_text   = new HTMLPurifier_Token_Text('');
        $this->p_comment= new HTMLPurifier_Token_Comment('');
    }
    public function createStart($name, $attr = array()) {
        $p = clone $this->p_start;
        $p->__construct($name, $attr);
        return $p;
    }
    public function createEnd($name) {
        $p = clone $this->p_end;
        $p->__construct($name);
        return $p;
    }
    public function createEmpty($name, $attr = array()) {
        $p = clone $this->p_empty;
        $p->__construct($name, $attr);
        return $p;
    }
    public function createText($data) {
        $p = clone $this->p_text;
        $p->__construct($data);
        return $p;
    }
    public function createComment($data) {
        $p = clone $this->p_comment;
        $p->__construct($data);
        return $p;
    }
}
class HTMLPurifier_URI
{
    public $scheme, $userinfo, $host, $port, $path, $query, $fragment;
    public function __construct($scheme, $userinfo, $host, $port, $path, $query, $fragment) {
        $this->scheme = is_null($scheme) || ctype_lower($scheme) ? $scheme : strtolower($scheme);
        $this->userinfo = $userinfo;
        $this->host = $host;
        $this->port = is_null($port) ? $port : (int) $port;
        $this->path = $path;
        $this->query = $query;
        $this->fragment = $fragment;
    }
    public function getSchemeObj($config, $context) {
        $registry = HTMLPurifier_URISchemeRegistry::instance();
        if ($this->scheme !== null) {
            $scheme_obj = $registry->getScheme($this->scheme, $config, $context);
            if (!$scheme_obj) return false;  
        } else {
            $def = $config->getDefinition('URI');
            $scheme_obj = $registry->getScheme($def->defaultScheme, $config, $context);
            if (!$scheme_obj) {
                trigger_error(
                    'Default scheme object "' . $def->defaultScheme . '" was not readable',
                    E_USER_WARNING
                );
                return false;
            }
        }
        return $scheme_obj;
    }
    public function validate($config, $context) {
        $chars_sub_delims = '!$&\'()*+,;=';
        $chars_gen_delims = ':/?#[]@';
        $chars_pchar = $chars_sub_delims . ':@';
        if (!is_null($this->scheme) && is_null($this->host)) {
            $def = $config->getDefinition('URI');
            if ($def->defaultScheme === $this->scheme) {
                $this->scheme = null;
            }
        }
        if (!is_null($this->host)) {
            $host_def = new HTMLPurifier_AttrDef_URI_Host();
            $this->host = $host_def->validate($this->host, $config, $context);
            if ($this->host === false) $this->host = null;
        }
        if (!is_null($this->userinfo)) {
            $encoder = new HTMLPurifier_PercentEncoder($chars_sub_delims . ':');
            $this->userinfo = $encoder->encode($this->userinfo);
        }
        if (!is_null($this->port)) {
            if ($this->port < 1 || $this->port > 65535) $this->port = null;
        }
        $path_parts = array();
        $segments_encoder = new HTMLPurifier_PercentEncoder($chars_pchar . '/');
        if (!is_null($this->host)) {
            $this->path = $segments_encoder->encode($this->path);
        } elseif ($this->path !== '' && $this->path[0] === '/') {
            if (strlen($this->path) >= 2 && $this->path[1] === '/') {
                $this->path = '';
            } else {
                $this->path = $segments_encoder->encode($this->path);
            }
        } elseif (!is_null($this->scheme) && $this->path !== '') {
            $this->path = $segments_encoder->encode($this->path);
        } elseif (is_null($this->scheme) && $this->path !== '') {
            $segment_nc_encoder = new HTMLPurifier_PercentEncoder($chars_sub_delims . '@');
            $c = strpos($this->path, '/');
            if ($c !== false) {
                $this->path = 
                    $segment_nc_encoder->encode(substr($this->path, 0, $c)) .
                    $segments_encoder->encode(substr($this->path, $c));
            } else {
                $this->path = $segment_nc_encoder->encode($this->path);
            }
        } else {
            $this->path = '';  
        }
        return true;
    }
    public function toString() {
        $authority = null;
        if (!is_null($this->host)) {
            $authority = '';
            if(!is_null($this->userinfo)) $authority .= $this->userinfo . '@';
            $authority .= $this->host;
            if(!is_null($this->port))     $authority .= ':' . $this->port;
        }
        $result = '';
        if (!is_null($this->scheme))    $result .= $this->scheme . ':';
        if (!is_null($authority))       $result .=  '//' . $authority;
        $result .= $this->path;
        if (!is_null($this->query))     $result .= '?' . $this->query;
        if (!is_null($this->fragment))  $result .= '#' . $this->fragment;
        return $result;
    }
}
class HTMLPurifier_URIDefinition extends HTMLPurifier_Definition
{
    public $type = 'URI';
    protected $filters = array();
    protected $registeredFilters = array();
    public $base;
    public $host;
    public $defaultScheme;
    public function __construct() {
        $this->registerFilter(new HTMLPurifier_URIFilter_DisableExternal());
        $this->registerFilter(new HTMLPurifier_URIFilter_DisableExternalResources());
        $this->registerFilter(new HTMLPurifier_URIFilter_HostBlacklist());
        $this->registerFilter(new HTMLPurifier_URIFilter_MakeAbsolute());
    }
    public function registerFilter($filter) {
        $this->registeredFilters[$filter->name] = $filter;
    }
    public function addFilter($filter, $config) {
        $filter->prepare($config);
        $this->filters[$filter->name] = $filter;
    }
    protected function doSetup($config) {
        $this->setupMemberVariables($config);
        $this->setupFilters($config);
    }
    protected function setupFilters($config) {
        foreach ($this->registeredFilters as $name => $filter) {
            $conf = $config->get('URI', $name);
            if ($conf !== false && $conf !== null) {
                $this->addFilter($filter, $config);
            }
        }
        unset($this->registeredFilters);
    }
    protected function setupMemberVariables($config) {
        $this->host = $config->get('URI', 'Host');
        $base_uri = $config->get('URI', 'Base');
        if (!is_null($base_uri)) {
            $parser = new HTMLPurifier_URIParser();
            $this->base = $parser->parse($base_uri);
            $this->defaultScheme = $this->base->scheme;
            if (is_null($this->host)) $this->host = $this->base->host;
        }
        if (is_null($this->defaultScheme)) $this->defaultScheme = $config->get('URI', 'DefaultScheme');
    }
    public function filter(&$uri, $config, $context) {
        foreach ($this->filters as $name => $x) {
            $result = $this->filters[$name]->filter($uri, $config, $context);
            if (!$result) return false;
        }
        return true;
    }
}
abstract class HTMLPurifier_URIFilter
{
    public $name;
    public function prepare($config) {}
    abstract public function filter(&$uri, $config, $context);
}
class HTMLPurifier_URIParser
{
    protected $percentEncoder;
    public function __construct() {
        $this->percentEncoder = new HTMLPurifier_PercentEncoder();
    }
    public function parse($uri) {
        $uri = $this->percentEncoder->normalize($uri);
        $r_URI = '!'.
            '(([^:/?#"<>]+):)?'.  
            '(//([^/?#"<>]*))?'.  
            '([^?#"<>]*)'.        
            '(\?([^#"<>]*))?'.    
            '(#([^"<>]*))?'.      
            '!';
        $matches = array();
        $result = preg_match($r_URI, $uri, $matches);
        if (!$result) return false;  
        $scheme     = !empty($matches[1]) ? $matches[2] : null;
        $authority  = !empty($matches[3]) ? $matches[4] : null;
        $path       = $matches[5];  
        $query      = !empty($matches[6]) ? $matches[7] : null;
        $fragment   = !empty($matches[8]) ? $matches[9] : null;
        if ($authority !== null) {
            $r_authority = "/^((.+?)@)?(\[[^\]]+\]|[^:]*)(:(\d*))?/";
            $matches = array();
            preg_match($r_authority, $authority, $matches);
            $userinfo   = !empty($matches[1]) ? $matches[2] : null;
            $host       = !empty($matches[3]) ? $matches[3] : '';
            $port       = !empty($matches[4]) ? (int) $matches[5] : null;
        } else {
            $port = $host = $userinfo = null;
        }
        return new HTMLPurifier_URI(
            $scheme, $userinfo, $host, $port, $path, $query, $fragment);
    }
}
class HTMLPurifier_URIScheme
{
    public $default_port = null;
    public $browsable = false;
    public $hierarchical = false;
    public function validate(&$uri, $config, $context) {
        if ($this->default_port == $uri->port) $uri->port = null;
        return true;
    }
}
class HTMLPurifier_URISchemeRegistry
{
    public static function instance($prototype = null) {
        static $instance = null;
        if ($prototype !== null) {
            $instance = $prototype;
        } elseif ($instance === null || $prototype == true) {
            $instance = new HTMLPurifier_URISchemeRegistry();
        }
        return $instance;
    }
    protected $schemes = array();
    public function getScheme($scheme, $config, $context) {
        if (!$config) $config = HTMLPurifier_Config::createDefault();
        $null = null;  
        $allowed_schemes = $config->get('URI', 'AllowedSchemes');
        if (!$config->get('URI', 'OverrideAllowedSchemes') &&
            !isset($allowed_schemes[$scheme])
        ) {
            return $null;
        }
        if (isset($this->schemes[$scheme])) return $this->schemes[$scheme];
        if (!isset($allowed_schemes[$scheme])) return $null;
        $class = 'HTMLPurifier_URIScheme_' . $scheme;
        if (!class_exists($class)) return $null;
        $this->schemes[$scheme] = new $class();
        return $this->schemes[$scheme];
    }
    public function register($scheme, $scheme_obj) {
        $this->schemes[$scheme] = $scheme_obj;
    }
}
class HTMLPurifier_VarParser
{
    static public $types = array(
        'string'    => true,
        'istring'   => true,
        'text'      => true,
        'itext'     => true,
        'int'       => true,
        'float'     => true,
        'bool'      => true,
        'lookup'    => true,
        'list'      => true,
        'hash'      => true,
        'mixed'     => true
    );
    static public $stringTypes = array(
        'string'    => true,
        'istring'   => true,
        'text'      => true,
        'itext'     => true,
    );
    final public function parse($var, $type, $allow_null = false) {
        if (!isset(HTMLPurifier_VarParser::$types[$type])) {
            throw new HTMLPurifier_VarParserException("Invalid type '$type'");
        }
        $var = $this->parseImplementation($var, $type, $allow_null);
        if ($allow_null && $var === null) return null;
        switch ($type) {
            case 'string':
            case 'istring':
            case 'text':
            case 'itext':
                if (!is_string($var)) break;
                if ($type[0] == 'i') $var = strtolower($var);
                return $var;
            case 'int':
                if (!is_int($var)) break;
                return $var;
            case 'float':
                if (!is_float($var)) break;
                return $var;
            case 'bool':
                if (!is_bool($var)) break;
                return $var;
            case 'lookup':
            case 'list':
            case 'hash':
                if (!is_array($var)) break;
                if ($type === 'lookup') {
                    foreach ($var as $k) if ($k !== true) $this->error('Lookup table contains value other than true');
                } elseif ($type === 'list') {
                    $keys = array_keys($var);
                    if (array_keys($keys) !== $keys) $this->error('Indices for list are not uniform');
                }
                return $var;
            case 'mixed':
                return $var;
            default:
                $this->errorInconsistent(get_class($this), $type);
        }
        $this->errorGeneric($var, $type);
    }
    protected function parseImplementation($var, $type, $allow_null) {
        return $var;
    }
    protected function error($msg) {
        throw new HTMLPurifier_VarParserException($msg);
    }
    protected function errorInconsistent($class, $type) {
        throw new HTMLPurifier_Exception("Inconsistency in $class: $type not implemented");
    }
    protected function errorGeneric($var, $type) {
        $vtype = gettype($var);
        $this->error("Expected type $type, got $vtype");
    }
}
class HTMLPurifier_VarParserException extends HTMLPurifier_Exception
{
}
class HTMLPurifier_AttrDef_CSS extends HTMLPurifier_AttrDef
{
    public function validate($css, $config, $context) {
        $css = $this->parseCDATA($css);
        $definition = $config->getCSSDefinition();
        $declarations = explode(';', $css);
        $propvalues = array();
        foreach ($declarations as $declaration) {
            if (!$declaration) continue;
            if (!strpos($declaration, ':')) continue;
            list($property, $value) = explode(':', $declaration, 2);
            $property = trim($property);
            $value    = trim($value);
            $ok = false;
            do {
                if (isset($definition->info[$property])) {
                    $ok = true;
                    break;
                }
                if (ctype_lower($property)) break;
                $property = strtolower($property);
                if (isset($definition->info[$property])) {
                    $ok = true;
                    break;
                }
            } while(0);
            if (!$ok) continue;
            if (strtolower(trim($value)) !== 'inherit') {
                $result = $definition->info[$property]->validate(
                    $value, $config, $context );
            } else {
                $result = 'inherit';
            }
            if ($result === false) continue;
            $propvalues[$property] = $result;
        }
        $new_declarations = '';
        foreach ($propvalues as $prop => $value) {
            $new_declarations .= "$prop:$value;";
        }
        return $new_declarations ? $new_declarations : false;
    }
}
class HTMLPurifier_AttrDef_Enum extends HTMLPurifier_AttrDef
{
    public $valid_values   = array();
    protected $case_sensitive = false;  
    public function __construct(
        $valid_values = array(), $case_sensitive = false
    ) {
        $this->valid_values = array_flip($valid_values);
        $this->case_sensitive = $case_sensitive;
    }
    public function validate($string, $config, $context) {
        $string = trim($string);
        if (!$this->case_sensitive) {
            $string = ctype_lower($string) ? $string : strtolower($string);
        }
        $result = isset($this->valid_values[$string]);
        return $result ? $string : false;
    }
    public function make($string) {
        if (strlen($string) > 2 && $string[0] == 's' && $string[1] == ':') {
            $string = substr($string, 2);
            $sensitive = true;
        } else {
            $sensitive = false;
        }
        $values = explode(',', $string);
        return new HTMLPurifier_AttrDef_Enum($values, $sensitive);
    }
}
class HTMLPurifier_AttrDef_Integer extends HTMLPurifier_AttrDef
{
    protected $negative = true;
    protected $zero = true;
    protected $positive = true;
    public function __construct(
        $negative = true, $zero = true, $positive = true
    ) {
        $this->negative = $negative;
        $this->zero     = $zero;
        $this->positive = $positive;
    }
    public function validate($integer, $config, $context) {
        $integer = $this->parseCDATA($integer);
        if ($integer === '') return false;
        if ( $this->negative && $integer[0] === '-' ) {
            $digits = substr($integer, 1);
            if ($digits === '0') $integer = '0';  
        } elseif( $this->positive && $integer[0] === '+' ) {
            $digits = $integer = substr($integer, 1);  
        } else {
            $digits = $integer;
        }
        if (!ctype_digit($digits)) return false;
        if (!$this->zero     && $integer == 0) return false;
        if (!$this->positive && $integer > 0) return false;
        if (!$this->negative && $integer < 0) return false;
        return $integer;
    }
}
class HTMLPurifier_AttrDef_Lang extends HTMLPurifier_AttrDef
{
    public function validate($string, $config, $context) {
        $string = trim($string);
        if (!$string) return false;
        $subtags = explode('-', $string);
        $num_subtags = count($subtags);
        if ($num_subtags == 0) return false;  
        $length = strlen($subtags[0]);
        switch ($length) {
            case 0:
                return false;
            case 1:
                if (! ($subtags[0] == 'x' || $subtags[0] == 'i') ) {
                    return false;
                }
                break;
            case 2:
            case 3:
                if (! ctype_alpha($subtags[0]) ) {
                    return false;
                } elseif (! ctype_lower($subtags[0]) ) {
                    $subtags[0] = strtolower($subtags[0]);
                }
                break;
            default:
                return false;
        }
        $new_string = $subtags[0];
        if ($num_subtags == 1) return $new_string;
        $length = strlen($subtags[1]);
        if ($length == 0 || ($length == 1 && $subtags[1] != 'x') || $length > 8 || !ctype_alnum($subtags[1])) {
            return $new_string;
        }
        if (!ctype_lower($subtags[1])) $subtags[1] = strtolower($subtags[1]);
        $new_string .= '-' . $subtags[1];
        if ($num_subtags == 2) return $new_string;
        for ($i = 2; $i < $num_subtags; $i++) {
            $length = strlen($subtags[$i]);
            if ($length == 0 || $length > 8 || !ctype_alnum($subtags[$i])) {
                return $new_string;
            }
            if (!ctype_lower($subtags[$i])) {
                $subtags[$i] = strtolower($subtags[$i]);
            }
            $new_string .= '-' . $subtags[$i];
        }
        return $new_string;
    }
}
class HTMLPurifier_AttrDef_Text extends HTMLPurifier_AttrDef
{
    public function validate($string, $config, $context) {
        return $this->parseCDATA($string);
    }
}
class HTMLPurifier_AttrDef_URI extends HTMLPurifier_AttrDef
{
    protected $parser;
    protected $embedsResource;
    public function __construct($embeds_resource = false) {
        $this->parser = new HTMLPurifier_URIParser();
        $this->embedsResource = (bool) $embeds_resource;
    }
    public function validate($uri, $config, $context) {
        if ($config->get('URI', 'Disable')) return false;
        $uri = $this->parseCDATA($uri);
        $uri = $this->parser->parse($uri);
        if ($uri === false) return false;
        $context->register('EmbeddedURI', $this->embedsResource); 
        $ok = false;
        do {
            $result = $uri->validate($config, $context);
            if (!$result) break;
            $uri_def = $config->getDefinition('URI');
            $result = $uri_def->filter($uri, $config, $context);
            if (!$result) break;
            $scheme_obj = $uri->getSchemeObj($config, $context);
            if (!$scheme_obj) break;
            if ($this->embedsResource && !$scheme_obj->browsable) break;
            $result = $scheme_obj->validate($uri, $config, $context);
            if (!$result) break;
            $ok = true;
        } while (false);
        $context->destroy('EmbeddedURI');
        if (!$ok) return false;
        $result = $uri->toString();
        if (
            !is_null($uri->host) &&  
            !empty($scheme_obj->browsable) &&
            !is_null($munge = $config->get('URI', 'Munge'))
        ) {
            $result = str_replace('%s', rawurlencode($result), $munge);
        }
        return $result;
    }
}
class HTMLPurifier_AttrDef_CSS_Number extends HTMLPurifier_AttrDef
{
    protected $non_negative = false;
    public function __construct($non_negative = false) {
        $this->non_negative = $non_negative;
    }
    public function validate($number, $config, $context) {
        $number = $this->parseCDATA($number);
        if ($number === '') return false;
        if ($number === '0') return '0';
        $sign = '';
        switch ($number[0]) {
            case '-':
                if ($this->non_negative) return false;
                $sign = '-';
            case '+':
                $number = substr($number, 1);
        }
        if (ctype_digit($number)) {
            $number = ltrim($number, '0');
            return $number ? $sign . $number : '0';
        }
        if (strpos($number, '.') === false) return false;
        list($left, $right) = explode('.', $number, 2);
        if ($left === '' && $right === '') return false;
        if ($left !== '' && !ctype_digit($left)) return false;
        $left  = ltrim($left,  '0');
        $right = rtrim($right, '0');
        if ($right === '') {
            return $left ? $sign . $left : '0';
        } elseif (!ctype_digit($right)) {
            return false;
        }
        return $sign . $left . '.' . $right;
    }
}
class HTMLPurifier_AttrDef_CSS_AlphaValue extends HTMLPurifier_AttrDef_CSS_Number
{
    public function __construct() {
        parent::__construct(false);  
    }
    public function validate($number, $config, $context) {
        $result = parent::validate($number, $config, $context);
        if ($result === false) return $result;
        $float = (float) $result;
        if ($float < 0.0) $result = '0';
        if ($float > 1.0) $result = '1';
        return $result;
    }
}
class HTMLPurifier_AttrDef_CSS_Background extends HTMLPurifier_AttrDef
{
    protected $info;
    public function __construct($config) {
        $def = $config->getCSSDefinition();
        $this->info['background-color'] = $def->info['background-color'];
        $this->info['background-image'] = $def->info['background-image'];
        $this->info['background-repeat'] = $def->info['background-repeat'];
        $this->info['background-attachment'] = $def->info['background-attachment'];
        $this->info['background-position'] = $def->info['background-position'];
    }
    public function validate($string, $config, $context) {
        $string = $this->parseCDATA($string);
        if ($string === '') return false;
        $string = $this->mungeRgb($string);
        $bits = explode(' ', strtolower($string));  
        $caught = array();
        $caught['color']    = false;
        $caught['image']    = false;
        $caught['repeat']   = false;
        $caught['attachment'] = false;
        $caught['position'] = false;
        $i = 0;  
        $none = false;
        foreach ($bits as $bit) {
            if ($bit === '') continue;
            foreach ($caught as $key => $status) {
                if ($key != 'position') {
                    if ($status !== false) continue;
                    $r = $this->info['background-' . $key]->validate($bit, $config, $context);
                } else {
                    $r = $bit;
                }
                if ($r === false) continue;
                if ($key == 'position') {
                    if ($caught[$key] === false) $caught[$key] = '';
                    $caught[$key] .= $r . ' ';
                } else {
                    $caught[$key] = $r;
                }
                $i++;
                break;
            }
        }
        if (!$i) return false;
        if ($caught['position'] !== false) {
            $caught['position'] = $this->info['background-position']->
                validate($caught['position'], $config, $context);
        }
        $ret = array();
        foreach ($caught as $value) {
            if ($value === false) continue;
            $ret[] = $value;
        }
        if (empty($ret)) return false;
        return implode(' ', $ret);
    }
}
class HTMLPurifier_AttrDef_CSS_BackgroundPosition extends HTMLPurifier_AttrDef
{
    protected $length;
    protected $percentage;
    public function __construct() {
        $this->length     = new HTMLPurifier_AttrDef_CSS_Length();
        $this->percentage = new HTMLPurifier_AttrDef_CSS_Percentage();
    }
    public function validate($string, $config, $context) {
        $string = $this->parseCDATA($string);
        $bits = explode(' ', $string);
        $keywords = array();
        $keywords['h'] = false;  
        $keywords['v'] = false;  
        $keywords['c'] = false;  
        $measures = array();
        $i = 0;
        $lookup = array(
            'top' => 'v',
            'bottom' => 'v',
            'left' => 'h',
            'right' => 'h',
            'center' => 'c'
        );
        foreach ($bits as $bit) {
            if ($bit === '') continue;
            $lbit = ctype_lower($bit) ? $bit : strtolower($bit);
            if (isset($lookup[$lbit])) {
                $status = $lookup[$lbit];
                $keywords[$status] = $lbit;
                $i++;
            }
            $r = $this->length->validate($bit, $config, $context);
            if ($r !== false) {
                $measures[] = $r;
                $i++;
            }
            $r = $this->percentage->validate($bit, $config, $context);
            if ($r !== false) {
                $measures[] = $r;
                $i++;
            }
        }
        if (!$i) return false;  
        $ret = array();
        if     ($keywords['h'])     $ret[] = $keywords['h'];
        elseif (count($measures))   $ret[] = array_shift($measures);
        elseif ($keywords['c']) {
            $ret[] = $keywords['c'];
            $keywords['c'] = false;  
        }
        if     ($keywords['v'])     $ret[] = $keywords['v'];
        elseif (count($measures))   $ret[] = array_shift($measures);
        elseif ($keywords['c'])     $ret[] = $keywords['c'];
        if (empty($ret)) return false;
        return implode(' ', $ret);
    }
}
class HTMLPurifier_AttrDef_CSS_Border extends HTMLPurifier_AttrDef
{
    protected $info = array();
    public function __construct($config) {
        $def = $config->getCSSDefinition();
        $this->info['border-width'] = $def->info['border-width'];
        $this->info['border-style'] = $def->info['border-style'];
        $this->info['border-top-color'] = $def->info['border-top-color'];
    }
    public function validate($string, $config, $context) {
        $string = $this->parseCDATA($string);
        $string = $this->mungeRgb($string);
        $bits = explode(' ', $string);
        $done = array();  
        $ret = '';  
        foreach ($bits as $bit) {
            foreach ($this->info as $propname => $validator) {
                if (isset($done[$propname])) continue;
                $r = $validator->validate($bit, $config, $context);
                if ($r !== false) {
                    $ret .= $r . ' ';
                    $done[$propname] = true;
                    break;
                }
            }
        }
        return rtrim($ret);
    }
}
class HTMLPurifier_AttrDef_CSS_Color extends HTMLPurifier_AttrDef
{
    public function validate($color, $config, $context) {
        static $colors = null;
        if ($colors === null) $colors = $config->get('Core', 'ColorKeywords');
        $color = trim($color);
        if ($color === '') return false;
        $lower = strtolower($color);
        if (isset($colors[$lower])) return $colors[$lower];
        if (strpos($color, 'rgb(') !== false) {
            $length = strlen($color);
            if (strpos($color, ')') !== $length - 1) return false;
            $triad = substr($color, 4, $length - 4 - 1);
            $parts = explode(',', $triad);
            if (count($parts) !== 3) return false;
            $type = false;  
            $new_parts = array();
            foreach ($parts as $part) {
                $part = trim($part);
                if ($part === '') return false;
                $length = strlen($part);
                if ($part[$length - 1] === '%') {
                    if (!$type) {
                        $type = 'percentage';
                    } elseif ($type !== 'percentage') {
                        return false;
                    }
                    $num = (float) substr($part, 0, $length - 1);
                    if ($num < 0) $num = 0;
                    if ($num > 100) $num = 100;
                    $new_parts[] = "$num%";
                } else {
                    if (!$type) {
                        $type = 'integer';
                    } elseif ($type !== 'integer') {
                        return false;
                    }
                    $num = (int) $part;
                    if ($num < 0) $num = 0;
                    if ($num > 255) $num = 255;
                    $new_parts[] = (string) $num;
                }
            }
            $new_triad = implode(',', $new_parts);
            $color = "rgb($new_triad)";
        } else {
            if ($color[0] === '#') {
                $hex = substr($color, 1);
            } else {
                $hex = $color;
                $color = '#' . $color;
            }
            $length = strlen($hex);
            if ($length !== 3 && $length !== 6) return false;
            if (!ctype_xdigit($hex)) return false;
        }
        return $color;
    }
}
class HTMLPurifier_AttrDef_CSS_Composite extends HTMLPurifier_AttrDef
{
    public $defs;
    public function __construct($defs) {
        $this->defs = $defs;
    }
    public function validate($string, $config, $context) {
        foreach ($this->defs as $i => $def) {
            $result = $this->defs[$i]->validate($string, $config, $context);
            if ($result !== false) return $result;
        }
        return false;
    }
}
class HTMLPurifier_AttrDef_CSS_DenyElementDecorator extends HTMLPurifier_AttrDef
{
    protected $def, $element;
    public function __construct($def, $element) {
        $this->def = $def;
        $this->element = $element;
    }
    public function validate($string, $config, $context) {
        $token = $context->get('CurrentToken', true);
        if ($token && $token->name == $this->element) return false;
        return $this->def->validate($string, $config, $context);
    }
}
class HTMLPurifier_AttrDef_CSS_Filter extends HTMLPurifier_AttrDef
{
    protected $intValidator;
    public function __construct() {
        $this->intValidator = new HTMLPurifier_AttrDef_Integer();
    }
    public function validate($value, $config, $context) {
        $value = $this->parseCDATA($value);
        if ($value === 'none') return $value;
        $function_length = strcspn($value, '(');
        $function = trim(substr($value, 0, $function_length));
        if ($function !== 'alpha' &&
            $function !== 'Alpha' &&
            $function !== 'progid:DXImageTransform.Microsoft.Alpha'
            ) return false;
        $cursor = $function_length + 1;
        $parameters_length = strcspn($value, ')', $cursor);
        $parameters = substr($value, $cursor, $parameters_length);
        $params = explode(',', $parameters);
        $ret_params = array();
        $lookup = array();
        foreach ($params as $param) {
            list($key, $value) = explode('=', $param);
            $key   = trim($key);
            $value = trim($value);
            if (isset($lookup[$key])) continue;
            if ($key !== 'opacity') continue;
            $value = $this->intValidator->validate($value, $config, $context);
            if ($value === false) continue;
            $int = (int) $value;
            if ($int > 100) $value = '100';
            if ($int < 0) $value = '0';
            $ret_params[] = "$key=$value";
            $lookup[$key] = true;
        }
        $ret_parameters = implode(',', $ret_params);
        $ret_function = "$function($ret_parameters)";
        return $ret_function;
    }
}
class HTMLPurifier_AttrDef_CSS_Font extends HTMLPurifier_AttrDef
{
    protected $info = array();
    public function __construct($config) {
        $def = $config->getCSSDefinition();
        $this->info['font-style']   = $def->info['font-style'];
        $this->info['font-variant'] = $def->info['font-variant'];
        $this->info['font-weight']  = $def->info['font-weight'];
        $this->info['font-size']    = $def->info['font-size'];
        $this->info['line-height']  = $def->info['line-height'];
        $this->info['font-family']  = $def->info['font-family'];
    }
    public function validate($string, $config, $context) {
        static $system_fonts = array(
            'caption' => true,
            'icon' => true,
            'menu' => true,
            'message-box' => true,
            'small-caption' => true,
            'status-bar' => true
        );
        $string = $this->parseCDATA($string);
        if ($string === '') return false;
        $lowercase_string = strtolower($string);
        if (isset($system_fonts[$lowercase_string])) {
            return $lowercase_string;
        }
        $bits = explode(' ', $string);  
        $stage = 0;  
        $caught = array();  
        $stage_1 = array('font-style', 'font-variant', 'font-weight');
        $final = '';  
        for ($i = 0, $size = count($bits); $i < $size; $i++) {
            if ($bits[$i] === '') continue;
            switch ($stage) {
                case 0:
                    foreach ($stage_1 as $validator_name) {
                        if (isset($caught[$validator_name])) continue;
                        $r = $this->info[$validator_name]->validate(
                                                $bits[$i], $config, $context);
                        if ($r !== false) {
                            $final .= $r . ' ';
                            $caught[$validator_name] = true;
                            break;
                        }
                    }
                    if (count($caught) >= 3) $stage = 1;
                    if ($r !== false) break;
                case 1:
                    $found_slash = false;
                    if (strpos($bits[$i], '/') !== false) {
                        list($font_size, $line_height) =
                                                    explode('/', $bits[$i]);
                        if ($line_height === '') {
                            $line_height = false;
                            $found_slash = true;
                        }
                    } else {
                        $font_size = $bits[$i];
                        $line_height = false;
                    }
                    $r = $this->info['font-size']->validate(
                                              $font_size, $config, $context);
                    if ($r !== false) {
                        $final .= $r;
                        if ($line_height === false) {
                            for ($j = $i + 1; $j < $size; $j++) {
                                if ($bits[$j] === '') continue;
                                if ($bits[$j] === '/') {
                                    if ($found_slash) {
                                        return false;
                                    } else {
                                        $found_slash = true;
                                        continue;
                                    }
                                }
                                $line_height = $bits[$j];
                                break;
                            }
                        } else {
                            $found_slash = true;
                            $j = $i;
                        }
                        if ($found_slash) {
                            $i = $j;
                            $r = $this->info['line-height']->validate(
                                              $line_height, $config, $context);
                            if ($r !== false) {
                                $final .= '/' . $r;
                            }
                        }
                        $final .= ' ';
                        $stage = 2;
                        break;
                    }
                    return false;
                case 2:
                    $font_family =
                        implode(' ', array_slice($bits, $i, $size - $i));
                    $r = $this->info['font-family']->validate(
                                              $font_family, $config, $context);
                    if ($r !== false) {
                        $final .= $r . ' ';
                        return rtrim($final);
                    }
                    return false;
            }
        }
        return false;
    }
}
class HTMLPurifier_AttrDef_CSS_FontFamily extends HTMLPurifier_AttrDef
{
    public function validate($string, $config, $context) {
        static $generic_names = array(
            'serif' => true,
            'sans-serif' => true,
            'monospace' => true,
            'fantasy' => true,
            'cursive' => true
        );
        $string = $this->parseCDATA($string);
        $fonts = explode(',', $string);
        $final = '';
        foreach($fonts as $font) {
            $font = trim($font);
            if ($font === '') continue;
            if (isset($generic_names[$font])) {
                $final .= $font . ', ';
                continue;
            }
            if ($font[0] === '"' || $font[0] === "'") {
                $length = strlen($font);
                if ($length <= 2) continue;
                $quote = $font[0];
                if ($font[$length - 1] !== $quote) continue;
                $font = substr($font, 1, $length - 2);
                $font = str_replace("\\$quote", $quote, $font);  
                $font = str_replace("\\\n", "\n", $font);        
            }
            if (ctype_alnum($font)) {
                $final .= $font . ', ';
                continue;
            }
            $font = str_replace("'", "\\'", $font);
            $font = str_replace("\n", "\\\n", $font);
            $final .= "'$font', ";
        }
        $final = rtrim($final, ', ');
        if ($final === '') return false;
        return $final;
    }
}
class HTMLPurifier_AttrDef_CSS_ImportantDecorator extends HTMLPurifier_AttrDef
{
    protected $def, $allow;
    public function __construct($def, $allow = false) {
        $this->def = $def;
        $this->allow = $allow;
    }
    public function validate($string, $config, $context) {
        $string = trim($string);
        $is_important = false;
        if (strlen($string) >= 9 && substr($string, -9) === 'important') {
            $temp = rtrim(substr($string, 0, -9));
            if (strlen($temp) >= 1 && substr($temp, -1) === '!') {
                $string = rtrim(substr($temp, 0, -1));
                $is_important = true;
            }
        }
        $string = $this->def->validate($string, $config, $context);
        if ($this->allow && $is_important) $string .= ' !important';
        return $string;
    }
}
class HTMLPurifier_AttrDef_CSS_Length extends HTMLPurifier_AttrDef
{
    protected $units = array('em' => true, 'ex' => true, 'px' => true, 'in' => true,
         'cm' => true, 'mm' => true, 'pt' => true, 'pc' => true);
    protected $number_def;
    public function __construct($non_negative = false) {
        $this->number_def = new HTMLPurifier_AttrDef_CSS_Number($non_negative);
    }
    public function validate($length, $config, $context) {
        $length = $this->parseCDATA($length);
        if ($length === '') return false;
        if ($length === '0') return '0';
        $strlen = strlen($length);
        if ($strlen === 1) return false;  
        $unit = substr($length, $strlen - 2);
        if (!ctype_lower($unit)) $unit = strtolower($unit);
        $number = substr($length, 0, $strlen - 2);
        if (!isset($this->units[$unit])) return false;
        $number = $this->number_def->validate($number, $config, $context);
        if ($number === false) return false;
        return $number . $unit;
    }
}
class HTMLPurifier_AttrDef_CSS_ListStyle extends HTMLPurifier_AttrDef
{
    protected $info;
    public function __construct($config) {
        $def = $config->getCSSDefinition();
        $this->info['list-style-type']     = $def->info['list-style-type'];
        $this->info['list-style-position'] = $def->info['list-style-position'];
        $this->info['list-style-image'] = $def->info['list-style-image'];
    }
    public function validate($string, $config, $context) {
        $string = $this->parseCDATA($string);
        if ($string === '') return false;
        $bits = explode(' ', strtolower($string));  
        $caught = array();
        $caught['type']     = false;
        $caught['position'] = false;
        $caught['image']    = false;
        $i = 0;  
        $none = false;
        foreach ($bits as $bit) {
            if ($i >= 3) return;  
            if ($bit === '') continue;
            foreach ($caught as $key => $status) {
                if ($status !== false) continue;
                $r = $this->info['list-style-' . $key]->validate($bit, $config, $context);
                if ($r === false) continue;
                if ($r === 'none') {
                    if ($none) continue;
                    else $none = true;
                    if ($key == 'image') continue;
                }
                $caught[$key] = $r;
                $i++;
                break;
            }
        }
        if (!$i) return false;
        $ret = array();
        if ($caught['type']) $ret[] = $caught['type'];
        if ($caught['image']) $ret[] = $caught['image'];
        if ($caught['position']) $ret[] = $caught['position'];
        if (empty($ret)) return false;
        return implode(' ', $ret);
    }
}
class HTMLPurifier_AttrDef_CSS_Multiple extends HTMLPurifier_AttrDef
{
    public $single;
    public $max;
    public function __construct($single, $max = 4) {
        $this->single = $single;
        $this->max = $max;
    }
    public function validate($string, $config, $context) {
        $string = $this->parseCDATA($string);
        if ($string === '') return false;
        $parts = explode(' ', $string);  
        $length = count($parts);
        $final = '';
        for ($i = 0, $num = 0; $i < $length && $num < $this->max; $i++) {
            if (ctype_space($parts[$i])) continue;
            $result = $this->single->validate($parts[$i], $config, $context);
            if ($result !== false) {
                $final .= $result . ' ';
                $num++;
            }
        }
        if ($final === '') return false;
        return rtrim($final);
    }
}
class HTMLPurifier_AttrDef_CSS_Percentage extends HTMLPurifier_AttrDef
{
    protected $number_def;
    public function __construct($non_negative = false) {
        $this->number_def = new HTMLPurifier_AttrDef_CSS_Number($non_negative);
    }
    public function validate($string, $config, $context) {
        $string = $this->parseCDATA($string);
        if ($string === '') return false;
        $length = strlen($string);
        if ($length === 1) return false;
        if ($string[$length - 1] !== '%') return false;
        $number = substr($string, 0, $length - 1);
        $number = $this->number_def->validate($number, $config, $context);
        if ($number === false) return false;
        return "$number%";
    }
}
class HTMLPurifier_AttrDef_CSS_TextDecoration extends HTMLPurifier_AttrDef
{
    public function validate($string, $config, $context) {
        static $allowed_values = array(
            'line-through' => true,
            'overline' => true,
            'underline' => true
        );
        $string = strtolower($this->parseCDATA($string));
        $parts = explode(' ', $string);
        $final = '';
        foreach ($parts as $part) {
            if (isset($allowed_values[$part])) {
                $final .= $part . ' ';
            }
        }
        $final = rtrim($final);
        if ($final === '') return false;
        return $final;
    }
}
class HTMLPurifier_AttrDef_CSS_URI extends HTMLPurifier_AttrDef_URI
{
    public function __construct() {
        parent::__construct(true);  
    }
    public function validate($uri_string, $config, $context) {
        $uri_string = $this->parseCDATA($uri_string);
        if (strpos($uri_string, 'url(') !== 0) return false;
        $uri_string = substr($uri_string, 4);
        $new_length = strlen($uri_string) - 1;
        if ($uri_string[$new_length] != ')') return false;
        $uri = trim(substr($uri_string, 0, $new_length));
        if (!empty($uri) && ($uri[0] == "'" || $uri[0] == '"')) {
            $quote = $uri[0];
            $new_length = strlen($uri) - 1;
            if ($uri[$new_length] !== $quote) return false;
            $uri = substr($uri, 1, $new_length - 1);
        }
        $keys   = array(  '(',   ')',   ',',   ' ',   '"',   "'");
        $values = array('\\(', '\\)', '\\,', '\\ ', '\\"', "\\'");
        $uri = str_replace($values, $keys, $uri);
        $result = parent::validate($uri, $config, $context);
        if ($result === false) return false;
        $result = str_replace($keys, $values, $result);
        return "url($result)";
    }
}
class HTMLPurifier_AttrDef_HTML_Bool extends HTMLPurifier_AttrDef
{
    protected $name;
    public $minimized = true;
    public function __construct($name = false) {$this->name = $name;}
    public function validate($string, $config, $context) {
        if (empty($string)) return false;
        return $this->name;
    }
    public function make($string) {
        return new HTMLPurifier_AttrDef_HTML_Bool($string);
    }
}
class HTMLPurifier_AttrDef_HTML_Color extends HTMLPurifier_AttrDef
{
    public function validate($string, $config, $context) {
        static $colors = null;
        if ($colors === null) $colors = $config->get('Core', 'ColorKeywords');
        $string = trim($string);
        if (empty($string)) return false;
        if (isset($colors[$string])) return $colors[$string];
        if ($string[0] === '#') $hex = substr($string, 1);
        else $hex = $string;
        $length = strlen($hex);
        if ($length !== 3 && $length !== 6) return false;
        if (!ctype_xdigit($hex)) return false;
        if ($length === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        return "#$hex";
    }
}
class HTMLPurifier_AttrDef_HTML_FrameTarget extends HTMLPurifier_AttrDef_Enum
{
    public $valid_values = false;  
    protected $case_sensitive = false;
    public function __construct() {}
    public function validate($string, $config, $context) {
        if ($this->valid_values === false) $this->valid_values = $config->get('Attr', 'AllowedFrameTargets');
        return parent::validate($string, $config, $context);
    }
}
class HTMLPurifier_AttrDef_HTML_ID extends HTMLPurifier_AttrDef
{
    public function validate($id, $config, $context) {
        if (!$config->get('Attr', 'EnableID')) return false;
        $id = trim($id);  
        if ($id === '') return false;
        $prefix = $config->get('Attr', 'IDPrefix');
        if ($prefix !== '') {
            $prefix .= $config->get('Attr', 'IDPrefixLocal');
            if (strpos($id, $prefix) !== 0) $id = $prefix . $id;
        } elseif ($config->get('Attr', 'IDPrefixLocal') !== '') {
            trigger_error('%Attr.IDPrefixLocal cannot be used unless '.
                '%Attr.IDPrefix is set', E_USER_WARNING);
        }
       $id_accumulator =& $context->get('IDAccumulator');
       if (isset($id_accumulator->ids[$id])) return false;
        if (ctype_alpha($id)) {
            $result = true;
        } else {
            if (!ctype_alpha(@$id[0])) return false;
            $trim = trim(  
                $id,
                'A..Za..z0..9:-._'
              );
            $result = ($trim === '');
        }
        $regexp = $config->get('Attr', 'IDBlacklistRegexp');
        if ($regexp && preg_match($regexp, $id)) {
            return false;
        }
        if (/*!$this->ref && */$result) $id_accumulator->add($id);
        return $result ? $id : false;
    }
}
class HTMLPurifier_AttrDef_HTML_Pixels extends HTMLPurifier_AttrDef
{
    public function validate($string, $config, $context) {
        $string = trim($string);
        if ($string === '0') return $string;
        if ($string === '')  return false;
        $length = strlen($string);
        if (substr($string, $length - 2) == 'px') {
            $string = substr($string, 0, $length - 2);
        }
        if (!is_numeric($string)) return false;
        $int = (int) $string;
        if ($int < 0) return '0';
        if ($int > 1200) return '1200';
        return (string) $int;
    }
}
class HTMLPurifier_AttrDef_HTML_Length extends HTMLPurifier_AttrDef_HTML_Pixels
{
    public function validate($string, $config, $context) {
        $string = trim($string);
        if ($string === '') return false;
        $parent_result = parent::validate($string, $config, $context);
        if ($parent_result !== false) return $parent_result;
        $length = strlen($string);
        $last_char = $string[$length - 1];
        if ($last_char !== '%') return false;
        $points = substr($string, 0, $length - 1);
        if (!is_numeric($points)) return false;
        $points = (int) $points;
        if ($points < 0) return '0%';
        if ($points > 100) return '100%';
        return ((string) $points) . '%';
    }
}
class HTMLPurifier_AttrDef_HTML_LinkTypes extends HTMLPurifier_AttrDef
{
    /** Name config attribute to pull. */
    protected $name;
    public function __construct($name) {
        $configLookup = array(
            'rel' => 'AllowedRel',
            'rev' => 'AllowedRev'
        );
        if (!isset($configLookup[$name])) {
            trigger_error('Unrecognized attribute name for link '.
                'relationship.', E_USER_ERROR);
            return;
        }
        $this->name = $configLookup[$name];
    }
    public function validate($string, $config, $context) {
        $allowed = $config->get('Attr', $this->name);
        if (empty($allowed)) return false;
        $string = $this->parseCDATA($string);
        $parts = explode(' ', $string);
        $ret_lookup = array();
        foreach ($parts as $part) {
            $part = strtolower(trim($part));
            if (!isset($allowed[$part])) continue;
            $ret_lookup[$part] = true;
        }
        if (empty($ret_lookup)) return false;
        $ret_array = array();
        foreach ($ret_lookup as $part => $bool) $ret_array[] = $part;
        $string = implode(' ', $ret_array);
        return $string;
    }
}
class HTMLPurifier_AttrDef_HTML_MultiLength extends HTMLPurifier_AttrDef_HTML_Length
{
    public function validate($string, $config, $context) {
        $string = trim($string);
        if ($string === '') return false;
        $parent_result = parent::validate($string, $config, $context);
        if ($parent_result !== false) return $parent_result;
        $length = strlen($string);
        $last_char = $string[$length - 1];
        if ($last_char !== '*') return false;
        $int = substr($string, 0, $length - 1);
        if ($int == '') return '*';
        if (!is_numeric($int)) return false;
        $int = (int) $int;
        if ($int < 0) return false;
        if ($int == 0) return '0';
        if ($int == 1) return '*';
        return ((string) $int) . '*';
    }
}
class HTMLPurifier_AttrDef_HTML_Nmtokens extends HTMLPurifier_AttrDef
{
    public function validate($string, $config, $context) {
        $string = trim($string);
        if (!$string) return false;
        $matches = array();
        $pattern = '/(?:(?<=\s)|\A)'.  
                   '((?:--|-?[A-Za-z_])[A-Za-z_\-0-9]*)'.
                   '(?:(?=\s)|\z)/';  
        preg_match_all($pattern, $string, $matches);
        if (empty($matches[1])) return false;
        $new_string = '';
        foreach ($matches[1] as $token) {
            $new_string .= $token . ' ';
        }
        $new_string = rtrim($new_string);
        return $new_string;
    }
}
abstract class HTMLPurifier_AttrDef_URI_Email extends HTMLPurifier_AttrDef
{
    function unpack($string) {
    }
}
class HTMLPurifier_AttrDef_URI_Host extends HTMLPurifier_AttrDef
{
    protected $ipv4;
    protected $ipv6;
    public function __construct() {
        $this->ipv4 = new HTMLPurifier_AttrDef_URI_IPv4();
        $this->ipv6 = new HTMLPurifier_AttrDef_URI_IPv6();
    }
    public function validate($string, $config, $context) {
        $length = strlen($string);
        if ($string === '') return '';
        if ($length > 1 && $string[0] === '[' && $string[$length-1] === ']') {
            $ip = substr($string, 1, $length - 2);
            $valid = $this->ipv6->validate($ip, $config, $context);
            if ($valid === false) return false;
            return '['. $valid . ']';
        }
        $ipv4 = $this->ipv4->validate($string, $config, $context);
        if ($ipv4 !== false) return $ipv4;
        $a   = '[a-z]';      
        $an  = '[a-z0-9]';   
        $and = '[a-z0-9-]';  
        $domainlabel   = "$an($and*$an)?";
        $toplabel      = "$a($and*$an)?";
        $match = preg_match("/^($domainlabel\.)*$toplabel\.?$/i", $string);
        if (!$match) return false;
        return $string;
    }
}
class HTMLPurifier_AttrDef_URI_IPv4 extends HTMLPurifier_AttrDef
{
    protected $ip4;
    public function validate($aIP, $config, $context) {
        if (!$this->ip4) $this->_loadRegex();
        if (preg_match('#^' . $this->ip4 . '$#s', $aIP))
        {
                return $aIP;
        }
        return false;
    }
    protected function _loadRegex() {
        $oct = '(?:25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9][0-9]|[0-9])';  
        $this->ip4 = "(?:{$oct}\\.{$oct}\\.{$oct}\\.{$oct})";
    }
}
class HTMLPurifier_AttrDef_URI_IPv6 extends HTMLPurifier_AttrDef_URI_IPv4
{
    public function validate($aIP, $config, $context) {
        if (!$this->ip4) $this->_loadRegex();
        $original = $aIP;
        $hex = '[0-9a-fA-F]';
        $blk = '(?:' . $hex . '{1,4})';
        $pre = '(?:/(?:12[0-8]|1[0-1][0-9]|[1-9][0-9]|[0-9]))';    
        if (strpos($aIP, '/') !== false)
        {
                if (preg_match('#' . $pre . '$#s', $aIP, $find))
                {
                        $aIP = substr($aIP, 0, 0-strlen($find[0]));
                        unset($find);
                }
                else
                {
                        return false;
                }
        }
        if (preg_match('#(?<=:'.')' . $this->ip4 . '$#s', $aIP, $find))
        {
                $aIP = substr($aIP, 0, 0-strlen($find[0]));
                $ip = explode('.', $find[0]);
                $ip = array_map('dechex', $ip);
                $aIP .= $ip[0] . $ip[1] . ':' . $ip[2] . $ip[3];
                unset($find, $ip);
        }
        $aIP = explode('::', $aIP);
        $c = count($aIP);
        if ($c > 2)
        {
                return false;
        }
        elseif ($c == 2)
        {
                list($first, $second) = $aIP;
                $first = explode(':', $first);
                $second = explode(':', $second);
                if (count($first) + count($second) > 8)
                {
                        return false;
                }
                while(count($first) < 8)
                {
                        array_push($first, '0');
                }
                array_splice($first, 8 - count($second), 8, $second);
                $aIP = $first;
                unset($first,$second);
        }
        else
        {
                $aIP = explode(':', $aIP[0]);
        }
        $c = count($aIP);
        if ($c != 8)
        {
                return false;
        }
        foreach ($aIP as $piece)
        {
                if (!preg_match('#^[0-9a-fA-F]{4}$#s', sprintf('%04s', $piece)))
                {
                        return false;
                }
        }
        return $original;
    }
}
class HTMLPurifier_AttrDef_URI_Email_SimpleCheck extends HTMLPurifier_AttrDef_URI_Email
{
    public function validate($string, $config, $context) {
        if ($string == '') return false;
        $string = trim($string);
        $result = preg_match('/^[A-Z0-9._%-]+@[A-Z0-9.-]+\.[A-Z]{2,4}$/i', $string);
        return $result ? $string : false;
    }
}
class HTMLPurifier_AttrTransform_BdoDir extends HTMLPurifier_AttrTransform
{
    public function transform($attr, $config, $context) {
        if (isset($attr['dir'])) return $attr;
        $attr['dir'] = $config->get('Attr', 'DefaultTextDir');
        return $attr;
    }
}
class HTMLPurifier_AttrTransform_BgColor extends HTMLPurifier_AttrTransform {
    public function transform($attr, $config, $context) {
        if (!isset($attr['bgcolor'])) return $attr;
        $bgcolor = $this->confiscateAttr($attr, 'bgcolor');
        $this->prependCSS($attr, "background-color:$bgcolor;");
        return $attr;
    }
}
class HTMLPurifier_AttrTransform_BoolToCSS extends HTMLPurifier_AttrTransform {
    protected $attr;
    protected $css;
    public function __construct($attr, $css) {
        $this->attr = $attr;
        $this->css  = $css;
    }
    public function transform($attr, $config, $context) {
        if (!isset($attr[$this->attr])) return $attr;
        unset($attr[$this->attr]);
        $this->prependCSS($attr, $this->css);
        return $attr;
    }
}
class HTMLPurifier_AttrTransform_Border extends HTMLPurifier_AttrTransform {
    public function transform($attr, $config, $context) {
        if (!isset($attr['border'])) return $attr;
        $border_width = $this->confiscateAttr($attr, 'border');
        $this->prependCSS($attr, "border:{$border_width}px solid;");
        return $attr;
    }
}
class HTMLPurifier_AttrTransform_EnumToCSS extends HTMLPurifier_AttrTransform {
    protected $attr;
    protected $enumToCSS = array();
    protected $caseSensitive = false;
    public function __construct($attr, $enum_to_css, $case_sensitive = false) {
        $this->attr = $attr;
        $this->enumToCSS = $enum_to_css;
        $this->caseSensitive = (bool) $case_sensitive;
    }
    public function transform($attr, $config, $context) {
        if (!isset($attr[$this->attr])) return $attr;
        $value = trim($attr[$this->attr]);
        unset($attr[$this->attr]);
        if (!$this->caseSensitive) $value = strtolower($value);
        if (!isset($this->enumToCSS[$value])) {
            return $attr;
        }
        $this->prependCSS($attr, $this->enumToCSS[$value]);
        return $attr;
    }
}
class HTMLPurifier_AttrTransform_ImgRequired extends HTMLPurifier_AttrTransform
{
    public function transform($attr, $config, $context) {
        $src = true;
        if (!isset($attr['src'])) {
            if ($config->get('Core', 'RemoveInvalidImg')) return $attr;
            $attr['src'] = $config->get('Attr', 'DefaultInvalidImage');
            $src = false;
        }
        if (!isset($attr['alt'])) {
            if ($src) {
                $attr['alt'] = basename($attr['src']);
            } else {
                $attr['alt'] = $config->get('Attr', 'DefaultInvalidImageAlt');
            }
        }
        return $attr;
    }
}
class HTMLPurifier_AttrTransform_ImgSpace extends HTMLPurifier_AttrTransform {
    protected $attr;
    protected $css = array(
        'hspace' => array('left', 'right'),
        'vspace' => array('top', 'bottom')
    );
    public function __construct($attr) {
        $this->attr = $attr;
        if (!isset($this->css[$attr])) {
            trigger_error(htmlspecialchars($attr) . ' is not valid space attribute');
        }
    }
    public function transform($attr, $config, $context) {
        if (!isset($attr[$this->attr])) return $attr;
        $width = $this->confiscateAttr($attr, $this->attr);
        if (!isset($this->css[$this->attr])) return $attr;
        $style = '';
        foreach ($this->css[$this->attr] as $suffix) {
            $property = "margin-$suffix";
            $style .= "$property:{$width}px;";
        }
        $this->prependCSS($attr, $style);
        return $attr;
    }
}
class HTMLPurifier_AttrTransform_Lang extends HTMLPurifier_AttrTransform
{
    public function transform($attr, $config, $context) {
        $lang     = isset($attr['lang']) ? $attr['lang'] : false;
        $xml_lang = isset($attr['xml:lang']) ? $attr['xml:lang'] : false;
        if ($lang !== false && $xml_lang === false) {
            $attr['xml:lang'] = $lang;
        } elseif ($xml_lang !== false) {
            $attr['lang'] = $xml_lang;
        }
        return $attr;
    }
}
class HTMLPurifier_AttrTransform_Length extends HTMLPurifier_AttrTransform
{
    protected $name;
    protected $cssName;
    public function __construct($name, $css_name = null) {
        $this->name = $name;
        $this->cssName = $css_name ? $css_name : $name;
    }
    public function transform($attr, $config, $context) {
        if (!isset($attr[$this->name])) return $attr;
        $length = $this->confiscateAttr($attr, $this->name);
        if(ctype_digit($length)) $length .= 'px';
        $this->prependCSS($attr, $this->cssName . ":$length;");
        return $attr;
    }
}
class HTMLPurifier_AttrTransform_Name extends HTMLPurifier_AttrTransform
{
    public function transform($attr, $config, $context) {
        if (!isset($attr['name'])) return $attr;
        $id = $this->confiscateAttr($attr, 'name');
        if ( isset($attr['id']))   return $attr;
        $attr['id'] = $id;
        return $attr;
    }
}
class HTMLPurifier_AttrTransform_ScriptRequired extends HTMLPurifier_AttrTransform
{
    public function transform($attr, $config, $context) {
        if (!isset($attr['type'])) {
            $attr['type'] = 'text/javascript';
        }
        return $attr;
    }
}
class HTMLPurifier_ChildDef_Chameleon extends HTMLPurifier_ChildDef
{
    public $inline;
    public $block;
    public $type = 'chameleon';
    public function __construct($inline, $block) {
        $this->inline = new HTMLPurifier_ChildDef_Optional($inline);
        $this->block  = new HTMLPurifier_ChildDef_Optional($block);
        $this->elements = $this->block->elements;
    }
    public function validateChildren($tokens_of_children, $config, $context) {
        if ($context->get('IsInline') === false) {
            return $this->block->validateChildren(
                $tokens_of_children, $config, $context);
        } else {
            return $this->inline->validateChildren(
                $tokens_of_children, $config, $context);
        }
    }
}
class HTMLPurifier_ChildDef_Custom extends HTMLPurifier_ChildDef
{
    public $type = 'custom';
    public $allow_empty = false;
    public $dtd_regex;
    private $_pcre_regex;
    public function __construct($dtd_regex) {
        $this->dtd_regex = $dtd_regex;
        $this->_compileRegex();
    }
    protected function _compileRegex() {
        $raw = str_replace(' ', '', $this->dtd_regex);
        if ($raw{0} != '(') {
            $raw = "($raw)";
        }
        $el = '[#a-zA-Z0-9_.-]+';
        $reg = $raw;
        preg_match_all("/$el/", $reg, $matches);
        foreach ($matches[0] as $match) {
            $this->elements[$match] = true;
        }
        $reg = preg_replace("/$el/", '(,\\0)', $reg);
        $reg = preg_replace("/([^,(|]\(+),/", '\\1', $reg);
        $reg = preg_replace("/,\(/", '(', $reg);
        $this->_pcre_regex = $reg;
    }
    public function validateChildren($tokens_of_children, $config, $context) {
        $list_of_children = '';
        $nesting = 0;  
        foreach ($tokens_of_children as $token) {
            if (!empty($token->is_whitespace)) continue;
            $is_child = ($nesting == 0);  
            if ($token instanceof HTMLPurifier_Token_Start) {
                $nesting++;
            } elseif ($token instanceof HTMLPurifier_Token_End) {
                $nesting--;
            }
            if ($is_child) {
                $list_of_children .= $token->name . ',';
            }
        }
        $list_of_children = ',' . rtrim($list_of_children, ',');
        $okay =
            preg_match(
                '/^,?'.$this->_pcre_regex.'$/',
                $list_of_children
            );
        return (bool) $okay;
    }
}
class HTMLPurifier_ChildDef_Empty extends HTMLPurifier_ChildDef
{
    public $allow_empty = true;
    public $type = 'empty';
    public function __construct() {}
    public function validateChildren($tokens_of_children, $config, $context) {
        return array();
    }
}
class HTMLPurifier_ChildDef_Required extends HTMLPurifier_ChildDef
{
    public $elements = array();
    public function __construct($elements) {
        if (is_string($elements)) {
            $elements = str_replace(' ', '', $elements);
            $elements = explode('|', $elements);
        }
        $keys = array_keys($elements);
        if ($keys == array_keys($keys)) {
            $elements = array_flip($elements);
            foreach ($elements as $i => $x) {
                $elements[$i] = true;
                if (empty($i)) unset($elements[$i]);  
            }
        }
        $this->elements = $elements;
    }
    public $allow_empty = false;
    public $type = 'required';
    public function validateChildren($tokens_of_children, $config, $context) {
        if (empty($tokens_of_children)) return false;
        $result = array();
        $nesting = 0;
        $is_deleting = false;
        $pcdata_allowed = isset($this->elements['#PCDATA']);
        $all_whitespace = true;
        $escape_invalid_children = $config->get('Core', 'EscapeInvalidChildren');
        static $gen = null;
        if ($gen === null) {
            $gen = new HTMLPurifier_Generator();
        }
        foreach ($tokens_of_children as $token) {
            if (!empty($token->is_whitespace)) {
                $result[] = $token;
                continue;
            }
            $all_whitespace = false;  
            $is_child = ($nesting == 0);
            if ($token instanceof HTMLPurifier_Token_Start) {
                $nesting++;
            } elseif ($token instanceof HTMLPurifier_Token_End) {
                $nesting--;
            }
            if ($is_child) {
                $is_deleting = false;
                if (!isset($this->elements[$token->name])) {
                    $is_deleting = true;
                    if ($pcdata_allowed && $token instanceof HTMLPurifier_Token_Text) {
                        $result[] = $token;
                    } elseif ($pcdata_allowed && $escape_invalid_children) {
                        $result[] = new HTMLPurifier_Token_Text(
                            $gen->generateFromToken($token, $config)
                        );
                    }
                    continue;
                }
            }
            if (!$is_deleting || ($pcdata_allowed && $token instanceof HTMLPurifier_Token_Text)) {
                $result[] = $token;
            } elseif ($pcdata_allowed && $escape_invalid_children) {
                $result[] =
                    new HTMLPurifier_Token_Text(
                        $gen->generateFromToken( $token, $config )
                    );
            } else {
            }
        }
        if (empty($result)) return false;
        if ($all_whitespace) return false;
        if ($tokens_of_children == $result) return true;
        return $result;
    }
}
class HTMLPurifier_ChildDef_Optional extends HTMLPurifier_ChildDef_Required
{
    public $allow_empty = true;
    public $type = 'optional';
    public function validateChildren($tokens_of_children, $config, $context) {
        $result = parent::validateChildren($tokens_of_children, $config, $context);
        if ($result === false) {
            if (empty($tokens_of_children)) return true;
            else return array();
        }
        return $result;
    }
}
class HTMLPurifier_ChildDef_StrictBlockquote extends HTMLPurifier_ChildDef_Required
{
    protected $real_elements;
    protected $fake_elements;
    public $allow_empty = true;
    public $type = 'strictblockquote';
    protected $init = false;
    public function validateChildren($tokens_of_children, $config, $context) {
        $def = $config->getHTMLDefinition();
        if (!$this->init) {
            $this->real_elements = $this->elements;
            $this->fake_elements = $def->info_content_sets['Flow'];
            $this->fake_elements['#PCDATA'] = true;
            $this->init = true;
        }
        $this->elements = $this->fake_elements;
        $result = parent::validateChildren($tokens_of_children, $config, $context);
        $this->elements = $this->real_elements;
        if ($result === false) return array();
        if ($result === true) $result = $tokens_of_children;
        $block_wrap_start = new HTMLPurifier_Token_Start($def->info_block_wrapper);
        $block_wrap_end   = new HTMLPurifier_Token_End(  $def->info_block_wrapper);
        $is_inline = false;
        $depth = 0;
        $ret = array();
        foreach ($result as $i => $token) {
            $token = $result[$i];
            if (!$is_inline) {
                if (!$depth) {
                     if (
                        ($token instanceof HTMLPurifier_Token_Text && !$token->is_whitespace) ||
                        (!$token instanceof HTMLPurifier_Token_Text && !isset($this->elements[$token->name]))
                     ) {
                        $is_inline = true;
                        $ret[] = $block_wrap_start;
                     }
                }
            } else {
                if (!$depth) {
                    if ($token instanceof HTMLPurifier_Token_Start || $token instanceof HTMLPurifier_Token_Empty) {
                        if (isset($this->elements[$token->name])) {
                            $ret[] = $block_wrap_end;
                            $is_inline = false;
                        }
                    }
                }
            }
            $ret[] = $token;
            if ($token instanceof HTMLPurifier_Token_Start) $depth++;
            if ($token instanceof HTMLPurifier_Token_End)   $depth--;
        }
        if ($is_inline) $ret[] = $block_wrap_end;
        return $ret;
    }
}
class HTMLPurifier_ChildDef_Table extends HTMLPurifier_ChildDef
{
    public $allow_empty = false;
    public $type = 'table';
    public $elements = array('tr' => true, 'tbody' => true, 'thead' => true,
        'tfoot' => true, 'caption' => true, 'colgroup' => true, 'col' => true);
    public function __construct() {}
    public function validateChildren($tokens_of_children, $config, $context) {
        if (empty($tokens_of_children)) return false;
        $tokens_of_children[] = false;
        $caption = false;
        $thead   = false;
        $tfoot   = false;
        $cols    = array();
        $content = array();
        $nesting = 0;  
        $is_collecting = false;  
        $collection = array();  
        $tag_index = 0;  
        foreach ($tokens_of_children as $token) {
            $is_child = ($nesting == 0);
            if ($token === false) {
            } elseif ($token instanceof HTMLPurifier_Token_Start) {
                $nesting++;
            } elseif ($token instanceof HTMLPurifier_Token_End) {
                $nesting--;
            }
            if ($is_collecting) {
                if ($is_child) {
                    switch ($collection[$tag_index]->name) {
                        case 'tr':
                        case 'tbody':
                            $content[] = $collection;
                            break;
                        case 'caption':
                            if ($caption !== false) break;
                            $caption = $collection;
                            break;
                        case 'thead':
                        case 'tfoot':
                            $var = $collection[$tag_index]->name;
                            if ($$var === false) {
                                $$var = $collection;
                            } else {
                                $collection[$tag_index]->name = 'tbody';
                                $collection[count($collection)-1]->name = 'tbody';
                                $content[] = $collection;
                            }
                            break;
                         case 'colgroup':
                            $cols[] = $collection;
                            break;
                    }
                    $collection = array();
                    $is_collecting = false;
                    $tag_index = 0;
                } else {
                    $collection[] = $token;
                }
            }
            if ($token === false) break;
            if ($is_child) {
                if ($token->name == 'col') {
                    $cols[] = array_merge($collection, array($token));
                    $collection = array();
                    $tag_index = 0;
                    continue;
                }
                switch($token->name) {
                    case 'caption':
                    case 'colgroup':
                    case 'thead':
                    case 'tfoot':
                    case 'tbody':
                    case 'tr':
                        $is_collecting = true;
                        $collection[] = $token;
                        continue;
                    default:
                        if ($token instanceof HTMLPurifier_Token_Text && $token->is_whitespace) {
                            $collection[] = $token;
                            $tag_index++;
                        }
                        continue;
                }
            }
        }
        if (empty($content)) return false;
        $ret = array();
        if ($caption !== false) $ret = array_merge($ret, $caption);
        if ($cols !== false)    foreach ($cols as $token_array) $ret = array_merge($ret, $token_array);
        if ($thead !== false)   $ret = array_merge($ret, $thead);
        if ($tfoot !== false)   $ret = array_merge($ret, $tfoot);
        foreach ($content as $token_array) $ret = array_merge($ret, $token_array);
        if (!empty($collection) && $is_collecting == false){
            $ret = array_merge($ret, $collection);
        }
        array_pop($tokens_of_children);  
        return ($ret === $tokens_of_children) ? true : $ret;
    }
}
class HTMLPurifier_ConfigDef_Directive extends HTMLPurifier_ConfigDef
{
    public $class = 'directive';
    public function __construct(
        $type = null,
        $allow_null = null,
        $allowed = null,
        $aliases = null
    ) {
        if (       $type !== null)        $this->type = $type;
        if ( $allow_null !== null)  $this->allow_null = $allow_null;
        if (    $allowed !== null)     $this->allowed = $allowed;
        if (    $aliases !== null)     $this->aliases = $aliases;
    }
    public $type = 'mixed';
    public $allow_null = false;
    public $allowed = true;
    public $aliases = array();
}
class HTMLPurifier_ConfigDef_DirectiveAlias extends HTMLPurifier_ConfigDef
{
    public $class = 'alias';
    public $namespace;
    public $name;
    public function __construct($namespace, $name) {
        $this->namespace = $namespace;
        $this->name = $name;
    }
}
class HTMLPurifier_ConfigDef_Namespace extends HTMLPurifier_ConfigDef
{
    public $class = 'namespace';
}
class HTMLPurifier_DefinitionCache_Decorator extends HTMLPurifier_DefinitionCache
{
    public $cache;
    public function __construct() {}
    public function decorate(&$cache) {
        $decorator = $this->copy();
        $decorator->cache =& $cache;
        $decorator->type  = $cache->type;
        return $decorator;
    }
    public function copy() {
        return new HTMLPurifier_DefinitionCache_Decorator();
    }
    public function add($def, $config) {
        return $this->cache->add($def, $config);
    }
    public function set($def, $config) {
        return $this->cache->set($def, $config);
    }
    public function replace($def, $config) {
        return $this->cache->replace($def, $config);
    }
    public function get($config) {
        return $this->cache->get($config);
    }
    public function remove($config) {
        return $this->cache->remove($config);
    }
    public function flush($config) {
        return $this->cache->flush($config);
    }
    public function cleanup($config) {
        return $this->cache->cleanup($config);
    }
}
class HTMLPurifier_DefinitionCache_Null extends HTMLPurifier_DefinitionCache
{
    public function add($def, $config) {
        return false;
    }
    public function set($def, $config) {
        return false;
    }
    public function replace($def, $config) {
        return false;
    }
    public function remove($config) {
        return false;
    }
    public function get($config) {
        return false;
    }
    public function flush($config) {
        return false;
    }
    public function cleanup($config) {
        return false;
    }
}
class HTMLPurifier_DefinitionCache_Serializer extends
      HTMLPurifier_DefinitionCache
{
    public function add($def, $config) {
        if (!$this->checkDefType($def)) return;
        $file = $this->generateFilePath($config);
        if (file_exists($file)) return false;
        if (!$this->_prepareDir($config)) return false;
        return $this->_write($file, serialize($def));
    }
    public function set($def, $config) {
        if (!$this->checkDefType($def)) return;
        $file = $this->generateFilePath($config);
        if (!$this->_prepareDir($config)) return false;
        return $this->_write($file, serialize($def));
    }
    public function replace($def, $config) {
        if (!$this->checkDefType($def)) return;
        $file = $this->generateFilePath($config);
        if (!file_exists($file)) return false;
        if (!$this->_prepareDir($config)) return false;
        return $this->_write($file, serialize($def));
    }
    public function get($config) {
        $file = $this->generateFilePath($config);
        if (!file_exists($file)) return false;
        return unserialize(file_get_contents($file));
    }
    public function remove($config) {
        $file = $this->generateFilePath($config);
        if (!file_exists($file)) return false;
        return unlink($file);
    }
    public function flush($config) {
        if (!$this->_prepareDir($config)) return false;
        $dir = $this->generateDirectoryPath($config);
        $dh  = opendir($dir);
        while (false !== ($filename = readdir($dh))) {
            if (empty($filename)) continue;
            if ($filename[0] === '.') continue;
            unlink($dir . '/' . $filename);
        }
    }
    public function cleanup($config) {
        if (!$this->_prepareDir($config)) return false;
        $dir = $this->generateDirectoryPath($config);
        $dh  = opendir($dir);
        while (false !== ($filename = readdir($dh))) {
            if (empty($filename)) continue;
            if ($filename[0] === '.') continue;
            $key = substr($filename, 0, strlen($filename) - 4);
            if ($this->isOld($key, $config)) unlink($dir . '/' . $filename);
        }
    }
    public function generateFilePath($config) {
        $key = $this->generateKey($config);
        return $this->generateDirectoryPath($config) . '/' . $key . '.ser';
    }
    public function generateDirectoryPath($config) {
        return A_PREFIX .'tmp/guard';
    }
    private function _write($file, $data) {
        return file_put_contents($file, $data);
    }
    private function _prepareDir($config) {
        $directory = $this->generateDirectoryPath($config);
        return $this->_testPermissions($directory) ;
    }
    private function _testPermissions($dir) {
        if (is_writable($dir)) return true;
        if (!is_dir($dir)) {
            trigger_error('Directory '.$dir.' does not exist',
                E_USER_ERROR);
            return false;
        }
        if (function_exists('posix_getuid')) {
            if (fileowner($dir) === posix_getuid()) {
                chmod($dir, 0755);
                return true;
            } elseif (filegroup($dir) === posix_getgid()) {
                $chmod = '775';
            } else {
                $chmod = '777';
            }
            trigger_error('Directory '.$dir.' not writable, '.
                'please chmod to ' . $chmod,
                E_USER_ERROR);
        } else {
            trigger_error('Directory '.$dir.' not writable, '.
                'please alter file permissions',
                E_USER_ERROR);
        }
        return false;
    }
}
class HTMLPurifier_DefinitionCache_Decorator_Cleanup extends
      HTMLPurifier_DefinitionCache_Decorator
{
    public $name = 'Cleanup';
    public function copy() {
        return new HTMLPurifier_DefinitionCache_Decorator_Cleanup();
    }
    public function add($def, $config) {
        $status = parent::add($def, $config);
        if (!$status) parent::cleanup($config);
        return $status;
    }
    public function set($def, $config) {
        $status = parent::set($def, $config);
        if (!$status) parent::cleanup($config);
        return $status;
    }
    public function replace($def, $config) {
        $status = parent::replace($def, $config);
        if (!$status) parent::cleanup($config);
        return $status;
    }
    public function get($config) {
        $ret = parent::get($config);
        if (!$ret) parent::cleanup($config);
        return $ret;
    }
}
class HTMLPurifier_DefinitionCache_Decorator_Memory extends
      HTMLPurifier_DefinitionCache_Decorator
{
    protected $definitions;
    public $name = 'Memory';
    public function copy() {
        return new HTMLPurifier_DefinitionCache_Decorator_Memory();
    }
    public function add($def, $config) {
        $status = parent::add($def, $config);
        if ($status) $this->definitions[$this->generateKey($config)] = $def;
        return $status;
    }
    public function set($def, $config) {
        $status = parent::set($def, $config);
        if ($status) $this->definitions[$this->generateKey($config)] = $def;
        return $status;
    }
    public function replace($def, $config) {
        $status = parent::replace($def, $config);
        if ($status) $this->definitions[$this->generateKey($config)] = $def;
        return $status;
    }
    public function get($config) {
        $key = $this->generateKey($config);
        if (isset($this->definitions[$key])) return $this->definitions[$key];
        $this->definitions[$key] = parent::get($config);
        return $this->definitions[$key];
    }
}
class HTMLPurifier_HTMLModule_Bdo extends HTMLPurifier_HTMLModule
{
    public $name = 'Bdo';
    public $attr_collections = array(
        'I18N' => array('dir' => false)
    );
    public function __construct() {
        $bdo = $this->addElement(
            'bdo', 'Inline', 'Inline', array('Core', 'Lang'),
            array(
                'dir' => 'Enum#ltr,rtl',  
            )
        );
        $bdo->attr_transform_post['required-dir'] = new HTMLPurifier_AttrTransform_BdoDir();
        $this->attr_collections['I18N']['dir'] = 'Enum#ltr,rtl';
    }
}
class HTMLPurifier_HTMLModule_CommonAttributes extends HTMLPurifier_HTMLModule
{
    public $name = 'CommonAttributes';
    public $attr_collections = array(
        'Core' => array(
            0 => array('Style'),
            'class' => 'NMTOKENS',
            'id' => 'ID',
            'title' => 'CDATA',
        ),
        'Lang' => array(),
        'I18N' => array(
            0 => array('Lang'),  
        ),
        'Common' => array(
            0 => array('Core', 'I18N')
        )
    );
}
class HTMLPurifier_HTMLModule_Edit extends HTMLPurifier_HTMLModule
{
    public $name = 'Edit';
    public function __construct() {
        $contents = 'Chameleon: #PCDATA | Inline ! #PCDATA | Flow';
        $attr = array(
            'cite' => 'URI',
        );
        $this->addElement('del', 'Inline', $contents, 'Common', $attr);
        $this->addElement('ins', 'Inline', $contents, 'Common', $attr);
    }
    public $defines_child_def = true;
    public function getChildDef($def) {
        if ($def->content_model_type != 'chameleon') return false;
        $value = explode('!', $def->content_model);
        return new HTMLPurifier_ChildDef_Chameleon($value[0], $value[1]);
    }
}
class HTMLPurifier_HTMLModule_Hypertext extends HTMLPurifier_HTMLModule
{
    public $name = 'Hypertext';
    public function __construct() {
        $a = $this->addElement(
            'a', 'Inline', 'Inline', 'Common',
            array(
                'href' => 'URI',
                'rel' => new HTMLPurifier_AttrDef_HTML_LinkTypes('rel'),
                'rev' => new HTMLPurifier_AttrDef_HTML_LinkTypes('rev'),
            )
        );
        $a->excludes = array('a' => true);
    }
}
class HTMLPurifier_HTMLModule_Image extends HTMLPurifier_HTMLModule
{
    public $name = 'Image';
    public function __construct() {
        $img = $this->addElement(
            'img', 'Inline', 'Empty', 'Common',
            array(
                'alt*' => 'Text',
                'height' => 'Length',
                'longdesc' => 'URI', 
                'src*' => new HTMLPurifier_AttrDef_URI(true),  
                'width' => 'Length'
            )
        );
        $img->attr_transform_pre[] =
        $img->attr_transform_post[] =
            new HTMLPurifier_AttrTransform_ImgRequired();
    }
}
class HTMLPurifier_HTMLModule_Legacy extends HTMLPurifier_HTMLModule
{
    public $name = 'Legacy';
    public function __construct() {
        $this->addElement('basefont', 'Inline', 'Empty', false, array(
            'color' => 'Color',
            'face' => 'Text',  
            'size' => 'Text',  
            'id' => 'ID'
        ));
        $this->addElement('center', 'Block', 'Flow', 'Common');
        $this->addElement('dir', 'Block', 'Required: li', 'Common', array(
            'compact' => 'Bool#compact'
        ));
        $this->addElement('font', 'Inline', 'Inline', array('Core', 'I18N'), array(
            'color' => 'Color',
            'face' => 'Text',  
            'size' => 'Text',  
        ));
        $this->addElement('menu', 'Block', 'Required: li', 'Common', array(
            'compact' => 'Bool#compact'
        ));
        $this->addElement('s', 'Inline', 'Inline', 'Common');
        $this->addElement('strike', 'Inline', 'Inline', 'Common');
        $this->addElement('u', 'Inline', 'Inline', 'Common');
        $align = 'Enum#left,right,center,justify';
        $address = $this->addBlankElement('address');
        $address->content_model = 'Inline | #PCDATA | p';
        $address->content_model_type = 'optional';
        $address->child = false;
        $blockquote = $this->addBlankElement('blockquote');
        $blockquote->content_model = 'Flow | #PCDATA';
        $blockquote->content_model_type = 'optional';
        $blockquote->child = false;
        $br = $this->addBlankElement('br');
        $br->attr['clear'] = 'Enum#left,all,right,none';
        $caption = $this->addBlankElement('caption');
        $caption->attr['align'] = 'Enum#top,bottom,left,right';
        $div = $this->addBlankElement('div');
        $div->attr['align'] = $align;
        $dl = $this->addBlankElement('dl');
        $dl->attr['compact'] = 'Bool#compact';
        for ($i = 1; $i <= 6; $i++) {
            $h = $this->addBlankElement("h$i");
            $h->attr['align'] = $align;
        }
        $hr = $this->addBlankElement('hr');
        $hr->attr['align'] = $align;
        $hr->attr['noshade'] = 'Bool#noshade';
        $hr->attr['size'] = 'Pixels';
        $hr->attr['width'] = 'Length';
        $img = $this->addBlankElement('img');
        $img->attr['align'] = 'Enum#top,middle,bottom,left,right';
        $img->attr['border'] = 'Pixels';
        $img->attr['hspace'] = 'Pixels';
        $img->attr['vspace'] = 'Pixels';
        $li = $this->addBlankElement('li');
        $li->attr['value'] = new HTMLPurifier_AttrDef_Integer();
        $li->attr['type']  = 'Enum#s:1,i,I,a,A,disc,square,circle';
        $ol = $this->addBlankElement('ol');
        $ol->attr['compact'] = 'Bool#compact';
        $ol->attr['start'] = new HTMLPurifier_AttrDef_Integer();
        $ol->attr['type'] = 'Enum#s:1,i,I,a,A';
        $p = $this->addBlankElement('p');
        $p->attr['align'] = $align;
        $pre = $this->addBlankElement('pre');
        $pre->attr['width'] = 'Number';
        $table = $this->addBlankElement('table');
        $table->attr['align'] = 'Enum#left,center,right';
        $table->attr['bgcolor'] = 'Color';
        $tr = $this->addBlankElement('tr');
        $tr->attr['bgcolor'] = 'Color';
        $th = $this->addBlankElement('th');
        $th->attr['bgcolor'] = 'Color';
        $th->attr['height'] = 'Length';
        $th->attr['nowrap'] = 'Bool#nowrap';
        $th->attr['width'] = 'Length';
        $td = $this->addBlankElement('td');
        $td->attr['bgcolor'] = 'Color';
        $td->attr['height'] = 'Length';
        $td->attr['nowrap'] = 'Bool#nowrap';
        $td->attr['width'] = 'Length';
        $ul = $this->addBlankElement('ul');
        $ul->attr['compact'] = 'Bool#compact';
        $ul->attr['type'] = 'Enum#square,disc,circle';
    }
}
class HTMLPurifier_HTMLModule_List extends HTMLPurifier_HTMLModule
{
    public $name = 'List';
    public $content_sets = array('Flow' => 'List');
    public function __construct() {
        $this->addElement('ol', 'List', 'Required: li', 'Common');
        $this->addElement('ul', 'List', 'Required: li', 'Common');
        $this->addElement('dl', 'List', 'Required: dt | dd', 'Common');
        $this->addElement('li', false, 'Flow', 'Common');
        $this->addElement('dd', false, 'Flow', 'Common');
        $this->addElement('dt', false, 'Inline', 'Common');
    }
}
class HTMLPurifier_HTMLModule_NonXMLCommonAttributes extends HTMLPurifier_HTMLModule
{
    public $name = 'NonXMLCommonAttributes';
    public $attr_collections = array(
        'Lang' => array(
            'lang' => 'LanguageCode',
        )
    );
}




class HTMLPurifier_HTMLModule_Object extends HTMLPurifier_HTMLModule
{
    
    public $name = 'Object';
    public $safe = false;
    
    public function __construct() {
        
        $this->addElement('object', 'Inline', 'Optional: #PCDATA | Flow | param', 'Common', 
            array(
                'archive' => 'URI',
                'classid' => 'URI',
                'codebase' => 'URI',
                'codetype' => 'Text',
                'data' => 'URI',
                'declare' => 'Bool#declare',
                'height' => 'Length',
                'name' => 'CDATA',
                'standby' => 'Text',
                'tabindex' => 'Number',
                'type' => 'ContentType',
                'width' => 'Length'
            )
        );

        $this->addElement('param', false, 'Empty', false,
            array(
                'id' => 'ID',
                'name*' => 'Text',
                'type' => 'Text',
                'value' => 'Text',
                'valuetype' => 'Enum#data,ref,object'
           )
        );
    
    }
    
}

class HTMLPurifier_HTMLModule_Presentation extends HTMLPurifier_HTMLModule
{
    public $name = 'Presentation';
    public function __construct() {
        $this->addElement('b',      'Inline', 'Inline', 'Common');
        $this->addElement('big',    'Inline', 'Inline', 'Common');
        $this->addElement('hr',     'Block',  'Empty',  'Common');
        $this->addElement('i',      'Inline', 'Inline', 'Common');
        $this->addElement('small',  'Inline', 'Inline', 'Common');
        $this->addElement('sub',    'Inline', 'Inline', 'Common');
        $this->addElement('sup',    'Inline', 'Inline', 'Common');
        $this->addElement('tt',     'Inline', 'Inline', 'Common');
    }
}
class HTMLPurifier_HTMLModule_Proprietary extends HTMLPurifier_HTMLModule
{
    public $name = 'Proprietary';
    public function __construct() {
        $this->addElement('marquee', 'Inline', 'Flow', 'Common', 
            array(
                'direction' => 'Enum#left,right,up,down',
                'behavior' => 'Enum#alternate',
                'width' => 'Length',
                'height' => 'Length',
                'scrolldelay' => 'Number',
                'scrollamount' => 'Number',
                'loop' => 'Number',
                'bgcolor' => 'Color',
                'hspace' => 'Pixels',
                'vspace' => 'Pixels',
            )
        );
    }
}
class HTMLPurifier_HTMLModule_Ruby extends HTMLPurifier_HTMLModule
{
    public $name = 'Ruby';
    public function __construct() {
        $this->addElement('ruby', 'Inline',
            'Custom: ((rb, (rt | (rp, rt, rp))) | (rbc, rtc, rtc?))',
            'Common');
        $this->addElement('rbc', false, 'Required: rb', 'Common');
        $this->addElement('rtc', false, 'Required: rt', 'Common');
        $rb = $this->addElement('rb', false, 'Inline', 'Common');
        $rb->excludes = array('ruby' => true);
        $rt = $this->addElement('rt', false, 'Inline', 'Common', array('rbspan' => 'Number'));
        $rt->excludes = array('ruby' => true);
        $this->addElement('rp', false, 'Optional: #PCDATA', 'Common');
    }
}
class HTMLPurifier_HTMLModule_Scripting extends HTMLPurifier_HTMLModule
{
    public $name = 'Scripting';
    public $elements = array('script', 'noscript');
    public $content_sets = array('Block' => 'script | noscript', 'Inline' => 'script | noscript');
    public $safe = false;
    public function __construct() {
        $this->info['noscript'] = new HTMLPurifier_ElementDef();
        $this->info['noscript']->attr = array( 0 => array('Common') );
        $this->info['noscript']->content_model = 'Heading | List | Block';
        $this->info['noscript']->content_model_type = 'required';
        $this->info['script'] = new HTMLPurifier_ElementDef();
        $this->info['script']->attr = array(
            'defer' => new HTMLPurifier_AttrDef_Enum(array('defer')),
            'src'   => new HTMLPurifier_AttrDef_URI(true),
            'type'  => new HTMLPurifier_AttrDef_Enum(array('text/javascript'))
        );
        $this->info['script']->content_model = '#PCDATA';
        $this->info['script']->content_model_type = 'optional';
        $this->info['script']->attr_transform_pre['type'] =
        $this->info['script']->attr_transform_post['type'] =
            new HTMLPurifier_AttrTransform_ScriptRequired();
    }
}
class HTMLPurifier_HTMLModule_StyleAttribute extends HTMLPurifier_HTMLModule
{
    public $name = 'StyleAttribute';
    public $attr_collections = array(
        'Style' => array('style' => false),  
        'Core' => array(0 => array('Style'))
    );
    public function __construct() {
        $this->attr_collections['Style']['style'] = new HTMLPurifier_AttrDef_CSS();
    }
}
class HTMLPurifier_HTMLModule_Tables extends HTMLPurifier_HTMLModule
{
    public $name = 'Tables';
    public function __construct() {
        $this->addElement('caption', false, 'Inline', 'Common');
        $this->addElement('table', 'Block', 
            new HTMLPurifier_ChildDef_Table(),  'Common', 
            array(
                'border' => 'Pixels',
                'cellpadding' => 'Length',
                'cellspacing' => 'Length',
                'frame' => 'Enum#void,above,below,hsides,lhs,rhs,vsides,box,border',
                'rules' => 'Enum#none,groups,rows,cols,all',
                'summary' => 'Text',
                'width' => 'Length'
            )
        );
        $cell_align = array(
            'align' => 'Enum#left,center,right,justify,char',
            'charoff' => 'Length',
            'valign' => 'Enum#top,middle,bottom,baseline',
        );
        $cell_t = array_merge(
            array(
                'abbr'    => 'Text',
                'colspan' => 'Number',
                'rowspan' => 'Number',
            ),
            $cell_align
        );
        $this->addElement('td', false, 'Flow', 'Common', $cell_t);
        $this->addElement('th', false, 'Flow', 'Common', $cell_t);
        $this->addElement('tr', false, 'Required: td | th', 'Common', $cell_align);
        $cell_col = array_merge(
            array(
                'span'  => 'Number',
                'width' => 'MultiLength',
            ),
            $cell_align
        );
        $this->addElement('col',      false, 'Empty',         'Common', $cell_col);
        $this->addElement('colgroup', false, 'Optional: col', 'Common', $cell_col);
        $this->addElement('tbody', false, 'Required: tr', 'Common', $cell_align);
        $this->addElement('thead', false, 'Required: tr', 'Common', $cell_align);
        $this->addElement('tfoot', false, 'Required: tr', 'Common', $cell_align);
    }
}
class HTMLPurifier_HTMLModule_Target extends HTMLPurifier_HTMLModule
{
    public $name = 'Target';
    public function __construct() {
        $elements = array('a');
        foreach ($elements as $name) {
            $e = $this->addBlankElement($name);
            $e->attr = array(
                'target' => new HTMLPurifier_AttrDef_HTML_FrameTarget()
            );
        }
    }
}
class HTMLPurifier_HTMLModule_Text extends HTMLPurifier_HTMLModule
{
    public $name = 'Text';
    public $content_sets = array(
        'Flow' => 'Heading | Block | Inline'
    );
    public function __construct() {
        $this->addElement('abbr',    'Inline', 'Inline', 'Common');
        $this->addElement('acronym', 'Inline', 'Inline', 'Common');
        $this->addElement('cite',    'Inline', 'Inline', 'Common');
        $this->addElement('code',    'Inline', 'Inline', 'Common');
        $this->addElement('dfn',     'Inline', 'Inline', 'Common');
        $this->addElement('em',      'Inline', 'Inline', 'Common');
        $this->addElement('kbd',     'Inline', 'Inline', 'Common');
        $this->addElement('q',       'Inline', 'Inline', 'Common', array('cite' => 'URI'));
        $this->addElement('samp',    'Inline', 'Inline', 'Common');
        $this->addElement('strong',  'Inline', 'Inline', 'Common');
        $this->addElement('var',     'Inline', 'Inline', 'Common');
        $this->addElement('span', 'Inline', 'Inline', 'Common');
        $this->addElement('br',   'Inline', 'Empty',  'Core');
        $this->addElement('address',     'Block', 'Inline', 'Common');
        $this->addElement('blockquote',  'Block', 'Optional: Heading | Block | List', 'Common', array('cite' => 'URI') );
        $pre = $this->addElement('pre', 'Block', 'Inline', 'Common');
        $pre->excludes = $this->makeLookup(
            'img', 'big', 'small', 'object', 'applet', 'font', 'basefont' );
        $this->addElement('h1', 'Heading', 'Inline', 'Common');
        $this->addElement('h2', 'Heading', 'Inline', 'Common');
        $this->addElement('h3', 'Heading', 'Inline', 'Common');
        $this->addElement('h4', 'Heading', 'Inline', 'Common');
        $this->addElement('h5', 'Heading', 'Inline', 'Common');
        $this->addElement('h6', 'Heading', 'Inline', 'Common');
        $this->addElement('p', 'Block', 'Inline', 'Common');
        $this->addElement('div', 'Block', 'Flow', 'Common');
    }
}
class HTMLPurifier_HTMLModule_Tidy extends HTMLPurifier_HTMLModule
{
    public $levels = array(0 => 'none', 'light', 'medium', 'heavy');
    public $defaultLevel = null;
    public $fixesForLevel = array(
        'light'  => array(),
        'medium' => array(),
        'heavy'  => array()
    );
    public function construct($config) {
        $fixes = $this->makeFixes();
        $this->makeFixesForLevel($fixes);
        $level = $config->get('HTML', 'TidyLevel');
        $fixes_lookup = $this->getFixesForLevel($level);
        $add_fixes    = $config->get('HTML', 'TidyAdd');
        $remove_fixes = $config->get('HTML', 'TidyRemove');
        foreach ($fixes as $name => $fix) {
            if (
                isset($remove_fixes[$name]) ||
                (!isset($add_fixes[$name]) && !isset($fixes_lookup[$name]))
            ) {
                unset($fixes[$name]);
            }
        }
        $this->populate($fixes);
    }
    public function getFixesForLevel($level) {
        if ($level == $this->levels[0]) {
            return array();
        }
        $activated_levels = array();
        for ($i = 1, $c = count($this->levels); $i < $c; $i++) {
            $activated_levels[] = $this->levels[$i];
            if ($this->levels[$i] == $level) break;
        }
        if ($i == $c) {
            trigger_error(
                'Tidy level ' . htmlspecialchars($level) . ' not recognized',
                E_USER_WARNING
            );
            return array();
        }
        $ret = array();
        foreach ($activated_levels as $level) {
            foreach ($this->fixesForLevel[$level] as $fix) {
                $ret[$fix] = true;
            }
        }
        return $ret;
    }
    public function makeFixesForLevel($fixes) {
        if (!isset($this->defaultLevel)) return;
        if (!isset($this->fixesForLevel[$this->defaultLevel])) {
            trigger_error(
                'Default level ' . $this->defaultLevel . ' does not exist',
                E_USER_ERROR
            );
            return;
        }
        $this->fixesForLevel[$this->defaultLevel] = array_keys($fixes);
    }
    public function populate($fixes) {
        foreach ($fixes as $name => $fix) {
            list($type, $params) = $this->getFixType($name);
            switch ($type) {
                case 'attr_transform_pre':
                case 'attr_transform_post':
                    $attr = $params['attr'];
                    if (isset($params['element'])) {
                        $element = $params['element'];
                        if (empty($this->info[$element])) {
                            $e = $this->addBlankElement($element);
                        } else {
                            $e = $this->info[$element];
                        }
                    } else {
                        $type = "info_$type";
                        $e = $this;
                    }
                    $f =& $e->$type;
                    $f[$attr] = $fix;
                    break;
                case 'tag_transform':
                    $this->info_tag_transform[$params['element']] = $fix;
                    break;
                case 'child':
                case 'content_model_type':
                    $element = $params['element'];
                    if (empty($this->info[$element])) {
                        $e = $this->addBlankElement($element);
                    } else {
                        $e = $this->info[$element];
                    }
                    $e->$type = $fix;
                    break;
                default:
                    trigger_error("Fix type $type not supported", E_USER_ERROR);
                    break;
            }
        }
    }
    public function getFixType($name) {
        $property = $attr = null;
        if (strpos($name, '#') !== false) list($name, $property) = explode('#', $name);
        if (strpos($name, '@') !== false) list($name, $attr)     = explode('@', $name);
        $params = array();
        if ($name !== '')    $params['element'] = $name;
        if (!is_null($attr)) $params['attr'] = $attr;
        if (!is_null($attr)) {
            if (is_null($property)) $property = 'pre';
            $type = 'attr_transform_' . $property;
            return array($type, $params);
        }
        if (is_null($property)) {
            return array('tag_transform', $params);
        }
        return array($property, $params);
    }
    public function makeFixes() {}
}
class HTMLPurifier_HTMLModule_XMLCommonAttributes extends HTMLPurifier_HTMLModule
{
    public $name = 'XMLCommonAttributes';
    public $attr_collections = array(
        'Lang' => array(
            'xml:lang' => 'LanguageCode',
        )
    );
}
class HTMLPurifier_HTMLModule_Tidy_Proprietary extends HTMLPurifier_HTMLModule_Tidy
{
    public $name = 'Tidy_Proprietary';
    public $defaultLevel = 'light';
    public function makeFixes() {
        return array();
    }
}
class HTMLPurifier_HTMLModule_Tidy_XHTMLAndHTML4 extends HTMLPurifier_HTMLModule_Tidy
{
    public function makeFixes() {
        $r = array();
        $r['font']   = new HTMLPurifier_TagTransform_Font();
        $r['menu']   = new HTMLPurifier_TagTransform_Simple('ul');
        $r['dir']    = new HTMLPurifier_TagTransform_Simple('ul');
        $r['center'] = new HTMLPurifier_TagTransform_Simple('div',  'text-align:center;');
        $r['u']      = new HTMLPurifier_TagTransform_Simple('span', 'text-decoration:underline;');
        $r['s']      = new HTMLPurifier_TagTransform_Simple('span', 'text-decoration:line-through;');
        $r['strike'] = new HTMLPurifier_TagTransform_Simple('span', 'text-decoration:line-through;');
        $r['caption@align'] = 
            new HTMLPurifier_AttrTransform_EnumToCSS('align', array(
                'left'   => 'text-align:left;',
                'right'  => 'text-align:right;',
                'top'    => 'caption-side:top;',
                'bottom' => 'caption-side:bottom;'  
            ));
        $r['img@align'] =
            new HTMLPurifier_AttrTransform_EnumToCSS('align', array(
                'left'   => 'float:left;',
                'right'  => 'float:right;',
                'top'    => 'vertical-align:top;',
                'middle' => 'vertical-align:middle;',
                'bottom' => 'vertical-align:baseline;',
            ));
        $r['table@align'] =
            new HTMLPurifier_AttrTransform_EnumToCSS('align', array(
                'left'   => 'float:left;',
                'center' => 'margin-left:auto;margin-right:auto;',
                'right'  => 'float:right;'
            ));
        $r['hr@align'] =
            new HTMLPurifier_AttrTransform_EnumToCSS('align', array(
                'left'   => 'margin-left:0;margin-right:auto;text-align:left;',
                'center' => 'margin-left:auto;margin-right:auto;text-align:center;',
                'right'  => 'margin-left:auto;margin-right:0;text-align:right;'
            ));
            $align_lookup = array();
            $align_values = array('left', 'right', 'center', 'justify');
            foreach ($align_values as $v) $align_lookup[$v] = "text-align:$v;";
        $r['h1@align'] =
        $r['h2@align'] =
        $r['h3@align'] =
        $r['h4@align'] =
        $r['h5@align'] =
        $r['h6@align'] =
        $r['p@align']  =
        $r['div@align'] = 
            new HTMLPurifier_AttrTransform_EnumToCSS('align', $align_lookup);
        $r['table@bgcolor'] =
        $r['td@bgcolor'] =
        $r['th@bgcolor'] =
            new HTMLPurifier_AttrTransform_BgColor();
        $r['img@border'] = new HTMLPurifier_AttrTransform_Border();
        $r['br@clear'] =
            new HTMLPurifier_AttrTransform_EnumToCSS('clear', array(
                'left'  => 'clear:left;',
                'right' => 'clear:right;',
                'all'   => 'clear:both;',
                'none'  => 'clear:none;',
            ));
        $r['td@height'] = 
        $r['th@height'] =
            new HTMLPurifier_AttrTransform_Length('height');
        $r['img@hspace'] = new HTMLPurifier_AttrTransform_ImgSpace('hspace');
        $r['img@name'] = 
        $r['a@name'] = new HTMLPurifier_AttrTransform_Name();
        $r['hr@noshade'] =
            new HTMLPurifier_AttrTransform_BoolToCSS(
                'noshade',
                'color:#808080;background-color:#808080;border:0;'
            );
        $r['td@nowrap'] = 
        $r['th@nowrap'] =
            new HTMLPurifier_AttrTransform_BoolToCSS(
                'nowrap',
                'white-space:nowrap;'
            );
        $r['hr@size'] = new HTMLPurifier_AttrTransform_Length('size', 'height');
            $ul_types = array(
                'disc'   => 'list-style-type:disc;',
                'square' => 'list-style-type:square;',
                'circle' => 'list-style-type:circle;'
            );
            $ol_types = array(
                '1'   => 'list-style-type:decimal;',
                'i'   => 'list-style-type:lower-roman;',
                'I'   => 'list-style-type:upper-roman;',
                'a'   => 'list-style-type:lower-alpha;',
                'A'   => 'list-style-type:upper-alpha;'
            );
            $li_types = $ul_types + $ol_types;
        $r['ul@type'] = new HTMLPurifier_AttrTransform_EnumToCSS('type', $ul_types);
        $r['ol@type'] = new HTMLPurifier_AttrTransform_EnumToCSS('type', $ol_types, true);
        $r['li@type'] = new HTMLPurifier_AttrTransform_EnumToCSS('type', $li_types, true);
        $r['img@vspace'] = new HTMLPurifier_AttrTransform_ImgSpace('vspace');
        $r['td@width'] =
        $r['th@width'] = 
        $r['hr@width'] = new HTMLPurifier_AttrTransform_Length('width');
        return $r;
    }
}
class HTMLPurifier_HTMLModule_Tidy_Strict extends HTMLPurifier_HTMLModule_Tidy_XHTMLAndHTML4
{
    public $name = 'Tidy_Strict';
    public $defaultLevel = 'light';
    public function makeFixes() {
        $r = parent::makeFixes();
        $r['blockquote#content_model_type'] = 'strictblockquote';
        return $r;
    }
    public $defines_child_def = true;
    public function getChildDef($def) {
        if ($def->content_model_type != 'strictblockquote') return parent::getChildDef($def);
        return new HTMLPurifier_ChildDef_StrictBlockquote($def->content_model);
    }
}
class HTMLPurifier_HTMLModule_Tidy_Transitional extends HTMLPurifier_HTMLModule_Tidy_XHTMLAndHTML4
{
    public $name = 'Tidy_Transitional';
    public $defaultLevel = 'heavy';
}
class HTMLPurifier_HTMLModule_Tidy_XHTML extends HTMLPurifier_HTMLModule_Tidy
{
    public $name = 'Tidy_XHTML';
    public $defaultLevel = 'medium';
    public function makeFixes() {
        $r = array();
        $r['@lang'] = new HTMLPurifier_AttrTransform_Lang();
        return $r;
    }
}
class HTMLPurifier_Injector_AutoParagraph extends HTMLPurifier_Injector
{
    public $name = 'AutoParagraph';
    public $needed = array('p');
    private function _pStart() {
        $par = new HTMLPurifier_Token_Start('p');
        $par->armor['MakeWellFormed_TagClosedError'] = true;
        return $par;
    }
    public function handleText(&$token) {
        $text = $token->data;
        if (empty($this->currentNesting)) {
            if (!$this->allowsElement('p')) return;
            $token = array($this->_pStart());
            $this->_splitText($text, $token);
        } elseif ($this->currentNesting[count($this->currentNesting)-1]->name == 'p') {
            $token = array();
            $this->_splitText($text, $token);
        } elseif ($this->allowsElement('p')) {
            if (strpos($text, "\n\n") !== false) {
                $token = array($this->_pStart());
                $this->_splitText($text, $token);
            } else {
                $ok = false;
                $nesting = 0;
                for ($i = $this->inputIndex + 1; isset($this->inputTokens[$i]); $i++) {
                    if ($this->inputTokens[$i] instanceof HTMLPurifier_Token_Start){
                        if (!$this->_isInline($this->inputTokens[$i])) {
                            $ok = false;
                            break;
                        }
                        $nesting++;
                    }
                    if ($this->inputTokens[$i] instanceof HTMLPurifier_Token_End) {
                        if ($nesting <= 0) break;
                        $nesting--;
                    }
                    if ($this->inputTokens[$i] instanceof HTMLPurifier_Token_Text) {
                        if (strpos($this->inputTokens[$i]->data, "\n\n") !== false) {
                            $ok = true;
                            break;
                        }
                    }
                }
                if ($ok) {
                    $token = array($this->_pStart(), $token);
                }
            }
        }
    }
    public function handleElement(&$token) {
        if (!empty($this->currentNesting)) {
            if ($this->allowsElement('p')) {
                if ($token->name == 'p') return;
                if (!$this->_isInline($token)) return;
                $prev = $this->inputTokens[$this->inputIndex - 1];
                if (!$prev instanceof HTMLPurifier_Token_Start) {
                    if (
                        $prev->name == 'p' && $prev instanceof HTMLPurifier_Token_End &&
                        $this->_isInline($token)
                    ) {
                        $token = array($this->_pStart(), $token);
                    }
                    return;
                }
                $ok = false;
                $j = 1;  
                for ($i = $this->inputIndex; isset($this->inputTokens[$i]); $i++) {
                    if ($this->inputTokens[$i] instanceof HTMLPurifier_Token_Start) $j++;
                    if ($this->inputTokens[$i] instanceof HTMLPurifier_Token_End) $j--;
                    if ($this->inputTokens[$i] instanceof HTMLPurifier_Token_Text) {
                        if (strpos($this->inputTokens[$i]->data, "\n\n") !== false) {
                            $ok = true;
                            break;
                        }
                    }
                    if ($j <= 0) break;
                }
                if ($ok) {
                    $token = array($this->_pStart(), $token);
                }
            }
            return;
        }
        if (!$this->_isInline($token)) return;
        $token = array($this->_pStart(), $token);
    }
    private function _splitText($data, &$result) {
        $raw_paragraphs = explode("\n\n", $data);
        $paragraphs = array();
        $needs_start = false;
        $needs_end   = false;
        $c = count($raw_paragraphs);
        if ($c == 1) {
            $result[] = new HTMLPurifier_Token_Text($data);
            return;
        }
        for ($i = 0; $i < $c; $i++) {
            $par = $raw_paragraphs[$i];
            if (trim($par) !== '') {
                $paragraphs[] = $par;
                continue;
            }
            if ($i == 0 && empty($result)) {
                $result[] = new HTMLPurifier_Token_End('p');
                $needs_start = true;
            } elseif ($i + 1 == $c) {
                $needs_end = true;
            }
        }
        if (empty($paragraphs)) {
            return;
        }
        if ($needs_start) $result[] = $this->_pStart();
        foreach ($paragraphs as $par) {
            $result[] = new HTMLPurifier_Token_Text($par);
            $result[] = new HTMLPurifier_Token_End('p');
            $result[] = $this->_pStart();
        }
        array_pop($result);
        $remove_paragraph_end = true;
        if (!$needs_end) {
            for ($i = $this->inputIndex + 1; isset($this->inputTokens[$i]); $i++) {
                if ($this->inputTokens[$i] instanceof HTMLPurifier_Token_Start || $this->inputTokens[$i] instanceof HTMLPurifier_Token_Empty) {
                    $remove_paragraph_end = $this->_isInline($this->inputTokens[$i]);
                }
                if ($this->inputTokens[$i] instanceof HTMLPurifier_Token_Text && !$this->inputTokens[$i]->is_whitespace) break;
                if ($this->inputTokens[$i] instanceof HTMLPurifier_Token_End) break;
            }
        } else {
            $remove_paragraph_end = false;
        }
        if ($remove_paragraph_end) {
            array_pop($result);
        }
    }
    private function _isInline($token) {
        return isset($this->htmlDefinition->info['p']->child->elements[$token->name]);
    }
}
class HTMLPurifier_Injector_Linkify extends HTMLPurifier_Injector
{
    public $name = 'Linkify';
    public $needed = array('a' => array('href'));
    public function handleText(&$token) {
        if (!$this->allowsElement('a')) return;
        if (strpos($token->data, '://') === false) {
            return;
        }
        $bits = preg_split('#((?:https?|ftp)://[^\s\'"<>()]+)#S', $token->data, -1, PREG_SPLIT_DELIM_CAPTURE);
        $token = array();
        for ($i = 0, $c = count($bits), $l = false; $i < $c; $i++, $l = !$l) {
            if (!$l) {
                if ($bits[$i] === '') continue;
                $token[] = new HTMLPurifier_Token_Text($bits[$i]);
            } else {
                $token[] = new HTMLPurifier_Token_Start('a', array('href' => $bits[$i]));
                $token[] = new HTMLPurifier_Token_Text($bits[$i]);
                $token[] = new HTMLPurifier_Token_End('a');
            }
        }
    }
}
class HTMLPurifier_Injector_PurifierLinkify extends HTMLPurifier_Injector
{
    public $name = 'PurifierLinkify';
    public $docURL;
    public $needed = array('a' => array('href'));
    public function prepare($config, $context) {
        $this->docURL = $config->get('AutoFormatParam', 'PurifierLinkifyDocURL');
        return parent::prepare($config, $context);
    }
    public function handleText(&$token) {
        if (!$this->allowsElement('a')) return;
        if (strpos($token->data, '%') === false) return;
        $bits = preg_split('#%([a-z0-9]+\.[a-z0-9]+)#Si', $token->data, -1, PREG_SPLIT_DELIM_CAPTURE);
        $token = array();
        for ($i = 0, $c = count($bits), $l = false; $i < $c; $i++, $l = !$l) {
            if (!$l) {
                if ($bits[$i] === '') continue;
                $token[] = new HTMLPurifier_Token_Text($bits[$i]);
            } else {
                $token[] = new HTMLPurifier_Token_Start('a',
                    array('href' => str_replace('%s', $bits[$i], $this->docURL)));
                $token[] = new HTMLPurifier_Token_Text('%' . $bits[$i]);
                $token[] = new HTMLPurifier_Token_End('a');
            }
        }
    }
}
class HTMLPurifier_Lexer_DOMLex extends HTMLPurifier_Lexer
{
    private $factory;
    public function __construct() {
        parent::__construct();
        $this->factory = new HTMLPurifier_TokenFactory();
    }
    public function tokenizeHTML($html, $config, $context) {
        $html = $this->normalize($html, $config, $context);
        if ($config->get('Core', 'AggressivelyFixLt')) {
            $char = '[^a-z!\/]';
            $comment = "/<!--(.*?)(-->|\z)/is";
            $html = preg_replace_callback($comment, array($this, 'callbackArmorCommentEntities'), $html);
            $html = preg_replace("/<($char)/i", '&lt;\\1', $html);
            $html = preg_replace_callback($comment, array($this, 'callbackUndoCommentSubst'), $html);  
        }
        $html = $this->wrapHTML($html, $config, $context);
        $doc = new DOMDocument();
        $doc->encoding = 'UTF-8';  
        set_error_handler(array($this, 'muteErrorHandler'));
        $doc->loadHTML($html);
        restore_error_handler();
        $tokens = array();
        $this->tokenizeDOM(
            $doc->getElementsByTagName('html')->item(0)->  
                  getElementsByTagName('body')->item(0)->  
                  getElementsByTagName('div')->item(0)     
            , $tokens);
        return $tokens;
    }
    protected function tokenizeDOM($node, &$tokens, $collect = false) {
        if ($node->nodeType === XML_TEXT_NODE) {
            $tokens[] = $this->factory->createText($node->data);
            return;
        } elseif ($node->nodeType === XML_CDATA_SECTION_NODE) {
            $last = end($tokens);
            $data = $node->data;
            if ($last instanceof HTMLPurifier_Token_Start && ($last->name == 'script' || $last->name == 'style')) {
                $new_data = trim($data);
                if (substr($new_data, 0, 4) === '<!--') {
                    $data = substr($new_data, 4);
                    if (substr($data, -3) === '-->') {
                        $data = substr($data, 0, -3);
                    } else {
                    }
                }
            }
            $tokens[] = $this->factory->createText($this->parseData($data));
            return;
        } elseif ($node->nodeType === XML_COMMENT_NODE) {
            $tokens[] = $this->factory->createComment($node->data);
            return;
        } elseif (
            $node->nodeType !== XML_ELEMENT_NODE
        ) {
            return;
        }
        $attr = $node->hasAttributes() ?
            $this->transformAttrToAssoc($node->attributes) :
            array();
        if (!$node->childNodes->length) {
            if ($collect) {
                $tokens[] = $this->factory->createEmpty($node->tagName, $attr);
            }
        } else {
            if ($collect) {  
                $tokens[] = $this->factory->createStart(
                    $tag_name = $node->tagName,  
                    $attr
                );
            }
            foreach ($node->childNodes as $node) {
                $this->tokenizeDOM($node, $tokens, true);
            }
            if ($collect) {
                $tokens[] = $this->factory->createEnd($tag_name);
            }
        }
    }
    protected function transformAttrToAssoc($node_map) {
        if ($node_map->length === 0) return array();
        $array = array();
        foreach ($node_map as $attr) {
            $array[$attr->name] = $attr->value;
        }
        return $array;
    }
    public function muteErrorHandler($errno, $errstr) {}
    public function callbackUndoCommentSubst($matches) {
        return '<!--' . strtr($matches[1], array('&amp;'=>'&','&lt;'=>'<')) . $matches[2];
    }
    public function callbackArmorCommentEntities($matches) {
        return '<!--' . str_replace('&', '&amp;', $matches[1]) . $matches[2];
    }
    protected function wrapHTML($html, $config, $context) {
        $def = $config->getDefinition('HTML');
        $ret = '';
        if (!empty($def->doctype->dtdPublic) || !empty($def->doctype->dtdSystem)) {
            $ret .= '<!DOCTYPE html ';
            if (!empty($def->doctype->dtdPublic)) $ret .= 'PUBLIC "' . $def->doctype->dtdPublic . '" ';
            if (!empty($def->doctype->dtdSystem)) $ret .= '"' . $def->doctype->dtdSystem . '" ';
            $ret .= '>';
        }
        $ret .= '<html><head>';
        $ret .= '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
        $ret .= '</head><body><div>'.$html.'</div></body></html>';
        return $ret;
    }
}
class HTMLPurifier_Lexer_DirectLex extends HTMLPurifier_Lexer
{
    protected $_whitespace = "\x20\x09\x0D\x0A";
    protected function scriptCallback($matches) {
        return $matches[1] . htmlspecialchars($matches[2], ENT_COMPAT, 'UTF-8') . $matches[3];
    }
    public function tokenizeHTML($html, $config, $context) {
        if ($config->get('HTML', 'Trusted')) {
            $html = preg_replace_callback('#(<script[^>]*>)(\s*[^<].+?)(</script>)#si',
                array($this, 'scriptCallback'), $html);
        }
        $html = $this->normalize($html, $config, $context);
        $cursor = 0;  
        $inside_tag = false;  
        $array = array();  
        $maintain_line_numbers = $config->get('Core', 'MaintainLineNumbers');
        if ($maintain_line_numbers === null) {
            $maintain_line_numbers = $config->get('Core', 'CollectErrors');
        }
        if ($maintain_line_numbers) $current_line = 1;
        else $current_line = false;
        $context->register('CurrentLine', $current_line);
        $nl = "\n";
        $synchronize_interval = $config->get('Core', 'DirectLexLineNumberSyncInterval'); 
        $e = false;
        if ($config->get('Core', 'CollectErrors')) {
            $e =& $context->get('ErrorCollector');
        }
        $loops = 0;
        while(++$loops) {
            if (
                $maintain_line_numbers &&  
                $synchronize_interval &&   
                $cursor > 0 &&             
                $loops % $synchronize_interval === 0  
            ) {
                $current_line = 1 + $this->substrCount($html, $nl, 0, $cursor);
            }
            $position_next_lt = strpos($html, '<', $cursor);
            $position_next_gt = strpos($html, '>', $cursor);
            if ($position_next_lt === $cursor) {
                $inside_tag = true;
                $cursor++;
            }
            if (!$inside_tag && $position_next_lt !== false) {
                $token = new
                    HTMLPurifier_Token_Text(
                        $this->parseData(
                            substr(
                                $html, $cursor, $position_next_lt - $cursor
                            )
                        )
                    );
                if ($maintain_line_numbers) {
                    $token->line = $current_line;
                    $current_line += $this->substrCount($html, $nl, $cursor, $position_next_lt - $cursor);
                }
                $array[] = $token;
                $cursor  = $position_next_lt + 1;
                $inside_tag = true;
                continue;
            } elseif (!$inside_tag) {
                if ($cursor === strlen($html)) break;
                $token = new
                    HTMLPurifier_Token_Text(
                        $this->parseData(
                            substr(
                                $html, $cursor
                            )
                        )
                    );
                if ($maintain_line_numbers) $token->line = $current_line;
                $array[] = $token;
                break;
            } elseif ($inside_tag && $position_next_gt !== false) {
                $strlen_segment = $position_next_gt - $cursor;
                if ($strlen_segment < 1) {
                    $token = new HTMLPurifier_Token_Text('<');
                    $cursor++;
                    continue;
                }
                $segment = substr($html, $cursor, $strlen_segment);
                if ($segment === false) {
                    break;
                }
                if (
                    substr($segment, 0, 3) === '!--'
                ) {
                    $position_comment_end = strpos($html, '-->', $cursor);
                    if ($position_comment_end === false) {
                        if ($e) $e->send(E_WARNING, 'Lexer: Unclosed comment');
                        $position_comment_end = strlen($html);
                        $end = true;
                    } else {
                        $end = false;
                    }
                    $strlen_segment = $position_comment_end - $cursor;
                    $segment = substr($html, $cursor, $strlen_segment);
                    $token = new
                        HTMLPurifier_Token_Comment(
                            substr(
                                $segment, 3, $strlen_segment - 3
                            )
                        );
                    if ($maintain_line_numbers) {
                        $token->line = $current_line;
                        $current_line += $this->substrCount($html, $nl, $cursor, $strlen_segment);
                    }
                    $array[] = $token;
                    $cursor = $end ? $position_comment_end : $position_comment_end + 3;
                    $inside_tag = false;
                    continue;
                }
                $is_end_tag = (strpos($segment,'/') === 0);
                if ($is_end_tag) {
                    $type = substr($segment, 1);
                    $token = new HTMLPurifier_Token_End($type);
                    if ($maintain_line_numbers) {
                        $token->line = $current_line;
                        $current_line += $this->substrCount($html, $nl, $cursor, $position_next_gt - $cursor);
                    }
                    $array[] = $token;
                    $inside_tag = false;
                    $cursor = $position_next_gt + 1;
                    continue;
                }
                if (!ctype_alpha($segment[0])) {
                    if ($e) $e->send(E_NOTICE, 'Lexer: Unescaped lt');
                    $token = new
                        HTMLPurifier_Token_Text(
                            '<' .
                            $this->parseData(
                                $segment
                            ) . 
                            '>'
                        );
                    if ($maintain_line_numbers) {
                        $token->line = $current_line;
                        $current_line += $this->substrCount($html, $nl, $cursor, $position_next_gt - $cursor);
                    }
                    $array[] = $token;
                    $cursor = $position_next_gt + 1;
                    $inside_tag = false;
                    continue;
                }
                $is_self_closing = (strrpos($segment,'/') === $strlen_segment-1);
                if ($is_self_closing) {
                    $strlen_segment--;
                    $segment = substr($segment, 0, $strlen_segment);
                }
                $position_first_space = strcspn($segment, $this->_whitespace);
                if ($position_first_space >= $strlen_segment) {
                    if ($is_self_closing) {
                        $token = new HTMLPurifier_Token_Empty($segment);
                    } else {
                        $token = new HTMLPurifier_Token_Start($segment);
                    }
                    if ($maintain_line_numbers) {
                        $token->line = $current_line;
                        $current_line += $this->substrCount($html, $nl, $cursor, $position_next_gt - $cursor);
                    }
                    $array[] = $token;
                    $inside_tag = false;
                    $cursor = $position_next_gt + 1;
                    continue;
                }
                $type = substr($segment, 0, $position_first_space);
                $attribute_string =
                    trim(
                        substr(
                            $segment, $position_first_space
                        )
                    );
                if ($attribute_string) {
                    $attr = $this->parseAttributeString(
                                    $attribute_string
                                  , $config, $context
                              );
                } else {
                    $attr = array();
                }
                if ($is_self_closing) {
                    $token = new HTMLPurifier_Token_Empty($type, $attr);
                } else {
                    $token = new HTMLPurifier_Token_Start($type, $attr);
                }
                if ($maintain_line_numbers) {
                    $token->line = $current_line;
                    $current_line += $this->substrCount($html, $nl, $cursor, $position_next_gt - $cursor);
                }
                $array[] = $token;
                $cursor = $position_next_gt + 1;
                $inside_tag = false;
                continue;
            } else {
                if ($e) $e->send(E_WARNING, 'Lexer: Missing gt');
                $token = new
                    HTMLPurifier_Token_Text(
                        '<' .
                        $this->parseData(
                            substr($html, $cursor)
                        )
                    );
                if ($maintain_line_numbers) $token->line = $current_line;
                $array[] = $token;
                break;
            }
            break;
        }
        $context->destroy('CurrentLine');
        return $array;
    }
    protected function substrCount($haystack, $needle, $offset, $length) {
        static $oldVersion;
        if ($oldVersion === null) {
            $oldVersion = version_compare(PHP_VERSION, '5.1', '<');
        }
        if ($oldVersion) {
            $haystack = substr($haystack, $offset, $length);
            return substr_count($haystack, $needle);
        } else {
            return substr_count($haystack, $needle, $offset, $length);
        }
    }
    public function parseAttributeString($string, $config, $context) {
        $string = (string) $string;  
        if ($string == '') return array();  
        $e = false;
        if ($config->get('Core', 'CollectErrors')) {
            $e =& $context->get('ErrorCollector');
        }
        $num_equal = substr_count($string, '=');
        $has_space = strpos($string, ' ');
        if ($num_equal === 0 && !$has_space) {
            return array($string => $string);
        } elseif ($num_equal === 1 && !$has_space) {
            list($key, $quoted_value) = explode('=', $string);
            $quoted_value = trim($quoted_value);
            if (!$key) {
                if ($e) $e->send(E_ERROR, 'Lexer: Missing attribute key');
                return array();
            }
            if (!$quoted_value) return array($key => '');
            $first_char = @$quoted_value[0];
            $last_char  = @$quoted_value[strlen($quoted_value)-1];
            $same_quote = ($first_char == $last_char);
            $open_quote = ($first_char == '"' || $first_char == "'");
            if ( $same_quote && $open_quote) {
                $value = substr($quoted_value, 1, strlen($quoted_value) - 2);
            } else {
                if ($open_quote) {
                    if ($e) $e->send(E_ERROR, 'Lexer: Missing end quote');
                    $value = substr($quoted_value, 1);
                } else {
                    $value = $quoted_value;
                }
            }
            if ($value === false) $value = '';
            return array($key => $value);
        }
        $array  = array();  
        $cursor = 0;  
        $size   = strlen($string);  
        $string .= ' ';
        while(true) {
            if ($cursor >= $size) {
                break;
            }
            $cursor += ($value = strspn($string, $this->_whitespace, $cursor));
            $key_begin = $cursor; 
            $cursor += strcspn($string, $this->_whitespace . '=', $cursor);
            $key_end = $cursor;  
            $key = substr($string, $key_begin, $key_end - $key_begin);
            if (!$key) {
                if ($e) $e->send(E_ERROR, 'Lexer: Missing attribute key');
                $cursor += strcspn($string, $this->_whitespace, $cursor + 1);  
                continue;  
            }
            $cursor += strspn($string, $this->_whitespace, $cursor);
            if ($cursor >= $size) {
                $array[$key] = $key;
                break;
            }
            $first_char = @$string[$cursor];
            if ($first_char == '=') {
                $cursor++;
                $cursor += strspn($string, $this->_whitespace, $cursor);
                if ($cursor === false) {
                    $array[$key] = '';
                    break;
                }
                $char = @$string[$cursor];
                if ($char == '"' || $char == "'") {
                    $cursor++;
                    $value_begin = $cursor;
                    $cursor = strpos($string, $char, $cursor);
                    $value_end = $cursor;
                } else {
                    $value_begin = $cursor;
                    $cursor += strcspn($string, $this->_whitespace, $cursor);
                    $value_end = $cursor;
                }
                if ($cursor === false) {
                    $cursor = $size;
                    $value_end = $cursor;
                }
                $value = substr($string, $value_begin, $value_end - $value_begin);
                if ($value === false) $value = '';
                $array[$key] = $this->parseData($value);
                $cursor++;
            } else {
                if ($key !== '') {
                    $array[$key] = $key;
                } else {
                    if ($e) $e->send(E_ERROR, 'Lexer: Missing attribute key');
                }
            }
        }
        return $array;
    }
}
abstract class HTMLPurifier_Strategy_Composite extends HTMLPurifier_Strategy
{
    protected $strategies = array();
    abstract public function __construct();
    public function execute($tokens, $config, $context) {
        foreach ($this->strategies as $strategy) {
            $tokens = $strategy->execute($tokens, $config, $context);
        }
        return $tokens;
    }
}
class HTMLPurifier_Strategy_Core extends HTMLPurifier_Strategy_Composite
{
    public function __construct() {
        $this->strategies[] = new HTMLPurifier_Strategy_RemoveForeignElements();
        $this->strategies[] = new HTMLPurifier_Strategy_MakeWellFormed();
        $this->strategies[] = new HTMLPurifier_Strategy_FixNesting();
        $this->strategies[] = new HTMLPurifier_Strategy_ValidateAttributes();
    }
}
class HTMLPurifier_Strategy_FixNesting extends HTMLPurifier_Strategy
{
    public function execute($tokens, $config, $context) {
        //####################################################################//
        $definition = $config->getHTMLDefinition();
        $parent_name = $definition->info_parent;
        array_unshift($tokens, new HTMLPurifier_Token_Start($parent_name));
        $tokens[] = new HTMLPurifier_Token_End($parent_name);
        $is_inline = $definition->info_parent_def->descendants_are_inline;
        $context->register('IsInline', $is_inline);
        $e =& $context->get('ErrorCollector', true);
        //####################################################################//
        $stack = array();
        $exclude_stack = array();
        $start_token = false;
        $context->register('CurrentToken', $start_token);
        //####################################################################//
        for ($i = 0, $size = count($tokens) ; $i < $size; ) {
            $child_tokens = array();
            for ($j = $i, $depth = 0; ; $j++) {
                if ($tokens[$j] instanceof HTMLPurifier_Token_Start) {
                    $depth++;
                    if ($depth == 1) continue;
                } elseif ($tokens[$j] instanceof HTMLPurifier_Token_End) {
                    $depth--;
                    if ($depth == 0) break;
                }
                $child_tokens[] = $tokens[$j];
            }
            $start_token = $tokens[$i];  
            if ($count = count($stack)) {
                $parent_index = $stack[$count-1];
                $parent_name  = $tokens[$parent_index]->name;
                if ($parent_index == 0) {
                    $parent_def   = $definition->info_parent_def;
                } else {
                    $parent_def   = $definition->info[$parent_name];
                }
            } else {
                $parent_index = $parent_name = $parent_def = null;
            }
            if ($is_inline === false) {
                if (!empty($parent_def) && $parent_def->descendants_are_inline) {
                    $is_inline = $count - 1;
                }
            } else {
                if ($count === $is_inline) {
                    $is_inline = false;
                }
            }
            $excluded = false;
            if (!empty($exclude_stack)) {
                foreach ($exclude_stack as $lookup) {
                    if (isset($lookup[$tokens[$i]->name])) {
                        $excluded = true;
                        break;
                    }
                }
            }
            if ($excluded) {
                $result = false;
                $excludes = array();  
            } else {
                if ($i === 0) {
                    $def = $definition->info_parent_def;
                } else {
                    $def = $definition->info[$tokens[$i]->name];
                }
                if (!empty($def->child)) {
                    $result = $def->child->validateChildren(
                        $child_tokens, $config, $context);
                } else {
                    $result = false;
                }
                $excludes = $def->excludes;
            }
            if ($result === true || $child_tokens === $result) {
                $stack[] = $i;
                if (!empty($excludes)) $exclude_stack[] = $excludes;
                $i++;
            } elseif($result === false) {
                if ($e) {
                    if ($excluded) {
                        $e->send(E_ERROR, 'Strategy_FixNesting: Node excluded');
                    } else {
                        $e->send(E_ERROR, 'Strategy_FixNesting: Node removed');
                    }
                }
                $length = $j - $i + 1;
                array_splice($tokens, $i, $length);
                $size -= $length;
                if (!$parent_def->child->allow_empty) {
                    $i = $parent_index;
                    array_pop($stack);
                }
            } else {
                $length = $j - $i - 1;
                if ($e) {
                    if (empty($result) && $length) {
                        $e->send(E_ERROR, 'Strategy_FixNesting: Node contents removed');
                    } else {
                        $e->send(E_WARNING, 'Strategy_FixNesting: Node reorganized');
                    }
                }
                array_splice($tokens, $i + 1, $length, $result);
                $size -= $length;
                $size += count($result);
                $stack[] = $i;
                if (!empty($excludes)) $exclude_stack[] = $excludes;
                $i++;
            }
            $size = count($tokens);
            while ($i < $size and !$tokens[$i] instanceof HTMLPurifier_Token_Start) {
                if ($tokens[$i] instanceof HTMLPurifier_Token_End) {
                    array_pop($stack);
                    if ($i == 0 || $i == $size - 1) {
                        $s_excludes = $definition->info_parent_def->excludes;
                    } else {
                        $s_excludes = $definition->info[$tokens[$i]->name]->excludes;
                    }
                    if ($s_excludes) {
                        array_pop($exclude_stack);
                    }
                }
                $i++;
            }
        }
        //####################################################################//
        array_shift($tokens);
        array_pop($tokens);
        $context->destroy('IsInline');
        $context->destroy('CurrentToken');
        //####################################################################//
        return $tokens;
    }
}
class HTMLPurifier_Strategy_MakeWellFormed extends HTMLPurifier_Strategy
{
    protected $inputTokens, $inputIndex, $outputTokens, $currentNesting,
        $currentInjector, $injectors;
    public function execute($tokens, $config, $context) {
        $definition = $config->getHTMLDefinition();
        $result = array();
        $generator = new HTMLPurifier_Generator();
        $escape_invalid_tags = $config->get('Core', 'EscapeInvalidTags');
        $e = $context->get('ErrorCollector', true);
        $this->currentNesting = array();
        $this->inputIndex     = false;
        $this->inputTokens    =& $tokens;
        $this->outputTokens   =& $result;
        $context->register('CurrentNesting', $this->currentNesting);
        $context->register('InputIndex',     $this->inputIndex);
        $context->register('InputTokens',    $tokens);
        $this->injectors = array();
        $injectors = $config->getBatch('AutoFormat');
        $custom_injectors = $injectors['Custom'];
        unset($injectors['Custom']);  
        foreach ($injectors as $injector => $b) {
            $injector = "HTMLPurifier_Injector_$injector";
            if (!$b) continue;
            $this->injectors[] = new $injector;
        }
        foreach ($custom_injectors as $injector) {
            if (is_string($injector)) {
                $injector = "HTMLPurifier_Injector_$injector";
                $injector = new $injector;
            }
            $this->injectors[] = $injector;
        }
        $this->currentInjector = false;
        foreach ($this->injectors as $i => $injector) {
            $error = $injector->prepare($config, $context);
            if (!$error) continue;
            array_splice($this->injectors, $i, 1);  
            trigger_error("Cannot enable {$injector->name} injector because $error is not allowed", E_USER_WARNING);
        }
        $token = false;
        $context->register('CurrentToken', $token);
        for ($this->inputIndex = 0; isset($tokens[$this->inputIndex]); $this->inputIndex++) {
            $token = $tokens[$this->inputIndex];
            foreach ($this->injectors as $injector) {
                if ($injector->skip > 0) $injector->skip--;
            }
            if (empty( $token->is_tag )) {
                if ($token instanceof HTMLPurifier_Token_Text) {
                     foreach ($this->injectors as $i => $injector) {
                         if (!$injector->skip) $injector->handleText($token);
                         if (is_array($token)) {
                             $this->currentInjector = $i;
                             break;
                         }
                     }
                }
                $this->processToken($token, $config, $context);
                continue;
            }
            $info = $definition->info[$token->name]->child;
            $ok = false;
            if ($info->type === 'empty' && $token instanceof HTMLPurifier_Token_Start) {
                $token = new HTMLPurifier_Token_Empty($token->name, $token->attr);
                $ok = true;
            } elseif ($info->type !== 'empty' && $token instanceof HTMLPurifier_Token_Empty) {
                $token = array(
                    new HTMLPurifier_Token_Start($token->name, $token->attr),
                    new HTMLPurifier_Token_End($token->name)
                );
                $ok = true;
            } elseif ($token instanceof HTMLPurifier_Token_Empty) {
                $ok = true;
            } elseif ($token instanceof HTMLPurifier_Token_Start) {
                if (!empty($this->currentNesting)) {
                    $parent = array_pop($this->currentNesting);
                    $parent_info = $definition->info[$parent->name];
                    if (!isset($parent_info->child->elements[$token->name])) {
                        if ($e) $e->send(E_NOTICE, 'Strategy_MakeWellFormed: Tag auto closed', $parent);
                        $result[] = new HTMLPurifier_Token_End($parent->name);
                        $this->inputIndex--;
                        continue;
                    }
                    $this->currentNesting[] = $parent;  
                }
                $ok = true;
            }
            if ($ok) {
                foreach ($this->injectors as $i => $injector) {
                    if (!$injector->skip) $injector->handleElement($token);
                    if (is_array($token)) {
                        $this->currentInjector = $i;
                        break;
                    }
                }
                $this->processToken($token, $config, $context);
                continue;
            }
            if (!$token instanceof HTMLPurifier_Token_End) continue;
            if (empty($this->currentNesting)) {
                if ($escape_invalid_tags) {
                    if ($e) $e->send(E_WARNING, 'Strategy_MakeWellFormed: Unnecessary end tag to text');
                    $result[] = new HTMLPurifier_Token_Text(
                        $generator->generateFromToken($token, $config, $context)
                    );
                } elseif ($e) {
                    $e->send(E_WARNING, 'Strategy_MakeWellFormed: Unnecessary end tag removed');
                }
                continue;
            }
            $current_parent = array_pop($this->currentNesting);
            if ($current_parent->name == $token->name) {
                $result[] = $token;
                foreach ($this->injectors as $i => $injector) {
                    $injector->notifyEnd($token);
                }
                continue;
            }
            $this->currentNesting[] = $current_parent;
            $size = count($this->currentNesting);
            $skipped_tags = false;
            for ($i = $size - 2; $i >= 0; $i--) {
                if ($this->currentNesting[$i]->name == $token->name) {
                    $skipped_tags = array_splice($this->currentNesting, $i);
                    break;
                }
            }
            if ($skipped_tags === false) {
                if ($escape_invalid_tags) {
                    $result[] = new HTMLPurifier_Token_Text(
                        $generator->generateFromToken($token, $config, $context)
                    );
                    if ($e) $e->send(E_WARNING, 'Strategy_MakeWellFormed: Stray end tag to text');
                } elseif ($e) {
                    $e->send(E_WARNING, 'Strategy_MakeWellFormed: Stray end tag removed');
                }
                continue;
            }
            for ($i = count($skipped_tags) - 1; $i >= 0; $i--) {
                if ($i && $e && !isset($skipped_tags[$i]->armor['MakeWellFormed_TagClosedError'])) {
                    $e->send(E_NOTICE, 'Strategy_MakeWellFormed: Tag closed by element end', $skipped_tags[$i]);
                }
                $result[] = $new_token = new HTMLPurifier_Token_End($skipped_tags[$i]->name);
                foreach ($this->injectors as $injector) {
                    $injector->notifyEnd($new_token);
                }
            }
        }
        $context->destroy('CurrentNesting');
        $context->destroy('InputTokens');
        $context->destroy('InputIndex');
        $context->destroy('CurrentToken');
        if (!empty($this->currentNesting)) {
            for ($i = count($this->currentNesting) - 1; $i >= 0; $i--) {
                if ($e && !isset($this->currentNesting[$i]->armor['MakeWellFormed_TagClosedError'])) {
                    $e->send(E_NOTICE, 'Strategy_MakeWellFormed: Tag closed by document end', $this->currentNesting[$i]);
                }
                $result[] = $new_token = new HTMLPurifier_Token_End($this->currentNesting[$i]->name);
                foreach ($this->injectors as $injector) {
                    $injector->notifyEnd($new_token);
                }
            }
        }
        unset($this->outputTokens, $this->injectors, $this->currentInjector,
          $this->currentNesting, $this->inputTokens, $this->inputIndex);
        return $result;
    }
    function processToken($token, $config, $context) {
        if (is_array($token)) {
            array_splice($this->inputTokens, $this->inputIndex--, 1, $token);
            if ($this->injectors) {
                $offset = count($token);
                for ($i = 0; $i <= $this->currentInjector; $i++) {
                    if (!$this->injectors[$i]->skip) $this->injectors[$i]->skip++;
                    $this->injectors[$i]->skip += $offset;
                }
            }
        } elseif ($token) {
            $this->outputTokens[] = $token;
            if ($token instanceof HTMLPurifier_Token_Start) {
                $this->currentNesting[] = $token;
            } elseif ($token instanceof HTMLPurifier_Token_End) {
                array_pop($this->currentNesting);  
            }
        }
    }
}
class HTMLPurifier_Strategy_RemoveForeignElements extends HTMLPurifier_Strategy
{
    public function execute($tokens, $config, $context) {
        $definition = $config->getHTMLDefinition();
        $generator = new HTMLPurifier_Generator();
        $result = array();
        $escape_invalid_tags = $config->get('Core', 'EscapeInvalidTags');
        $remove_invalid_img  = $config->get('Core', 'RemoveInvalidImg');
        $remove_script_contents = $config->get('Core', 'RemoveScriptContents');
        $hidden_elements     = $config->get('Core', 'HiddenElements');
        if ($remove_script_contents === true) {
            $hidden_elements['script'] = true;
        } elseif ($remove_script_contents === false && isset($hidden_elements['script'])) {
            unset($hidden_elements['script']);
        }
        $attr_validator = new HTMLPurifier_AttrValidator();
        $remove_until = false;
        $textify_comments = false;
        $token = false;
        $context->register('CurrentToken', $token);
        $e = false;
        if ($config->get('Core', 'CollectErrors')) {
            $e =& $context->get('ErrorCollector');
        }
        foreach($tokens as $token) {
            if ($remove_until) {
                if (empty($token->is_tag) || $token->name !== $remove_until) {
                    continue;
                }
            }
            if (!empty( $token->is_tag )) {
                if (
                    isset($definition->info_tag_transform[$token->name])
                ) {
                    $original_name = $token->name;
                    $token = $definition->
                                info_tag_transform[$token->name]->
                                    transform($token, $config, $context);
                    if ($e) $e->send(E_NOTICE, 'Strategy_RemoveForeignElements: Tag transform', $original_name);
                }
                if (isset($definition->info[$token->name])) {
                    if (
                        ($token instanceof HTMLPurifier_Token_Start || $token instanceof HTMLPurifier_Token_Empty) &&
                        $definition->info[$token->name]->required_attr &&
                        ($token->name != 'img' || $remove_invalid_img)  
                    ) {
                        $attr_validator->validateToken($token, $config, $context);
                        $ok = true;
                        foreach ($definition->info[$token->name]->required_attr as $name) {
                            if (!isset($token->attr[$name])) {
                                $ok = false;
                                break;
                            }
                        }
                        if (!$ok) {
                            if ($e) $e->send(E_ERROR, 'Strategy_RemoveForeignElements: Missing required attribute', $name);
                            continue;
                        }
                        $token->armor['ValidateAttributes'] = true;
                    }
                    if (isset($hidden_elements[$token->name]) && $token instanceof HTMLPurifier_Token_Start) {
                        $textify_comments = $token->name;
                    } elseif ($token->name === $textify_comments && $token instanceof HTMLPurifier_Token_End) {
                        $textify_comments = false;
                    }
                } elseif ($escape_invalid_tags) {
                    if ($e) $e->send(E_WARNING, 'Strategy_RemoveForeignElements: Foreign element to text');
                    $token = new HTMLPurifier_Token_Text(
                        $generator->generateFromToken($token, $config, $context)
                    );
                } else {
                    if (isset($hidden_elements[$token->name])) {
                        if ($token instanceof HTMLPurifier_Token_Start) {
                            $remove_until = $token->name;
                        } elseif ($token instanceof HTMLPurifier_Token_Empty) {
                        } else {
                            $remove_until = false;
                        }
                        if ($e) $e->send(E_ERROR, 'Strategy_RemoveForeignElements: Foreign meta element removed');
                    } else {
                        if ($e) $e->send(E_ERROR, 'Strategy_RemoveForeignElements: Foreign element removed');
                    }
                    continue;
                }
            } elseif ($token instanceof HTMLPurifier_Token_Comment) {
                if ($textify_comments !== false) {
                    $data = $token->data;
                    $token = new HTMLPurifier_Token_Text($data);
                } else {
                    if ($e) $e->send(E_NOTICE, 'Strategy_RemoveForeignElements: Comment removed');
                    continue;
                }
            } elseif ($token instanceof HTMLPurifier_Token_Text) {
            } else {
                continue;
            }
            $result[] = $token;
        }
        if ($remove_until && $e) {
            $e->send(E_ERROR, 'Strategy_RemoveForeignElements: Token removed to end', $remove_until);
        }
        $context->destroy('CurrentToken');
        return $result;
    }
}
class HTMLPurifier_Strategy_ValidateAttributes extends HTMLPurifier_Strategy
{
    public function execute($tokens, $config, $context) {
        $validator = new HTMLPurifier_AttrValidator();
        $token = false;
        $context->register('CurrentToken', $token);
        foreach ($tokens as $key => $token) {
            if (!$token instanceof HTMLPurifier_Token_Start && !$token instanceof HTMLPurifier_Token_Empty) continue;
            if (!empty($token->armor['ValidateAttributes'])) continue;
            $validator->validateToken($token, $config, $context);
            $tokens[$key] = $token;  
        }
        $context->destroy('CurrentToken');
        return $tokens;
    }
}
class HTMLPurifier_TagTransform_Font extends HTMLPurifier_TagTransform
{
    public $transform_to = 'span';
    protected $_size_lookup = array(
        '0' => 'xx-small',
        '1' => 'xx-small',
        '2' => 'small',
        '3' => 'medium',
        '4' => 'large',
        '5' => 'x-large',
        '6' => 'xx-large',
        '7' => '300%',
        '-1' => 'smaller',
        '-2' => '60%',
        '+1' => 'larger',
        '+2' => '150%',
        '+3' => '200%',
        '+4' => '300%'
    );
    public function transform($tag, $config, $context) {
        if ($tag instanceof HTMLPurifier_Token_End) {
            $new_tag = clone $tag;
            $new_tag->name = $this->transform_to;
            return $new_tag;
        }
        $attr = $tag->attr;
        $prepend_style = '';
        if (isset($attr['color'])) {
            $prepend_style .= 'color:' . $attr['color'] . ';';
            unset($attr['color']);
        }
        if (isset($attr['face'])) {
            $prepend_style .= 'font-family:' . $attr['face'] . ';';
            unset($attr['face']);
        }
        if (isset($attr['size'])) {
            if ($attr['size']{0} == '+' || $attr['size']{0} == '-') {
                $size = (int) $attr['size'];
                if ($size < -2) $attr['size'] = '-2';
                if ($size > 4)  $attr['size'] = '+4';
            } else {
                $size = (int) $attr['size'];
                if ($size > 7) $attr['size'] = '7';
            }
            if (isset($this->_size_lookup[$attr['size']])) {
                $prepend_style .= 'font-size:' .
                  $this->_size_lookup[$attr['size']] . ';';
            }
            unset($attr['size']);
        }
        if ($prepend_style) {
            $attr['style'] = isset($attr['style']) ?
                $prepend_style . $attr['style'] :
                $prepend_style;
        }
        $new_tag = clone $tag;
        $new_tag->name = $this->transform_to;
        $new_tag->attr = $attr;
        return $new_tag;
    }
}
class HTMLPurifier_TagTransform_Simple extends HTMLPurifier_TagTransform
{
    protected $style;
    public function __construct($transform_to, $style = null) {
        $this->transform_to = $transform_to;
        $this->style = $style;
    }
    public function transform($tag, $config, $context) {
        $new_tag = clone $tag;
        $new_tag->name = $this->transform_to;
        if (!is_null($this->style) &&
            ($new_tag instanceof HTMLPurifier_Token_Start || $new_tag instanceof HTMLPurifier_Token_Empty)
        ) {
            $this->prependCSS($new_tag->attr, $this->style);
        }
        return $new_tag;
    }
}
class HTMLPurifier_Token_Comment extends HTMLPurifier_Token
{
    public $data; /**< Character data within comment. */
    public function __construct($data, $line = null) {
        $this->data = $data;
        $this->line = $line;
    }
}
class HTMLPurifier_Token_Tag extends HTMLPurifier_Token
{
    public $is_tag = true;
    public $name;
    public $attr = array();
    public function __construct($name, $attr = array(), $line = null) {
        $this->name = ctype_lower($name) ? $name : strtolower($name);
        foreach ($attr as $key => $value) {
            if (!ctype_lower($key)) {
                $new_key = strtolower($key);
                if (!isset($attr[$new_key])) {
                    $attr[$new_key] = $attr[$key];
                }
                if ($new_key !== $key) {
                    unset($attr[$key]);
                }
            }
        }
        $this->attr = $attr;
        $this->line = $line;
    }
}
class HTMLPurifier_Token_Empty extends HTMLPurifier_Token_Tag
{
}
class HTMLPurifier_Token_End extends HTMLPurifier_Token_Tag
{
}
class HTMLPurifier_Token_Start extends HTMLPurifier_Token_Tag
{
}
class HTMLPurifier_Token_Text extends HTMLPurifier_Token
{
    public $name = '#PCDATA'; /**< PCDATA tag name compatible with DTD. */
    public $data; /**< Parsed character data of text. */
    public $is_whitespace; /**< Bool indicating if node is whitespace. */
    public function __construct($data, $line = null) {
        $this->data = $data;
        $this->is_whitespace = ctype_space($data);
        $this->line = $line;
    }
}
class HTMLPurifier_URIFilter_DisableExternal extends HTMLPurifier_URIFilter
{
    public $name = 'DisableExternal';
    protected $ourHostParts = false;
    public function prepare($config) {
        $our_host = $config->get('URI', 'Host');
        if ($our_host !== null) $this->ourHostParts = array_reverse(explode('.', $our_host));
    }
    public function filter(&$uri, $config, $context) {
        if (is_null($uri->host)) return true;
        if ($this->ourHostParts === false) return false;
        $host_parts = array_reverse(explode('.', $uri->host));
        foreach ($this->ourHostParts as $i => $x) {
            if (!isset($host_parts[$i])) return false;
            if ($host_parts[$i] != $this->ourHostParts[$i]) return false;
        }
        return true;
    }
}
class HTMLPurifier_URIFilter_DisableExternalResources extends HTMLPurifier_URIFilter_DisableExternal
{
    public $name = 'DisableExternalResources';
    public function filter(&$uri, $config, $context) {
        if (!$context->get('EmbeddedURI', true)) return true;
        return parent::filter($uri, $config, $context);
    }
}
class HTMLPurifier_URIFilter_HostBlacklist extends HTMLPurifier_URIFilter
{
    public $name = 'HostBlacklist';
    protected $blacklist = array();
    public function prepare($config) {
        $this->blacklist = $config->get('URI', 'HostBlacklist');
    }
    public function filter(&$uri, $config, $context) {
        foreach($this->blacklist as $blacklisted_host_fragment) {
            if (strpos($uri->host, $blacklisted_host_fragment) !== false) {
                return false;
            }
        }
        return true;
    }
}
class HTMLPurifier_URIFilter_MakeAbsolute extends HTMLPurifier_URIFilter
{
    public $name = 'MakeAbsolute';
    protected $base;
    protected $basePathStack = array();
    public function prepare($config) {
        $def = $config->getDefinition('URI');
        $this->base = $def->base;
        if (is_null($this->base)) {
            trigger_error('URI.MakeAbsolute is being ignored due to lack of value for URI.Base configuration', E_USER_ERROR);
            return;
        }
        $this->base->fragment = null;  
        $stack = explode('/', $this->base->path);
        array_pop($stack);  
        $stack = $this->_collapseStack($stack);  
        $this->basePathStack = $stack;
    }
    public function filter(&$uri, $config, $context) {
        if (is_null($this->base)) return true;  
        if (
            $uri->path === '' && is_null($uri->scheme) &&
            is_null($uri->host) && is_null($uri->query) && is_null($uri->fragment)
        ) {
            $uri = clone $this->base;
            return true;
        }
        if (!is_null($uri->scheme)) {
            if (!is_null($uri->host)) return true;
            $scheme_obj = $uri->getSchemeObj($config, $context);
            if (!$scheme_obj) {
                return false;
            }
            if (!$scheme_obj->hierarchical) {
                return true;
            }
        }
        if (!is_null($uri->host)) {
            return true;
        }
        if ($uri->path === '') {
            $uri->path = $this->base->path;
        }elseif ($uri->path[0] !== '/') {
            $stack = explode('/', $uri->path);
            $new_stack = array_merge($this->basePathStack, $stack);
            $new_stack = $this->_collapseStack($new_stack);
            $uri->path = implode('/', $new_stack);
        }
        $uri->scheme = $this->base->scheme;
        if (is_null($uri->userinfo)) $uri->userinfo = $this->base->userinfo;
        if (is_null($uri->host))     $uri->host     = $this->base->host;
        if (is_null($uri->port))     $uri->port     = $this->base->port;
        return true;
    }
    private function _collapseStack($stack) {
        $result = array();
        for ($i = 0; isset($stack[$i]); $i++) {
            $is_folder = false;
            if ($stack[$i] == '' && $i && isset($stack[$i+1])) continue;
            if ($stack[$i] == '..') {
                if (!empty($result)) {
                    $segment = array_pop($result);
                    if ($segment === '' && empty($result)) {
                        $result[] = '';
                    } elseif ($segment === '..') {
                        $result[] = '..';  
                    }
                } else {
                    $result[] = '..';
                }
                $is_folder = true;
                continue;
            }
            if ($stack[$i] == '.') {
                $is_folder = true;
                continue;
            }
            $result[] = $stack[$i];
        }
        if ($is_folder) $result[] = '';
        return $result;
    }
}
class HTMLPurifier_URIScheme_ftp extends HTMLPurifier_URIScheme {
    public $default_port = 21;
    public $browsable = true;  
    public $hierarchical = true;
    public function validate(&$uri, $config, $context) {
        parent::validate($uri, $config, $context);
        $uri->query    = null;
        $semicolon_pos = strrpos($uri->path, ';');  
        if ($semicolon_pos !== false) {
            $type = substr($uri->path, $semicolon_pos + 1);  
            $uri->path = substr($uri->path, 0, $semicolon_pos);
            $type_ret = '';
            if (strpos($type, '=') !== false) {
                list($key, $typecode) = explode('=', $type, 2);
                if ($key !== 'type') {
                    $uri->path .= '%3B' . $type;
                } elseif ($typecode === 'a' || $typecode === 'i' || $typecode === 'd') {
                    $type_ret = ";type=$typecode";
                }
            } else {
                $uri->path .= '%3B' . $type;
            }
            $uri->path = str_replace(';', '%3B', $uri->path);
            $uri->path .= $type_ret;
        }
        return true;
    }
}
class HTMLPurifier_URIScheme_http extends HTMLPurifier_URIScheme {
    public $default_port = 80;
    public $browsable = true;
    public $hierarchical = true;
    public function validate(&$uri, $config, $context) {
        parent::validate($uri, $config, $context);
        $uri->userinfo = null;
        return true;
    }
}
class HTMLPurifier_URIScheme_https extends HTMLPurifier_URIScheme_http {
    public $default_port = 443;
}
class HTMLPurifier_URIScheme_mailto extends HTMLPurifier_URIScheme {
    public $browsable = false;
    public function validate(&$uri, $config, $context) {
        parent::validate($uri, $config, $context);
        $uri->userinfo = null;
        $uri->host     = null;
        $uri->port     = null;
        return true;
    }
}
class HTMLPurifier_URIScheme_news extends HTMLPurifier_URIScheme {
    public $browsable = false;
    public function validate(&$uri, $config, $context) {
        parent::validate($uri, $config, $context);
        $uri->userinfo = null;
        $uri->host     = null;
        $uri->port     = null;
        $uri->query    = null;
        return true;
    }
}
class HTMLPurifier_URIScheme_nntp extends HTMLPurifier_URIScheme {
    public $default_port = 119;
    public $browsable = false;
    public function validate(&$uri, $config, $context) {
        parent::validate($uri, $config, $context);
        $uri->userinfo = null;
        $uri->query    = null;
        return true;
    }
}
class HTMLPurifier_VarParser_Flexible extends HTMLPurifier_VarParser
{
    protected function parseImplementation($var, $type, $allow_null) {
        if ($allow_null && $var === null) return null;
        switch ($type) {
            case 'mixed':
            case 'istring':
            case 'string':
            case 'text':
            case 'itext':
                return $var;
            case 'int':
                if (is_string($var) && ctype_digit($var)) $var = (int) $var;
                return $var;
            case 'float':
                if ((is_string($var) && is_numeric($var)) || is_int($var)) $var = (float) $var;
                return $var;
            case 'bool':
                if (is_int($var) && ($var === 0 || $var === 1)) {
                    $var = (bool) $var;
                } elseif (is_string($var)) {
                    if ($var == 'on' || $var == 'true' || $var == '1') {
                        $var = true;
                    } elseif ($var == 'off' || $var == 'false' || $var == '0') {
                        $var = false;
                    } else {
                        throw new HTMLPurifier_VarParserException("Unrecognized value '$var' for $type");
                    }
                }
                return $var;
            case 'list':
            case 'hash':
            case 'lookup':
                if (is_string($var)) {
                    if ($var == '') return array();
                    if (strpos($var, "\n") === false && strpos($var, "\r") === false) {
                        $var = explode(',',$var);
                    } else {
                        $var = preg_split('/(,|[\n\r]+)/', $var);
                    }
                    foreach ($var as $i => $j) $var[$i] = trim($j);
                    if ($type === 'hash') {
                        $nvar = array();
                        foreach ($var as $keypair) {
                            $c = explode(':', $keypair, 2);
                            if (!isset($c[1])) continue;
                            $nvar[$c[0]] = $c[1];
                        }
                        $var = $nvar;
                    }
                }
                if (!is_array($var)) break;
                $keys = array_keys($var);
                if ($keys === array_keys($keys)) {
                    if ($type == 'list') return $var;
                    elseif ($type == 'lookup') {
                        $new = array();
                        foreach ($var as $key) {
                            $new[$key] = true;
                        }
                        return $new;
                    } else break;
                }
                if ($type === 'lookup') {
                    foreach ($var as $key => $value) {
                        $var[$key] = true;
                    }
                }
                return $var;
            default:
                $this->errorInconsistent(__CLASS__, $type);
        }
        $this->errorGeneric($var, $type);
    }
}
class HTMLPurifier_VarParser_Native extends HTMLPurifier_VarParser
{
    protected function parseImplementation($var, $type, $allow_null) {
        return $this->evalExpression($var);
    }
    protected function evalExpression($expr) {
        $var = null;
        $result = eval("\$var = $expr;");
        if ($result === false) {
            throw new HTMLPurifier_VarParserException("Fatal error in evaluated code");
        }
        return $var;
    }
}
