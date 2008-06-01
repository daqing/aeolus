<?php

class HTMLPurifier
{
    /** Version of HTML Purifier */
    public $version = '3.1.0';
    
	# This is a standalone edition 
    /** Constant with version of HTML Purifier */
    const VERSION = '3.1.0';
    
    /** Global configuration object */
    public $config;
    
    /** Array of extra HTMLPurifier_Filter objects to run on HTML, for backwards compatibility */
    private $filters = array();
    
    /** Single instance of HTML Purifier */
    private static $instance;
    
    protected $strategy, $generator;
    
    /**
     * Resultant HTMLPurifier_Context of last run purification. Is an array
     * of contexts if the last called method was purifyArray().
     */
    public $context;
    
    /**
     * Initializes the purifier.
     */
    public function __construct($config = null) {
        $this->config = HTMLPurifier_Config::create($config);

        $this->strategy     = new HTMLPurifier_Strategy_Core();
        
    }
    
    /**
     * Filters an HTML snippet/document to be XSS-free and standards-compliant.
     * @return Purified HTML
     */
    public function purify($html, $config = null) {
        
        // :TODO: make the config merge in, instead of replace
        $config = $config ? HTMLPurifier_Config::create($config) : $this->config;
        
        // implementation is partially environment dependant, partially
        // configuration dependant
        $lexer = HTMLPurifier_Lexer::create($config);
        
        $context = new HTMLPurifier_Context();
        
        // setup HTML generator
        $this->generator = new HTMLPurifier_Generator($config, $context);
        $context->register('Generator', $this->generator);
        
        // set up global context variables
        if ($config->get('Core', 'CollectErrors')) {
            // may get moved out if other facilities use it
            $language_factory = HTMLPurifier_LanguageFactory::instance();
            $language = $language_factory->create($config, $context);
            $context->register('Locale', $language);
            
            $error_collector = new HTMLPurifier_ErrorCollector($context);
            $context->register('ErrorCollector', $error_collector);
        }
        
        // setup id_accumulator context, necessary due to the fact that
        // AttrValidator can be called from many places
        $id_accumulator = HTMLPurifier_IDAccumulator::build($config, $context);
        $context->register('IDAccumulator', $id_accumulator);
        
        $html = HTMLPurifier_Encoder::convertToUTF8($html, $config, $context);
        
        // setup filters
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
            // maybe "HTMLPurifier_Filter_$filter", but be consistent with AutoFormat
            $filters[] = $filter;
        }
        $filters = array_merge($filters, $this->filters);
        // maybe prepare(), but later
        
        for ($i = 0, $filter_size = count($filters); $i < $filter_size; $i++) {
            $html = $filters[$i]->preFilter($html, $config, $context);
        }
        
        // purified HTML
        $html = 
            $this->generator->generateFromTokens(
                // list of tokens
                $this->strategy->execute(
                    // list of un-purified tokens
                    $lexer->tokenizeHTML(
                        // un-purified HTML
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
    
    /**
     * Filters an array of HTML snippets
     * @param $config Optional HTMLPurifier_Config object for this operation.
     *                See HTMLPurifier::purify() for more details.
     * @return Array of purified HTML
     */
    public function purifyArray($array_of_html, $config = null) {
        $context_array = array();
        foreach ($array_of_html as $key => $html) {
            $array_of_html[$key] = $this->purify($html, $config);
            $context_array[$key] = $this->context;
        }
        $this->context = $context_array;
        return $array_of_html;
    }
    
    /**
     * Singleton for enforcing just one HTML Purifier in your system
     * @param $prototype Optional prototype HTMLPurifier instance to
     *                   overload singleton with, or HTMLPurifier_Config
     *                   instance to configure the generated version with.
     */
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
    
    /**
     * @note Backwards compatibility, see instance()
     */
    public static function getInstance($prototype = null) {
        return HTMLPurifier::instance($prototype);
    }
    
}

/**
 * Defines common attribute collections that modules reference
 */
class HTMLPurifier_AttrCollections
{
    
    /**
     * Associative array of attribute collections, indexed by name
     */
    public $info = array();
    
    /**
     * Performs all expansions on internal data for use by other inclusions
     * It also collects all attribute collection extensions from
     * modules
     * @param $attr_types HTMLPurifier_AttrTypes instance
     * @param $modules Hash array of HTMLPurifier_HTMLModule members
     */
    public function __construct($attr_types, $modules) {
        // load extensions from the modules
        foreach ($modules as $module) {
            foreach ($module->attr_collections as $coll_i => $coll) {
                if (!isset($this->info[$coll_i])) {
                    $this->info[$coll_i] = array();
                }
                foreach ($coll as $attr_i => $attr) {
                    if ($attr_i === 0 && isset($this->info[$coll_i][$attr_i])) {
                        // merge in includes
                        $this->info[$coll_i][$attr_i] = array_merge(
                            $this->info[$coll_i][$attr_i], $attr);
                        continue;
                    }
                    $this->info[$coll_i][$attr_i] = $attr;
                }
            }
        }
        // perform internal expansions and inclusions
        foreach ($this->info as $name => $attr) {
            // merge attribute collections that include others
            $this->performInclusions($this->info[$name]);
            // replace string identifiers with actual attribute objects
            $this->expandIdentifiers($this->info[$name], $attr_types);
        }
    }
    
    /**
     * Takes a reference to an attribute associative array and performs
     * all inclusions specified by the zero index.
     * @param &$attr Reference to attribute array
     */
    public function performInclusions(&$attr) {
        if (!isset($attr[0])) return;
        $merge = $attr[0];
        $seen  = array(); // recursion guard
        // loop through all the inclusions
        for ($i = 0; isset($merge[$i]); $i++) {
            if (isset($seen[$merge[$i]])) continue;
            $seen[$merge[$i]] = true;
            // foreach attribute of the inclusion, copy it over
            if (!isset($this->info[$merge[$i]])) continue;
            foreach ($this->info[$merge[$i]] as $key => $value) {
                if (isset($attr[$key])) continue; // also catches more inclusions
                $attr[$key] = $value;
            }
            if (isset($this->info[$merge[$i]][0])) {
                // recursion
                $merge = array_merge($merge, $this->info[$merge[$i]][0]);
            }
        }
        unset($attr[0]);
    }
    
    /**
     * Expands all string identifiers in an attribute array by replacing
     * them with the appropriate values inside HTMLPurifier_AttrTypes
     * @param &$attr Reference to attribute array
     * @param $attr_types HTMLPurifier_AttrTypes instance
     */
    public function expandIdentifiers(&$attr, $attr_types) {
        
        // because foreach will process new elements we add, make sure we
        // skip duplicates
        $processed = array();
        
        foreach ($attr as $def_i => $def) {
            // skip inclusions
            if ($def_i === 0) continue;
            
            if (isset($processed[$def_i])) continue;
            
            // determine whether or not attribute is required
            if ($required = (strpos($def_i, '*') !== false)) {
                // rename the definition
                unset($attr[$def_i]);
                $def_i = trim($def_i, '*');
                $attr[$def_i] = $def;
            }
            
            $processed[$def_i] = true;
            
            // if we've already got a literal object, move on
            if (is_object($def)) {
                // preserve previous required
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
    
    /**
     * Tells us whether or not an HTML attribute is minimized. Has no
     * meaning in other contexts.
     */
    public $minimized = false;
    
    /**
     * Tells us whether or not an HTML attribute is required. Has no
     * meaning in other contexts
     */
    public $required = false;
    
    /**
     * Validates and cleans passed string according to a definition.
     * 
     * @param $string String to be validated and cleaned.
     * @param $config Mandatory HTMLPurifier_Config object.
     * @param $context Mandatory HTMLPurifier_AttrContext object.
     */
    abstract public function validate($string, $config, $context);
    
    public function parseCDATA($string) {
        $string = trim($string);
        $string = str_replace("\n", '', $string);
        $string = str_replace(array("\r", "\t"), ' ', $string);
        return $string;
    }
    
    /**
     * Factory method for creating this class from a string.
     * @param $string String construction info
     * @return Created AttrDef object corresponding to $string
     */
    public function make($string) {
        return $this;
    }
    
    /**
     * Removes spaces from rgb(0, 0, 0) so that shorthand CSS properties work
     * properly. THIS IS A HACK!
     */
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

/**
 * Provides lookup array of attribute types to HTMLPurifier_AttrDef objects
 */
class HTMLPurifier_AttrTypes
{
    /**
     * Lookup array of attribute string identifiers to concrete implementations
     */
    protected $info = array();
    
    /**
     * Constructs the info array, supplying default implementations for attribute
     * types.
     */
    public function __construct() {
        // pseudo-types, must be instantiated via shorthand
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
        
        // unimplemented aliases
        $this->info['ContentType'] = new HTMLPurifier_AttrDef_Text();
        
        // number is really a positive integer (one or more digits)
        // FIXME: ^^ not always, see start and value of list items
        $this->info['Number']   = new HTMLPurifier_AttrDef_Integer(false, false, true);
    }
    
    /**
     * Retrieves a type
     * @param $type String type name
     * @return Object AttrDef for type
     */
    public function get($type) {
        
        // determine if there is any extra info tacked on
        if (strpos($type, '#') !== false) list($type, $string) = explode('#', $type, 2);
        else $string = '';
        
        if (!isset($this->info[$type])) {
            trigger_error('Cannot retrieve undefined attribute type ' . $type, E_USER_ERROR);
            return;
        }
        
        return $this->info[$type]->make($string);
        
    }
    
    /**
     * Sets a new implementation for a type
     * @param $type String type name
     * @param $impl Object AttrDef for type
     */
    public function set($type, $impl) {
        $this->info[$type] = $impl;
    }
}

/**
 * Validates the attributes of a token. Doesn't manage required attributes
 * very well. The only reason we factored this out was because RemoveForeignElements
 * also needed it besides ValidateAttributes.
 */
class HTMLPurifier_AttrValidator
{
    
    public function validateToken(&$token, &$config, $context) {
            
        $definition = $config->getHTMLDefinition();
        $e =& $context->get('ErrorCollector', true);
        
        // initialize IDAccumulator if necessary
        $ok =& $context->get('IDAccumulator', true);
        if (!$ok) {
            $id_accumulator = HTMLPurifier_IDAccumulator::build($config, $context);
            $context->register('IDAccumulator', $id_accumulator);
        }
        
        // initialize CurrentToken if necessary
        $current_token =& $context->get('CurrentToken', true);
        if (!$current_token) $context->register('CurrentToken', $token);
        
        if (
          !$token instanceof HTMLPurifier_Token_Start &&
          !$token instanceof HTMLPurifier_Token_Empty
        ) return $token;
        
        // create alias to global definition array, see also $defs
        // DEFINITION CALL
        $d_defs = $definition->info_global_attr;
        
        // reference attributes for easy manipulation
        $attr =& $token->attr;
        
        // do global transformations (pre)
        // nothing currently utilizes this
        foreach ($definition->info_attr_transform_pre as $transform) {
            $attr = $transform->transform($o = $attr, $config, $context);
            if ($e && ($attr != $o)) $e->send(E_NOTICE, 'AttrValidator: Attributes transformed', $o, $attr);
        }
        
        // do local transformations only applicable to this element (pre)
        // ex. <p align="right"> to <p style="text-align:right;">
        foreach ($definition->info[$token->name]->attr_transform_pre as $transform) {
            $attr = $transform->transform($o = $attr, $config, $context);
            if ($e && ($attr != $o)) $e->send(E_NOTICE, 'AttrValidator: Attributes transformed', $o, $attr);
        }
        
        // create alias to this element's attribute definition array, see
        // also $d_defs (global attribute definition array)
        // DEFINITION CALL
        $defs = $definition->info[$token->name]->attr;
        
        $attr_key = false;
        $context->register('CurrentAttr', $attr_key);
        
        // iterate through all the attribute keypairs
        // Watch out for name collisions: $key has previously been used
        foreach ($attr as $attr_key => $value) {
            
            // call the definition
            if ( isset($defs[$attr_key]) ) {
                // there is a local definition defined
                if ($defs[$attr_key] === false) {
                    $result = false;
                } else {
                    // validate according to the element's definition
                    $result = $defs[$attr_key]->validate(
                                    $value, $config, $context
                               );
                }
            } elseif ( isset($d_defs[$attr_key]) ) {
                // there is a global definition defined, validate according
                // to the global definition
                $result = $d_defs[$attr_key]->validate(
                                $value, $config, $context
                           );
            } else {
                // system never heard of the attribute? DELETE!
                $result = false;
            }
            
            // put the results into effect
            if ($result === false || $result === null) {
                // this is a generic error message that should replaced
                // with more specific ones when possible
                if ($e) $e->send(E_ERROR, 'AttrValidator: Attribute removed');
                
                // remove the attribute
                unset($attr[$attr_key]);
            } elseif (is_string($result)) {
                $attr[$attr_key] = $result;
            }
            
        }
        
        $context->destroy('CurrentAttr');
        
        // post transforms
        
        // global (error reporting untested)
        foreach ($definition->info_attr_transform_post as $transform) {
            $attr = $transform->transform($o = $attr, $config, $context);
            if ($e && ($attr != $o)) $e->send(E_NOTICE, 'AttrValidator: Attributes transformed', $o, $attr);
        }
        
        // local (error reporting untested)
        foreach ($definition->info[$token->name]->attr_transform_post as $transform) {
            $attr = $transform->transform($o = $attr, $config, $context);
            if ($e && ($attr != $o)) $e->send(E_NOTICE, 'AttrValidator: Attributes transformed', $o, $attr);
        }
        
        // destroy CurrentToken if we made it ourselves
        if (!$current_token) $context->destroy('CurrentToken');
        
    }
    
    
}

// constants are slow, so we use as few as possible
if (!defined('HTMLPURIFIER_PREFIX')) {
    define('HTMLPURIFIER_PREFIX', dirname(__FILE__)); 
    set_include_path(HTMLPURIFIER_PREFIX . PATH_SEPARATOR . get_include_path());
}

// accomodations for versions earlier than 5.0.2
// borrowed from PHP_Compat, LGPL licensed, by Aidan Lister <aidan@php.net>
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

/**
 * Bootstrap class that contains meta-functionality for HTML Purifier such as
 * the autoload function.
 *
 * @note
 *      This class may be used without any other files from HTML Purifier.
 */
class HTMLPurifier_Bootstrap
{
    
    /**
     * Autoload function for HTML Purifier
     * @param $class Class to load
     */
    public static function autoload($class) {
        $file = HTMLPurifier_Bootstrap::getPath($class);
        if (!$file) return false;
        require HTMLPURIFIER_PREFIX . '/' . $file;
        return true;
    }
    
    /**
     * Returns the path for a specific class.
     */
    public static function getPath($class) {
        return str_replace('_', '/', $class) . '.php';
    }
    
    /**
     * "Pre-registers" our autoloader on the SPL stack.
     */
    public static function registerAutoload() {
        $autoload = array('HTMLPurifier_Bootstrap', 'autoload');
        if ( ($funcs = spl_autoload_functions()) === false ) {
            spl_autoload_register($autoload);
        } elseif (function_exists('spl_autoload_unregister')) {
            $compat = version_compare(PHP_VERSION, '5.1.2', '<=') &&
                      version_compare(PHP_VERSION, '5.1.0', '>=');
            foreach ($funcs as $func) {
                if (is_array($func)) {
                    // :TRICKY: There are some compatibility issues and some
                    // places where we need to error out
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
                    // Suprisingly, spl_autoload_register supports the
                    // Class::staticMethod callback format, although call_user_func doesn't
                    if ($compat) $func = implode('::', $func);
                }
                spl_autoload_unregister($func);
            }
            spl_autoload_register($autoload);
            foreach ($funcs as $func) spl_autoload_register($func);
        }
    }
    
}

/**
 * Super-class for definition datatype objects, implements serialization
 * functions for the class.
 */
abstract class HTMLPurifier_Definition
{
    
    /**
     * Has setup() been called yet?
     */
    public $setup = false;
    
    /**
     * What type of definition is it?
     */
    public $type;
    
    /**
     * Sets up the definition object into the final form, something
     * not done by the constructor
     * @param $config HTMLPurifier_Config instance
     */
    abstract protected function doSetup($config);
    
    /**
     * Setup function that aborts if already setup
     * @param $config HTMLPurifier_Config instance
     */
    public function setup($config) {
        if ($this->setup) return;
        $this->setup = true;
        $this->doSetup($config);
    }
    
}

/**
 * Defines allowed CSS attributes and what their values are.
 * @see HTMLPurifier_HTMLDefinition
 */
class HTMLPurifier_CSSDefinition extends HTMLPurifier_Definition
{
    
    public $type = 'CSS';
    
    /**
     * Assoc array of attribute name to definition object.
     */
    public $info = array();
    
    /**
     * Constructs the info array.  The meat of this class.
     */
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
            new HTMLPurifier_AttrDef_CSS_Length(true) //disallow negative
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
            new HTMLPurifier_AttrDef_CSS_Number(true), // no negatives
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
        
        // non-negative
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
        
        // this could use specialized code
        $this->info['font-weight'] = new HTMLPurifier_AttrDef_Enum(
            array('normal', 'bold', 'bolder', 'lighter', '100', '200', '300',
            '400', '500', '600', '700', '800', '900'), false);
        
        // MUST be called after other font properties, as it references
        // a CSSDefinition object
        $this->info['font'] = new HTMLPurifier_AttrDef_CSS_Font($config);
        
        // same here
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
        
        // partial support
        $this->info['white-space'] = new HTMLPurifier_AttrDef_Enum(array('nowrap'));
        
        if ($config->get('CSS', 'Proprietary')) {
            $this->doSetupProprietary($config);
        }
        
        if ($config->get('CSS', 'AllowTricky')) {
            $this->doSetupTricky($config);
        }
        
        $allow_important = $config->get('CSS', 'AllowImportant');
        // wrap all attr-defs with decorator that handles !important
        foreach ($this->info as $k => $v) {
            $this->info[$k] = new HTMLPurifier_AttrDef_CSS_ImportantDecorator($v, $allow_important);
        }
        
        $this->setupConfigStuff($config);
    }
    
    protected function doSetupProprietary($config) {
        // Internet Explorer only scrollbar colors
        $this->info['scrollbar-arrow-color']        = new HTMLPurifier_AttrDef_CSS_Color();
        $this->info['scrollbar-base-color']         = new HTMLPurifier_AttrDef_CSS_Color();
        $this->info['scrollbar-darkshadow-color']   = new HTMLPurifier_AttrDef_CSS_Color();
        $this->info['scrollbar-face-color']         = new HTMLPurifier_AttrDef_CSS_Color();
        $this->info['scrollbar-highlight-color']    = new HTMLPurifier_AttrDef_CSS_Color();
        $this->info['scrollbar-shadow-color']       = new HTMLPurifier_AttrDef_CSS_Color();
        
        // technically not proprietary, but CSS3, and no one supports it
        $this->info['opacity']          = new HTMLPurifier_AttrDef_CSS_AlphaValue();
        $this->info['-moz-opacity']     = new HTMLPurifier_AttrDef_CSS_AlphaValue();
        $this->info['-khtml-opacity']   = new HTMLPurifier_AttrDef_CSS_AlphaValue();
        
        // only opacity, for now
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
    
    
    /**
     * Performs extra config-based processing. Based off of
     * HTMLPurifier_HTMLDefinition.
     * @todo Refactor duplicate elements into common class (probably using
     *       composition, not inheritance).
     */
    protected function setupConfigStuff($config) {
        
        // setup allowed elements
        $support = "(for information on implementing this, see the ".
                   "support forums) ";
        $allowed_attributes = $config->get('CSS', 'AllowedProperties');
        if ($allowed_attributes !== null) {
            foreach ($this->info as $name => $d) {
                if(!isset($allowed_attributes[$name])) unset($this->info[$name]);
                unset($allowed_attributes[$name]);
            }
            // emit errors
            foreach ($allowed_attributes as $name => $d) {
                // :TODO: Is this htmlspecialchars() call really necessary?
                $name = htmlspecialchars($name);
                trigger_error("Style attribute '$name' is not supported $support", E_USER_WARNING);
            }
        }
        
    }
}

/**
 * Defines allowed child nodes and validates tokens against it.
 */
abstract class HTMLPurifier_ChildDef
{
    public $type;
    
    public $allow_empty;
    
    public $elements = array();
    
    abstract public function validateChildren($tokens_of_children, $config, $context);
}

/**
 * Configuration object that triggers customizable behavior.
 *
 */
class HTMLPurifier_Config
{
    
    /**
     * HTML Purifier's version
     */
    public $version = '3.1.0';
    
    /**
     * Bool indicator whether or not to automatically finalize 
     * the object if a read operation is done
     */
    public $autoFinalize = true;
    
    // protected member variables
    
    /**
     * Namespace indexed array of serials for specific namespaces (see
     * getSerial() for more info).
     */
    protected $serials = array();
    
    /**
     * Serial for entire configuration object
     */
    protected $serial;
    
    /**
     * Two-level associative array of configuration directives
     */
    protected $conf;
    
    /**
     * Parser for variables
     */
    protected $parser;
    
    /**
     * Reference HTMLPurifier_ConfigSchema for value checking
     * @note This is public for introspective purposes. Please don't
     *       abuse!
     */
    public $def;
    
    /**
     * Indexed array of definitions
     */
    protected $definitions;
    
    /**
     * Bool indicator whether or not config is finalized
     */
    protected $finalized = false;
    
    /**
     * @param $definition HTMLPurifier_ConfigSchema that defines what directives
     *                    are allowed.
     */
    public function __construct($definition) {
        $this->conf = $definition->defaults; // set up, copy in defaults
        $this->def  = $definition; // keep a copy around for checking
        $this->parser = new HTMLPurifier_VarParser_Flexible();
    }
    
    public static function create($config, $schema = null) {
        if ($config instanceof HTMLPurifier_Config) {
            // pass-through
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
    
    /**
     * Convenience constructor that creates a default configuration object.
     * @return Default HTMLPurifier_Config object.
     */
    public static function createDefault() {
        $definition = HTMLPurifier_ConfigSchema::instance();
        $config = new HTMLPurifier_Config($definition);
        return $config;
    }
    
    /**
     * Retreives a value from the configuration.
     * @param $namespace String namespace
     * @param $key String key
     */
    public function get($namespace, $key) {
        if (!$this->finalized && $this->autoFinalize) $this->finalize();
        if (!isset($this->def->info[$namespace][$key])) {
            // can't add % due to SimpleTest bug
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
    
    /**
     * Retreives an array of directives to values from a given namespace
     * @param $namespace String namespace
     */
    public function getBatch($namespace) {
        if (!$this->finalized && $this->autoFinalize) $this->finalize();
        if (!isset($this->def->info[$namespace])) {
            trigger_error('Cannot retrieve undefined namespace ' . htmlspecialchars($namespace),
                E_USER_WARNING);
            return;
        }
        return $this->conf[$namespace];
    }
    
    /**
     * Returns a md5 signature of a segment of the configuration object
     * that uniquely identifies that particular configuration
     * @note Revision is handled specially and is removed from the batch
     *       before processing!
     * @param $namespace Namespace to get serial for
     */
    public function getBatchSerial($namespace) {
        if (empty($this->serials[$namespace])) {
            $batch = $this->getBatch($namespace);
            unset($batch['DefinitionRev']);
            $this->serials[$namespace] = md5(serialize($batch));
        }
        return $this->serials[$namespace];
    }
    
    /**
     * Returns a md5 signature for the entire configuration object
     * that uniquely identifies that particular configuration
     */
    public function getSerial() {
        if (empty($this->serial)) {
            $this->serial = md5(serialize($this->getAll()));
        }
        return $this->serial;
    }
    
    /**
     * Retrieves all directives, organized by namespace
     */
    public function getAll() {
        if (!$this->finalized && $this->autoFinalize) $this->finalize();
        return $this->conf;
    }
    
    /**
     * Sets a value to configuration.
     * @param $namespace String namespace
     * @param $key String key
     * @param $value Mixed value
     */
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
            // resolve value alias if defined
            if (isset($this->def->info[$namespace][$key]->aliases[$value])) {
                $value = $this->def->info[$namespace][$key]->aliases[$value];
            }
            if ($this->def->info[$namespace][$key]->allowed !== true) {
                // check to see if the value is allowed
                if (!isset($this->def->info[$namespace][$key]->allowed[$value])) {
                    trigger_error('Value not supported, valid values are: ' .
                        $this->_listify($this->def->info[$namespace][$key]->allowed), E_USER_WARNING);
                    return;
                }
            }
        }
        $this->conf[$namespace][$key] = $value;
        
        // reset definitions if the directives they depend on changed
        // this is a very costly process, so it's discouraged 
        // with finalization
        if ($namespace == 'HTML' || $namespace == 'CSS') {
            $this->definitions[$namespace] = null;
        }
        
        $this->serials[$namespace] = false;
    }
    
    /**
     * Convenience function for error reporting
     */
    private function _listify($lookup) {
        $list = array();
        foreach ($lookup as $name => $b) $list[] = $name;
        return implode(', ', $list);
    }
    
    /**
     * Retrieves object reference to the HTML definition.
     * @param $raw Return a copy that has not been setup yet. Must be
     *             called before it's been setup, otherwise won't work.
     */
    public function getHTMLDefinition($raw = false) {
        return $this->getDefinition('HTML', $raw);
    }
    
    /**
     * Retrieves object reference to the CSS definition
     * @param $raw Return a copy that has not been setup yet. Must be
     *             called before it's been setup, otherwise won't work.
     */
    public function getCSSDefinition($raw = false) {
        return $this->getDefinition('CSS', $raw);
    }
    
    /**
     * Retrieves a definition
     * @param $type Type of definition: HTML, CSS, etc
     * @param $raw  Whether or not definition should be returned raw
     */
    public function getDefinition($type, $raw = false) {
        if (!$this->finalized && $this->autoFinalize) $this->finalize();
        $factory = HTMLPurifier_DefinitionCacheFactory::instance();
        $cache = $factory->create($type, $this);
        if (!$raw) {
            // see if we can quickly supply a definition
            if (!empty($this->definitions[$type])) {
                if (!$this->definitions[$type]->setup) {
                    $this->definitions[$type]->setup($this);
                    $cache->set($this->definitions[$type], $this);
                }
                return $this->definitions[$type];
            }
            // memory check missed, try cache
            $this->definitions[$type] = $cache->get($this);
            if ($this->definitions[$type]) {
                // definition in cache, return it
                return $this->definitions[$type];
            }
        } elseif (
            !empty($this->definitions[$type]) &&
            !$this->definitions[$type]->setup
        ) {
            // raw requested, raw in memory, quick return
            return $this->definitions[$type];
        }
        // quick checks failed, let's create the object
        if ($type == 'HTML') {
            $this->definitions[$type] = new HTMLPurifier_HTMLDefinition();
        } elseif ($type == 'CSS') {
            $this->definitions[$type] = new HTMLPurifier_CSSDefinition();
        } elseif ($type == 'URI') {
            $this->definitions[$type] = new HTMLPurifier_URIDefinition();
        } else {
            throw new HTMLPurifier_Exception("Definition of $type type not supported");
        }
        // quick abort if raw
        if ($raw) {
            if (is_null($this->get($type, 'DefinitionID'))) {
                // fatally error out if definition ID not set
                throw new HTMLPurifier_Exception("Cannot retrieve raw version without specifying %$type.DefinitionID");
            }
            return $this->definitions[$type];
        }
        // set it up
        $this->definitions[$type]->setup($this);
        // save in cache
        $cache->set($this->definitions[$type], $this);
        return $this->definitions[$type];
    }
    
    /**
     * Loads configuration values from an array with the following structure:
     * Namespace.Directive => Value
     * @param $config_array Configuration associative array
     */
    public function loadArray($config_array) {
        if ($this->isFinalized('Cannot load directives after finalization')) return;
        foreach ($config_array as $key => $value) {
            $key = str_replace('_', '.', $key);
            if (strpos($key, '.') !== false) {
                // condensed form
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
    
    /**
     * Returns a list of array(namespace, directive) for all directives
     * that are allowed in a web-form context as per an allowed
     * namespaces/directives list.
     * @param $allowed List of allowed namespaces/directives
     */
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
                     // directive
                     if ($ns_or_directive[0] == '-') {
                         $blacklisted_directives[substr($ns_or_directive, 1)] = true;
                     } else {
                         $allowed_directives[$ns_or_directive] = true;
                     }
                 } else {
                     // namespace
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
    
    /**
     * Loads configuration values from an ini file
     * @param $filename Name of ini file
     */
    public function loadIni($filename) {
        if ($this->isFinalized('Cannot load directives after finalization')) return;
        $array = parse_ini_file($filename, true);
        $this->loadArray($array);
    }
    
    /**
     * Checks whether or not the configuration object is finalized.
     * @param $error String error message, or false for no error
     */
    public function isFinalized($error = false) {
        if ($this->finalized && $error) {
            trigger_error($error, E_USER_ERROR);
        }
        return $this->finalized;
    }
    
    /**
     * Finalizes configuration only if auto finalize is on and not
     * already finalized
     */
    public function autoFinalize() {
        if (!$this->finalized && $this->autoFinalize) $this->finalize();
    }
    
    /**
     * Finalizes a configuration object, prohibiting further change
     */
    public function finalize() {
        $this->finalized = true;
    }
    
}

/**
 * Base class for configuration entity
 */
abstract class HTMLPurifier_ConfigDef {
    public $class = false;
}

/**
 * Configuration definition, defines directives and their defaults.
 */
class HTMLPurifier_ConfigSchema {
    
    /**
     * Defaults of the directives and namespaces.
     * @note This shares the exact same structure as HTMLPurifier_Config::$conf
     */
    public $defaults = array();
    
    /**
     * Definition of the directives.
     */
    public $info = array();
    
    /**
     * Application-wide singleton
     */
    static protected $singleton;
    
    /**
     * Variable parser.
     */
    protected $parser;
    
    public function __construct() {
        $this->parser = new HTMLPurifier_VarParser_Flexible();
    }
    
    /**
     * Unserializes the default ConfigSchema.
     */
    public static function makeFromSerial() {
        return unserialize(file_get_contents(HTMLPURIFIER_PREFIX . '/HTMLPurifier/ConfigSchema/schema.ser'));
    }
    
    /**
     * Retrieves an instance of the application-wide configuration definition.
     */
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
    
    /**
     * Defines a namespace for directives to be put into.
     * @warning This is slightly different from the corresponding static
     *          method.
     * @param $namespace Namespace's name
     */
    public function addNamespace($namespace) {
        $this->info[$namespace] = array();
        $this->defaults[$namespace] = array();
    }
    
    /**
     * Defines a directive value alias.
     * 
     */
    public function addValueAliases($namespace, $name, $aliases) {
        foreach ($aliases as $alias => $real) {
            $this->info[$namespace][$name]->aliases[$alias] = $real;
        }
    }
    
    /**
     * Defines a set of allowed values for a directive.
     */
    public function addAllowedValues($namespace, $name, $allowed) {
        $type = $this->info[$namespace][$name]->type;
        $this->info[$namespace][$name]->allowed = $allowed;
    }
    
    /**
     * Defines a directive alias for backwards compatibility
     */
    public function addAlias($namespace, $name, $new_namespace, $new_name) {
        $this->info[$namespace][$name] = new HTMLPurifier_ConfigDef_DirectiveAlias($new_namespace, $new_name);
    }
    
    // DEPRECATED METHODS
    
    /** @see HTMLPurifier_ConfigSchema->set() */
    public static function define($namespace, $name, $default, $type, $description) {
        HTMLPurifier_ConfigSchema::deprecated(__METHOD__);
        // process modifiers (OPTIMIZE!)
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
    
    /**
     * Throws an E_USER_NOTICE stating that a method is deprecated.
     */
    private static function deprecated($method) {
        trigger_error("Static HTMLPurifier_ConfigSchema::$method deprecated, use add*() method instead", E_USER_NOTICE);
    }
    
}

/**
 * @todo Unit test
 */
class HTMLPurifier_ContentSets
{
    
    /**
     * List of content set strings (pipe seperators) indexed by name.
     */
    public $info = array();
    
    /**
     * List of content set lookups (element => true) indexed by name.
     * @note This is in HTMLPurifier_HTMLDefinition->info_content_sets
     */
    public $lookup = array();
    
    /**
     * Synchronized list of defined content sets (keys of info)
     */
    protected $keys = array();
    /**
     * Synchronized list of defined content values (values of info)
     */
    protected $values = array();
    
    /**
     * Merges in module's content sets, expands identifiers in the content
     * sets and populates the keys, values and lookup member variables.
     * @param $modules List of HTMLPurifier_HTMLModule
     */
    public function __construct($modules) {
        if (!is_array($modules)) $modules = array($modules);
        // populate content_sets based on module hints
        // sorry, no way of overloading
        foreach ($modules as $module_i => $module) {
            foreach ($module->content_sets as $key => $value) {
                if (isset($this->info[$key])) {
                    // add it into the existing content set
                    $this->info[$key] = $this->info[$key] . ' | ' . $value;
                } else {
                    $this->info[$key] = $value;
                }
            }
        }
        // perform content_set expansions
        $this->keys = array_keys($this->info);
        foreach ($this->info as $i => $set) {
            // only performed once, so infinite recursion is not
            // a problem
            $this->info[$i] =
                str_replace(
                    $this->keys,
                    // must be recalculated each time due to
                    // changing substitutions
                    array_values($this->info),
                $set);
        }
        $this->values = array_values($this->info);
        
        // generate lookup tables
        foreach ($this->info as $name => $set) {
            $this->lookup[$name] = $this->convertToLookup($set);
        }
    }
    
    /**
     * Accepts a definition; generates and assigns a ChildDef for it
     * @param $def HTMLPurifier_ElementDef reference
     * @param $module Module that defined the ElementDef
     */
    public function generateChildDef(&$def, $module) {
        if (!empty($def->child)) return; // already done!
        $content_model = $def->content_model;
        if (is_string($content_model)) {
            $def->content_model = str_replace(
                $this->keys, $this->values, $content_model);
        }
        $def->child = $this->getChildDef($def, $module);
    }
    
    /**
     * Instantiates a ChildDef based on content_model and content_model_type
     * member variables in HTMLPurifier_ElementDef
     * @note This will also defer to modules for custom HTMLPurifier_ChildDef
     *       subclasses that need content set expansion
     * @param $def HTMLPurifier_ElementDef to have ChildDef extracted
     * @return HTMLPurifier_ChildDef corresponding to ElementDef
     */
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
        // defer to its module
        $return = false;
        if ($module->defines_child_def) { // save a func call
            $return = $module->getChildDef($def);
        }
        if ($return !== false) return $return;
        // error-out
        trigger_error(
            'Could not determine which ChildDef class to instantiate',
            E_USER_ERROR
        );
        return false;
    }
    
    /**
     * Converts a string list of elements separated by pipes into
     * a lookup array.
     * @param $string List of elements
     * @return Lookup array of elements
     */
    protected function convertToLookup($string) {
        $array = explode('|', str_replace(' ', '', $string));
        $ret = array();
        foreach ($array as $i => $k) {
            $ret[$k] = true;
        }
        return $ret;
    }
    
}

/**
 * Registry object that contains information about the current context.
 * @warning Is a bit buggy when variables are set to null: it thinks
 *          they don't exist! So use false instead, please.
 * @note Since the variables Context deals with may not be objects,
 *       references are very important here! Do not remove!
 */
class HTMLPurifier_Context
{
    
    /**
     * Private array that stores the references.
     */
    private $_storage = array();
    
    /**
     * Registers a variable into the context.
     * @param $name String name
     * @param $ref Reference to variable to be registered
     */
    public function register($name, &$ref) {
        if (isset($this->_storage[$name])) {
            trigger_error("Name $name produces collision, cannot re-register",
                          E_USER_ERROR);
            return;
        }
        $this->_storage[$name] =& $ref;
    }
    
    /**
     * Retrieves a variable reference from the context.
     * @param $name String name
     * @param $ignore_error Boolean whether or not to ignore error
     */
    public function &get($name, $ignore_error = false) {
        if (!isset($this->_storage[$name])) {
            if (!$ignore_error) {
                trigger_error("Attempted to retrieve non-existent variable $name",
                              E_USER_ERROR);
            }
            $var = null; // so we can return by reference
            return $var;
        }
        return $this->_storage[$name];
    }
    
    /**
     * Destorys a variable in the context.
     * @param $name String name
     */
    public function destroy($name) {
        if (!isset($this->_storage[$name])) {
            trigger_error("Attempted to destroy non-existent variable $name",
                          E_USER_ERROR);
            return;
        }
        unset($this->_storage[$name]);
    }
    
    /**
     * Checks whether or not the variable exists.
     * @param $name String name
     */
    public function exists($name) {
        return isset($this->_storage[$name]);
    }
    
    /**
     * Loads a series of variables from an associative array
     * @param $context_array Assoc array of variables to load
     */
    public function loadArray($context_array) {
        foreach ($context_array as $key => $discard) {
            $this->register($key, $context_array[$key]);
        }
    }
    
}

abstract class HTMLPurifier_DefinitionCache
{
    
    public $type;
    
    /**
     * @param $name Type of definition objects this instance of the
     *      cache will handle.
     */
    public function __construct($type) {
        $this->type = $type;
    }
    
    /**
     * Generates a unique identifier for a particular configuration
     * @param Instance of HTMLPurifier_Config
     */
    public function generateKey($config) {
        return $config->version . ',' . // possibly replace with function calls
               $config->getBatchSerial($this->type) . ',' .
               $config->get($this->type, 'DefinitionRev');
    }
    
    /**
     * Tests whether or not a key is old with respect to the configuration's
     * version and revision number.
     * @param $key Key to test
     * @param $config Instance of HTMLPurifier_Config to test against
     */
    public function isOld($key, $config) {
        if (substr_count($key, ',') < 2) return true;
        list($version, $hash, $revision) = explode(',', $key, 3);
        $compare = version_compare($version, $config->version);
        // version mismatch, is always old
        if ($compare != 0) return true;
        // versions match, ids match, check revision number
        if (
            $hash == $config->getBatchSerial($this->type) &&
            $revision < $config->get($this->type, 'DefinitionRev')
        ) return true;
        return false;
    }
    
    /**
     * Checks if a definition's type jives with the cache's type
     * @note Throws an error on failure
     * @param $def Definition object to check
     * @return Boolean true if good, false if not
     */
    public function checkDefType($def) {
        if ($def->type !== $this->type) {
            trigger_error("Cannot use definition of type {$def->type} in cache for {$this->type}");
            return false;
        }
        return true;
    }
    
    /**
     * Adds a definition object to the cache
     */
    abstract public function add($def, $config);
    
    /**
     * Unconditionally saves a definition object to the cache
     */
    abstract public function set($def, $config);
    
    /**
     * Replace an object in the cache
     */
    abstract public function replace($def, $config);
    
    /**
     * Retrieves a definition object from the cache
     */
    abstract public function get($config);
    
    /**
     * Removes a definition object to the cache
     */
    abstract public function remove($config);
    
    /**
     * Clears all objects from cache
     */
    abstract public function flush($config);
    
    /**
     * Clears all expired (older version or revision) objects from cache
     * @note Be carefuly implementing this method as flush. Flush must
     *       not interfere with other Definition types, and cleanup()
     *       should not be repeatedly called by userland code.
     */
    abstract public function cleanup($config);
    
}

/**
 * Responsible for creating definition caches.
 */
class HTMLPurifier_DefinitionCacheFactory
{
    
    protected $caches = array('Serializer' => array());
    protected $implementations = array();
    protected $decorators = array();
    
    /**
     * Initialize default decorators
     */
    public function setup() {
        $this->addDecorator('Cleanup');
    }
    
    /**
     * Retrieves an instance of global definition cache factory.
     */
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
    
    /**
     * Registers a new definition cache object
     * @param $short Short name of cache object, for reference
     * @param $long Full class name of cache object, for construction 
     */
    public function register($short, $long) {
        $this->implementations[$short] = $long;
    }
    
    /**
     * Factory method that creates a cache object based on configuration
     * @param $name Name of definitions handled by cache
     * @param $config Instance of HTMLPurifier_Config
     */
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
            // prevent infinite recursion in PHP 4
            unset($cache);
            $cache = $new_cache;
        }
        $this->caches[$method][$type] = $cache;
        return $this->caches[$method][$type];
    }
    
    /**
     * Registers a decorator to add to all new cache objects
     * @param 
     */
    public function addDecorator($decorator) {
        if (is_string($decorator)) {
            $class = "HTMLPurifier_DefinitionCache_Decorator_$decorator";
            $decorator = new $class;
        }
        $this->decorators[$decorator->name] = $decorator;
    }
    
}

/**
 * Represents a document type, contains information on which modules
 * need to be loaded.
 * @note This class is inspected by Printer_HTMLDefinition->renderDoctype.
 *       If structure changes, please update that function.
 */
class HTMLPurifier_Doctype
{
    /**
     * Full name of doctype
     */
    public $name;
    
    /**
     * List of standard modules (string identifiers or literal objects)
     * that this doctype uses
     */
    public $modules = array();
    
    /**
     * List of modules to use for tidying up code
     */
    public $tidyModules = array();
    
    /**
     * Is the language derived from XML (i.e. XHTML)?
     */
    public $xml = true;
    
    /**
     * List of aliases for this doctype
     */
    public $aliases = array();
    
    /**
     * Public DTD identifier
     */
    public $dtdPublic;
    
    /**
     * System DTD identifier
     */
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
    
    /**
     * Hash of doctype names to doctype objects
     */
    protected $doctypes;
    
    /**
     * Lookup table of aliases to real doctype names
     */
    protected $aliases;
    
    /**
     * Registers a doctype to the registry
     * @note Accepts a fully-formed doctype object, or the
     *       parameters for constructing a doctype object
     * @param $doctype Name of doctype or literal doctype object
     * @param $modules Modules doctype will load
     * @param $modules_for_modes Modules doctype will load for certain modes
     * @param $aliases Alias names for doctype
     * @return Editable registered doctype
     */
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
        // hookup aliases
        foreach ($doctype->aliases as $alias) {
            if (isset($this->doctypes[$alias])) continue;
            $this->aliases[$alias] = $name;
        }
        // remove old aliases
        if (isset($this->aliases[$name])) unset($this->aliases[$name]);
        return $doctype;
    }
    
    /**
     * Retrieves reference to a doctype of a certain name
     * @note This function resolves aliases
     * @note When possible, use the more fully-featured make()
     * @param $doctype Name of doctype
     * @return Editable doctype object
     */
    public function get($doctype) {
        if (isset($this->aliases[$doctype])) $doctype = $this->aliases[$doctype];
        if (!isset($this->doctypes[$doctype])) {
            trigger_error('Doctype ' . htmlspecialchars($doctype) . ' does not exist', E_USER_ERROR);
            $anon = new HTMLPurifier_Doctype($doctype);
            return $anon;
        }
        return $this->doctypes[$doctype];
    }
    
    /**
     * Creates a doctype based on a configuration object,
     * will perform initialization on the doctype
     * @note Use this function to get a copy of doctype that config
     *       can hold on to (this is necessary in order to tell
     *       Generator whether or not the current document is XML
     *       based or not).
     */
    public function make($config) {
        return clone $this->get($this->getDoctypeFromConfig($config));
    }
    
    /**
     * Retrieves the doctype from the configuration object
     */
    public function getDoctypeFromConfig($config) {
        // recommended test
        $doctype = $config->get('HTML', 'Doctype');
        if (!empty($doctype)) return $doctype;
        $doctype = $config->get('HTML', 'CustomDoctype');
        if (!empty($doctype)) return $doctype;
        // backwards-compatibility
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
    
    /**
     * Does the definition work by itself, or is it created solely
     * for the purpose of merging into another definition?
     */
    public $standalone = true;
    
    public $attr = array();
    
    /**
     * Indexed list of tag's HTMLPurifier_AttrTransform to be done before validation
     */
    public $attr_transform_pre = array();
    
    /**
     * Indexed list of tag's HTMLPurifier_AttrTransform to be done after validation
     */
    public $attr_transform_post = array();
    
    /**
     * HTMLPurifier_ChildDef of this tag.
     */
    public $child;
    
    /**
     * Abstract string representation of internal ChildDef rules. See
     * HTMLPurifier_ContentSets for how this is parsed and then transformed
     * into an HTMLPurifier_ChildDef.
     * @warning This is a temporary variable that is not available after
     *      being processed by HTMLDefinition
     */
    public $content_model;
    
    /**
     * Value of $child->type, used to determine which ChildDef to use,
     * used in combination with $content_model.
     * @warning This must be lowercase
     * @warning This is a temporary variable that is not available after
     *      being processed by HTMLDefinition
     */
    public $content_model_type;
    
    
    
    /**
     * Does the element have a content model (#PCDATA | Inline)*? This
     * is important for chameleon ins and del processing in 
     * HTMLPurifier_ChildDef_Chameleon. Dynamically set: modules don't
     * have to worry about this one.
     */
    public $descendants_are_inline = false;
    
    /**
     * List of the names of required attributes this element has. Dynamically
     * populated by HTMLPurifier_HTMLDefinition::getElement
     */
    public $required_attr = array();
    
    public $excludes = array();
    
    /**
     * Low-level factory constructor for creating new standalone element defs
     */
    public static function create($content_model, $content_model_type, $attr) {
        $def = new HTMLPurifier_ElementDef();
        $def->content_model = $content_model;
        $def->content_model_type = $content_model_type;
        $def->attr = $attr;
        return $def;
    }
    
    /**
     * Merges the values of another element definition into this one.
     * Values from the new element def take precedence if a value is
     * not mergeable.
     */
    public function mergeIn($def) {
        
        // later keys takes precedence
        foreach($def->attr as $k => $v) {
            if ($k === 0) {
                // merge in the includes
                // sorry, no way to override an include
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
    
    /**
     * Merges one array into another, removes values which equal false
     * @param $a1 Array by reference that is merged into
     * @param $a2 Array that merges into $a1
     */
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

/**
 * A UTF-8 specific character encoder that handles cleaning and transforming.
 * @note All functions in this class should be static.
 */
class HTMLPurifier_Encoder
{
    
    /**
     * Constructor throws fatal error if you attempt to instantiate class
     */
    private function __construct() {
        trigger_error('Cannot instantiate encoder, call methods statically', E_USER_ERROR);
    }
    
    /**
     * Error-handler that mutes errors, alternative to shut-up operator.
     */
    private static function muteErrorHandler() {}
    
    public static function cleanUTF8($str, $force_php = false) {
        
        static $non_sgml_chars = array();
        if (empty($non_sgml_chars)) {
            for ($i = 0; $i <= 31; $i++) {
                // non-SGML ASCII chars
                // save \r, \t and \n
                if ($i == 9 || $i == 13 || $i == 10) continue;
                $non_sgml_chars[chr($i)] = '';
            }
            for ($i = 127; $i <= 159; $i++) {
                $non_sgml_chars[HTMLPurifier_Encoder::unichr($i)] = '';
            }
        }
        
        static $iconv = null;
        if ($iconv === null) $iconv = function_exists('iconv');
        
        // UTF-8 validity is checked since PHP 4.3.5
        // This is an optimization: if the string is already valid UTF-8, no
        // need to do iconv/php stuff. 99% of the time, this will be the case.
        if (preg_match('/^.{1}/us', $str)) {
            return strtr($str, $non_sgml_chars);
        }
        
        if ($iconv && !$force_php) {
            // do the shortcut way
            set_error_handler(array('HTMLPurifier_Encoder', 'muteErrorHandler'));
            $str = iconv('UTF-8', 'UTF-8//IGNORE', $str);
            restore_error_handler();
            return strtr($str, $non_sgml_chars);
        }
        
        $mState = 0; // cached expected number of octets after the current octet
                     // until the beginning of the next UTF8 character sequence
        $mUcs4  = 0; // cached Unicode character
        $mBytes = 1; // cached expected number of octets in the current sequence
        
        
        $out = '';
        $char = '';
        
        $len = strlen($str);
        for($i = 0; $i < $len; $i++) {
            $in = ord($str{$i});
            $char .= $str[$i]; // append byte to char
            if (0 == $mState) {
                // When mState is zero we expect either a US-ASCII character 
                // or a multi-octet sequence.
                if (0 == (0x80 & ($in))) {
                    // US-ASCII, pass straight through.
                    if (($in <= 31 || $in == 127) && 
                        !($in == 9 || $in == 13 || $in == 10) // save \r\t\n
                    ) {
                        // control characters, remove
                    } else {
                        $out .= $char;
                    }
                    // reset
                    $char = '';
                    $mBytes = 1;
                } elseif (0xC0 == (0xE0 & ($in))) {
                    // First octet of 2 octet sequence
                    $mUcs4 = ($in);
                    $mUcs4 = ($mUcs4 & 0x1F) << 6;
                    $mState = 1;
                    $mBytes = 2;
                } elseif (0xE0 == (0xF0 & ($in))) {
                    // First octet of 3 octet sequence
                    $mUcs4 = ($in);
                    $mUcs4 = ($mUcs4 & 0x0F) << 12;
                    $mState = 2;
                    $mBytes = 3;
                } elseif (0xF0 == (0xF8 & ($in))) {
                    // First octet of 4 octet sequence
                    $mUcs4 = ($in);
                    $mUcs4 = ($mUcs4 & 0x07) << 18;
                    $mState = 3;
                    $mBytes = 4;
                } elseif (0xF8 == (0xFC & ($in))) {
                    // First octet of 5 octet sequence.
                    // 
                    // This is illegal because the encoded codepoint must be 
                    // either:
                    // (a) not the shortest form or
                    // (b) outside the Unicode range of 0-0x10FFFF.
                    // Rather than trying to resynchronize, we will carry on 
                    // until the end of the sequence and let the later error
                    // handling code catch it.
                    $mUcs4 = ($in);
                    $mUcs4 = ($mUcs4 & 0x03) << 24;
                    $mState = 4;
                    $mBytes = 5;
                } elseif (0xFC == (0xFE & ($in))) {
                    // First octet of 6 octet sequence, see comments for 5
                    // octet sequence.
                    $mUcs4 = ($in);
                    $mUcs4 = ($mUcs4 & 1) << 30;
                    $mState = 5;
                    $mBytes = 6;
                } else {
                    // Current octet is neither in the US-ASCII range nor a 
                    // legal first octet of a multi-octet sequence.
                    $mState = 0;
                    $mUcs4  = 0;
                    $mBytes = 1;
                    $char = '';
                }
            } else {
                // When mState is non-zero, we expect a continuation of the
                // multi-octet sequence
                if (0x80 == (0xC0 & ($in))) {
                    // Legal continuation.
                    $shift = ($mState - 1) * 6;
                    $tmp = $in;
                    $tmp = ($tmp & 0x0000003F) << $shift;
                    $mUcs4 |= $tmp;
                    
                    if (0 == --$mState) {
                        // End of the multi-octet sequence. mUcs4 now contains
                        // the final Unicode codepoint to be output
                        
                        // Check for illegal sequences and codepoints.
                        
                        // From Unicode 3.1, non-shortest form is illegal
                        if (((2 == $mBytes) && ($mUcs4 < 0x0080)) ||
                            ((3 == $mBytes) && ($mUcs4 < 0x0800)) ||
                            ((4 == $mBytes) && ($mUcs4 < 0x10000)) ||
                            (4 < $mBytes) ||
                            // From Unicode 3.2, surrogate characters = illegal
                            (($mUcs4 & 0xFFFFF800) == 0xD800) ||
                            // Codepoints outside the Unicode range are illegal
                            ($mUcs4 > 0x10FFFF)
                        ) {
                            
                        } elseif (0xFEFF != $mUcs4 && // omit BOM
                            !($mUcs4 >= 128 && $mUcs4 <= 159) // omit non-SGML
                        ) {
                            $out .= $char;
                        }
                        // initialize UTF8 cache (reset)
                        $mState = 0;
                        $mUcs4  = 0;
                        $mBytes = 1;
                        $char = '';
                    }
                } else {
                    // ((0xC0 & (*in) != 0x80) && (mState != 0))
                    // Incomplete multi-octet sequence.
                    // used to result in complete fail, but we'll reset
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
            // bits are set outside the "valid" range as defined
            // by UNICODE 4.1.0 
            return '';
        }
        
        $x = $y = $z = $w = 0; 
        if ($code < 128) {
            // regular ASCII character
            $x = $code;
        } else {
            // set up bits for UTF-8
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
        // set up the actual character
        $ret = '';
        if($w) $ret .= chr($w);
        if($z) $ret .= chr($z);
        if($y) $ret .= chr($y);
        $ret .= chr($x); 
        
        return $ret;
    }
    
    /**
     * Converts a string to UTF-8 based on configuration.
     */
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
    
    /**
     * Converts a string from UTF-8 based on configuration.
     * @note Currently, this is a lossy conversion, with unexpressable
     *       characters being omitted.
     */
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
            if( $bytevalue <= 0x7F ) { //0xxx xxxx
                $result .= chr( $bytevalue );
                $bytesleft = 0;
            } elseif( $bytevalue <= 0xBF ) { //10xx xxxx
                $working = $working << 6;
                $working += ($bytevalue & 0x3F);
                $bytesleft--;
                if( $bytesleft <= 0 ) {
                    $result .= "&#" . $working . ";";
                }
            } elseif( $bytevalue <= 0xDF ) { //110x xxxx
                $working = $bytevalue & 0x1F;
                $bytesleft = 1;
            } elseif( $bytevalue <= 0xEF ) { //1110 xxxx
                $working = $bytevalue & 0x0F;
                $bytesleft = 2;
            } else { //1111 0xxx
                $working = $bytevalue & 0x07;
                $bytesleft = 3;
            }
        }
        return $result;
    }
    
    
}

/**
 * Object that provides entity lookup table from entity name to character
 */
class HTMLPurifier_EntityLookup {
    
    /**
     * Assoc array of entity name to character represented.
     */
    public $table;
    
    public function setup($file = false) {
        if (!$file) {
            $file = HTMLPURIFIER_PREFIX . '/HTMLPurifier/EntityLookup/entities.ser';
        }
        $this->table = unserialize(file_get_contents($file));
    }
    
    /**
     * Retrieves sole instance of the object.
     * @param Optional prototype of custom lookup table to overload with.
     */
    public static function instance($prototype = false) {
        // no references, since PHP doesn't copy unless modified
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

/**
 * Handles referencing and derefencing character entities
 */
class HTMLPurifier_EntityParser
{
    
    /**
     * Reference to entity lookup table.
     */
    protected $_entity_lookup;
    
    /**
     * Callback regex string for parsing entities.
     */                             
    protected $_substituteEntitiesRegex =
'/&(?:[#]x([a-fA-F0-9]+)|[#]0*(\d+)|([A-Za-z_:][A-Za-z0-9.\-_:]*));?/';
//     1. hex             2. dec      3. string (XML style)
    
    
    /**
     * Decimal to parsed string conversion table for special entities.
     */
    protected $_special_dec2str =
            array(
                    34 => '"',
                    38 => '&',
                    39 => "'",
                    60 => '<',
                    62 => '>'
            );
    
    /**
     * Stripped entity names to decimal conversion table for special entities.
     */
    protected $_special_ent2dec =
            array(
                    'quot' => 34,
                    'amp'  => 38,
                    'lt'   => 60,
                    'gt'   => 62
            );
    
    /**
     * Substitutes non-special entities with their parsed equivalents. Since
     * running this whenever you have parsed character is t3h 5uck, we run
     * it before everything else.
     * 
     * @param $string String to have non-special entities parsed.
     * @returns Parsed string.
     */
    public function substituteNonSpecialEntities($string) {
        // it will try to detect missing semicolons, but don't rely on it
        return preg_replace_callback(
            $this->_substituteEntitiesRegex,
            array($this, 'nonSpecialEntityCallback'),
            $string
            );
    }
    
    /**
     * Callback function for substituteNonSpecialEntities() that does the work.
     * 
     * @param $matches  PCRE matches array, with 0 the entire match, and
     *                  either index 1, 2 or 3 set with a hex value, dec value,
     *                  or string (respectively).
     * @returns Replacement string.
     */
    
    protected function nonSpecialEntityCallback($matches) {
        // replaces all but big five
        $entity = $matches[0];
        $is_num = (@$matches[0][1] === '#');
        if ($is_num) {
            $is_hex = (@$entity[2] === 'x');
            $code = $is_hex ? hexdec($matches[1]) : (int) $matches[2];
            
            // abort for special characters
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
    
    /**
     * Substitutes only special entities with their parsed equivalents.
     * 
     * @notice We try to avoid calling this function because otherwise, it
     * would have to be called a lot (for every parsed section).
     * 
     * @param $string String to have non-special entities parsed.
     * @returns Parsed string.
     */
    public function substituteSpecialEntities($string) {
        return preg_replace_callback(
            $this->_substituteEntitiesRegex,
            array($this, 'specialEntityCallback'),
            $string);
    }
    
    /**
     * Callback function for substituteSpecialEntities() that does the work.
     * 
     * This callback has same syntax as nonSpecialEntityCallback().
     * 
     * @param $matches  PCRE-style matches array, with 0 the entire match, and
     *                  either index 1, 2 or 3 set with a hex value, dec value,
     *                  or string (respectively).
     * @returns Replacement string.
     */
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

/**
 * Error collection class that enables HTML Purifier to report HTML
 * problems back to the user
 */
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
    
    /**
     * Sends an error message to the collector for later use
     * @param $line Integer line number, or HTMLPurifier_Token that caused error
     * @param $severity int Error severity, PHP error style (don't use E_USER_)
     * @param $msg string Error message text
     */
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
        
        // perform special substitutions, also add custom parameters
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
    
    /**
     * Retrieves raw error data for custom formatter to use
     * @param List of arrays in format of array(Error message text,
     *        token that caused error, tokens surrounding token)
     */
    public function getRaw() {
        return $this->errors;
    }
    
    /**
     * Default HTML formatting implementation for error messages
     * @param $config Configuration array, vital for HTML output nature
     */
    public function getHTMLFormatted($config) {
        $ret = array();
        
        $errors = $this->errors;
        
        // sort error array by line
        // line numbers are enabled if they aren't explicitly disabled
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
                // have javascript link generation that causes 
                // textarea to skip to the specified line
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

/**
 * Global exception class for HTML Purifier; any exceptions we throw
 * are from here.
 */
class HTMLPurifier_Exception extends Exception
{
    
}

class HTMLPurifier_Filter
{
    
    /**
     * Name of the filter for identification purposes
     */
    public $name;
    
    /**
     * Pre-processor function, handles HTML before HTML Purifier 
     */
    public function preFilter($html, $config, $context) {
        return $html;
    }
    
    /**
     * Post-processor function, handles HTML after HTML Purifier
     */
    public function postFilter($html, $config, $context) {
        return $html;
    }
    
}

/**
 * Generates HTML from tokens.
 * @todo Refactor interface so that configuration/context is determined
 *       upon instantiation, no need for messy generateFromTokens() calls
 * @todo Make some of the more internal functions protected, and have
 *       unit tests work around that
 */
class HTMLPurifier_Generator
{
    
    /**
     * Whether or not generator should produce XML output
     */
    private $_xhtml = true;
    
    /**
     * :HACK: Whether or not generator should comment the insides of <script> tags
     */
    private $_scriptFix = false;
    
    /**
     * Cache of HTMLDefinition during HTML output to determine whether or
     * not attributes should be minimized.
     */
    private $_def;
    
    /**
     * Configuration for the generator
     */
    protected $config;
    
    /**
     * @param $config Instance of HTMLPurifier_Config
     * @param $context Instance of HTMLPurifier_Context
     */
    public function __construct($config = null, $context = null) {
        if (!$config) $config = HTMLPurifier_Config::createDefault();
        $this->config = $config;
        $this->_scriptFix = $config->get('Output', 'CommentScriptContents');
        $this->_def = $config->getHTMLDefinition();
        $this->_xhtml = $this->_def->doctype->xml;
    }
    
    /**
     * Generates HTML from an array of tokens.
     * @param $tokens Array of HTMLPurifier_Token
     * @param $config HTMLPurifier_Config object
     * @return Generated HTML
     */
    public function generateFromTokens($tokens) {
        if (!$tokens) return '';
        
        // Basic algorithm
        $html = '';
        for ($i = 0, $size = count($tokens); $i < $size; $i++) {
            if ($this->_scriptFix && $tokens[$i]->name === 'script'
                && $i + 2 < $size && $tokens[$i+2] instanceof HTMLPurifier_Token_End) {
                // script special case
                // the contents of the script block must be ONE token
                // for this to work.
                $html .= $this->generateFromToken($tokens[$i++]);
                $html .= $this->generateScriptFromToken($tokens[$i++]);
            }
            $html .= $this->generateFromToken($tokens[$i]);
        }
        
        // Tidy cleanup
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
            $html = (string) $tidy; // explicit cast necessary
        }
        
        // Normalize newlines to system defined value
        $nl = $this->config->get('Output', 'Newline');
        if ($nl === null) $nl = PHP_EOL;
        if ($nl !== "\n") $html = str_replace("\n", $nl, $html);
        return $html;
    }
    
    /**
     * Generates HTML from a single token.
     * @param $token HTMLPurifier_Token object.
     * @return Generated HTML
     */
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
                ( $this->_xhtml ? ' /': '' ) // <br /> v. <br>
                . '>';
            
        } elseif ($token instanceof HTMLPurifier_Token_Text) {
            return $this->escape($token->data, ENT_NOQUOTES);
            
        } elseif ($token instanceof HTMLPurifier_Token_Comment) {
            return '<!--' . $token->data . '-->';
        } else {
            return '';
            
        }
    }
    
    /**
     * Special case processor for the contents of script tags
     * @warning This runs into problems if there's already a literal
     *          --> somewhere inside the script contents.
     */
    public function generateScriptFromToken($token) {
        if (!$token instanceof HTMLPurifier_Token_Text) return $this->generateFromToken($token);
        // Thanks <http://lachy.id.au/log/2005/05/script-comments>
        $data = preg_replace('#//\s*$#', '', $token->data);
        return '<!--//--><![CDATA[//><!--' . "\n" . trim($data) . "\n" . '//--><!]]>';
    }
    
    /**
     * Generates attribute declarations from attribute array.
     * @note This does not include the leading or trailing space.
     * @param $assoc_array_of_attributes Attribute array
     * @param $element Name of element attributes are for, used to check
     *        attribute minimization.
     * @return Generate HTML fragment for insertion.
     */
    public function generateAttributes($assoc_array_of_attributes, $element = false) {
        $html = '';
        foreach ($assoc_array_of_attributes as $key => $value) {
            if (!$this->_xhtml) {
                // Remove namespaced attributes
                if (strpos($key, ':') !== false) continue;
                // Check if we should minimize the attribute: val="val" -> val
                if ($element && !empty($this->_def->info[$element]->attr[$key]->minimized)) {
                    $html .= $key . ' ';
                    continue;
                }
            }
            $html .= $key.'="'.$this->escape($value).'" ';
        }
        return rtrim($html);
    }
    
    /**
     * Escapes raw text data.
     * @todo This really ought to be protected, but until we have a facility
     *       for properly generating HTML here w/o using tokens, it stays
     *       public.
     * @param $string String data to escape for HTML.
     * @param $quote Quoting style, like htmlspecialchars. ENT_NOQUOTES is
     *               permissible for non-attribute output.
     * @return String escaped data.
     */
    public function escape($string, $quote = ENT_COMPAT) {
        return htmlspecialchars($string, $quote, 'UTF-8');
    }
    
}

class HTMLPurifier_HTMLDefinition extends HTMLPurifier_Definition
{
    
    /**
     * Associative array of element names to HTMLPurifier_ElementDef
     */
    public $info = array();
    
    /**
     * Associative array of global attribute name to attribute definition.
     */
    public $info_global_attr = array();
    
    /**
     * String name of parent element HTML will be going into.
     */
    public $info_parent = 'div';
    
    /**
     * Definition for parent element, allows parent element to be a
     * tag that's not allowed inside the HTML fragment.
     */
    public $info_parent_def;
    
    /**
     * String name of element used to wrap inline elements in block context
     * @note This is rarely used except for BLOCKQUOTEs in strict mode
     */
    public $info_block_wrapper = 'p';
    
    /**
     * Associative array of deprecated tag name to HTMLPurifier_TagTransform
     */
    public $info_tag_transform = array();
    
    /**
     * Indexed list of HTMLPurifier_AttrTransform to be performed before validation.
     */
    public $info_attr_transform_pre = array();
    
    /**
     * Indexed list of HTMLPurifier_AttrTransform to be performed after validation.
     */
    public $info_attr_transform_post = array();
    
    /**
     * Nested lookup array of content set name (Block, Inline) to
     * element name to whether or not it belongs in that content set.
     */
    public $info_content_sets = array();
    
    /**
     * Doctype object
     */
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
    
    /**
     * Adds a custom element to your HTML definition
     * @note See HTMLPurifier_HTMLModule::addElement for detailed 
     *       parameter and return value descriptions.
     */
    public function addElement($element_name, $type, $contents, $attr_collections, $attributes) {
        $module = $this->getAnonymousModule();
        // assume that if the user is calling this, the element
        // is safe. This may not be a good idea
        $element = $module->addElement($element_name, $type, $contents, $attr_collections, $attributes);
        return $element;
    }
    
    /**
     * Adds a blank element to your HTML definition, for overriding
     * existing behavior
     * @note See HTMLPurifier_HTMLModule::addBlankElement for detailed
     *       parameter and return value descriptions.
     */
    public function addBlankElement($element_name) {
        $module  = $this->getAnonymousModule();
        $element = $module->addBlankElement($element_name);
        return $element;
    }
    
    /**
     * Retrieves a reference to the anonymous module, so you can
     * bust out advanced features without having to make your own
     * module.
     */
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
    
    /**
     * Performs low-cost, preliminary initialization.
     */
    public function __construct() {
        $this->manager = new HTMLPurifier_HTMLModuleManager();
    }
    
    protected function doSetup($config) {
        $this->processModules($config);
        $this->setupConfigStuff($config);
        unset($this->manager);
        
        // cleanup some of the element definitions
        foreach ($this->info as $k => $v) {
            unset($this->info[$k]->content_model);
            unset($this->info[$k]->content_model_type);
        }
    }
    
    /**
     * Extract out the information from the manager
     */
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
    
    /**
     * Sets up stuff based on config. We need a better way of doing this.
     */
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
        
        // support template text
        $support = "(for information on implementing this, see the ".
                   "support forums) ";
        
        // setup allowed elements -----------------------------------------
        
        $allowed_elements = $config->get('HTML', 'AllowedElements');
        $allowed_attributes = $config->get('HTML', 'AllowedAttributes'); // retrieve early
        
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
            // emit errors
            foreach ($allowed_elements as $element => $d) {
                $element = htmlspecialchars($element); // PHP doesn't escape errors, be careful!
                trigger_error("Element '$element' is not supported $support", E_USER_WARNING);
            }
        }
        
        // setup allowed attributes ---------------------------------------
        
        $allowed_attributes_mutable = $allowed_attributes; // by copy!
        if (is_array($allowed_attributes)) {
            
            // This actually doesn't do anything, since we went away from
            // global attributes. It's possible that userland code uses
            // it, but HTMLModuleManager doesn't!
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
            // emit errors
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
                        // otherwise fall through
                    case 1:
                        $attribute = htmlspecialchars($bits[0]);
                        trigger_error("Global attribute '$attribute' is not ".
                            "supported in any elements $support",
                            E_USER_WARNING);
                        break;
                }
            }
            
        }
        
        // setup forbidden elements ---------------------------------------
        
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
                } // this segment might get removed eventually
                elseif (isset($forbidden_attributes["$tag.$attr"])) {
                    // $tag.$attr are not user supplied, so no worries!
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
    
    /**
     * Parses a TinyMCE-flavored Allowed Elements and Attributes list into
     * separate lists for processing. Format is element[attr1|attr2],element2...
     * @warning Although it's largely drawn from TinyMCE's implementation,
     *      it is different, and you'll probably have to modify your lists
     * @param $list String list to parse
     * @param array($allowed_elements, $allowed_attributes)
     * @todo Give this its own class, probably static interface
     */
    public function parseTinyMCEAllowedList($list) {
        
        $list = str_replace(array(' ', "\t"), '', $list);
        
        $elements = array();
        $attributes = array();
        
        $chunks = preg_split('/(,|[\n\r]+)/', $list);
        foreach ($chunks as $chunk) {
            if (empty($chunk)) continue;
            // remove TinyMCE element control characters
            if (!strpos($chunk, '[')) {
                $element = $chunk;
                $attr = false;
            } else {
                list($element, $attr) = explode('[', $chunk);
            }
            if ($element !== '*') $elements[$element] = true;
            if (!$attr) continue;
            $attr = substr($attr, 0, strlen($attr) - 1); // remove trailing ]
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
    
    // -- Overloadable ----------------------------------------------------
    
    /**
     * Short unique string identifier of the module
     */
    public $name;
    
    /**
     * Informally, a list of elements this module changes. Not used in
     * any significant way.
     */
    public $elements = array();
    
    /**
     * Associative array of element names to element definitions.
     * Some definitions may be incomplete, to be merged in later
     * with the full definition.
     */
    public $info = array();
    
    /**
     * Associative array of content set names to content set additions.
     * This is commonly used to, say, add an A element to the Inline
     * content set. This corresponds to an internal variable $content_sets
     * and NOT info_content_sets member variable of HTMLDefinition.
     */
    public $content_sets = array();
    
    public $attr_collections = array();
    
    /**
     * Associative array of deprecated tag name to HTMLPurifier_TagTransform
     */
    public $info_tag_transform = array();
    
    /**
     * List of HTMLPurifier_AttrTransform to be performed before validation.
     */
    public $info_attr_transform_pre = array();
    
    /**
     * List of HTMLPurifier_AttrTransform to be performed after validation.
     */
    public $info_attr_transform_post = array();
    
    /**
     * Boolean flag that indicates whether or not getChildDef is implemented.
     * For optimization reasons: may save a call to a function. Be sure
     * to set it if you do implement getChildDef(), otherwise it will have
     * no effect!
     */
    public $defines_child_def = false;
    
    /**
     * Boolean flag whether or not this module is safe. If it is not safe, all
     * of its members are unsafe. Modules are safe by default (this might be
     * slightly dangerous, but it doesn't make much sense to force HTML Purifier,
     * which is based off of safe HTML, to explicitly say, "This is safe," even
     * though there are modules which are "unsafe")
     * 
     * @note Previously, safety could be applied at an element level granularity.
     *       We've removed this ability, so in order to add "unsafe" elements
     *       or attributes, a dedicated module with this property set to false
     *       must be used.
     */
    public $safe = true;
    
    public function getChildDef($def) {return false;}
    
    public function addElement($element, $type, $contents, $attr_includes = array(), $attr = array()) {
        $this->elements[] = $element;
        // parse content_model
        list($content_model_type, $content_model) = $this->parseContents($contents);
        // merge in attribute inclusions
        $this->mergeInAttrIncludes($attr, $attr_includes);
        // add element to content sets
        if ($type) $this->addElementToContentSet($element, $type);
        // create element
        $this->info[$element] = HTMLPurifier_ElementDef::create(
            $content_model, $content_model_type, $attr
        );
        // literal object $contents means direct child manipulation
        if (!is_string($contents)) $this->info[$element]->child = $contents;
        return $this->info[$element];
    }
    
    /**
     * Convenience function that creates a totally blank, non-standalone
     * element.
     * @param $element Name of element to create
     * @return Created element
     */
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
    
    /**
     * Convenience function that registers an element to a content set
     * @param Element to register
     * @param Name content set (warning: case sensitive, usually upper-case
     *        first letter)
     */
    public function addElementToContentSet($element, $type) {
        if (!isset($this->content_sets[$type])) $this->content_sets[$type] = '';
        else $this->content_sets[$type] .= ' | ';
        $this->content_sets[$type] .= $element;
    }
    
    public function parseContents($contents) {
        if (!is_string($contents)) return array(null, null); // defer
        switch ($contents) {
            // check for shorthand content model forms
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
    
    /**
     * Convenience function that merges a list of attribute includes into
     * an attribute array.
     * @param $attr Reference to attr array to modify
     * @param $attr_includes Array of includes / string include to merge in
     */
    public function mergeInAttrIncludes(&$attr, $attr_includes) {
        if (!is_array($attr_includes)) {
            if (empty($attr_includes)) $attr_includes = array();
            else $attr_includes = array($attr_includes);
        }
        $attr[0] = $attr_includes;
    }
    
    /**
     * Convenience function that generates a lookup table with boolean
     * true as value.
     * @param $list List of values to turn into a lookup
     * @note You can also pass an arbitrary number of arguments in
     *       place of the regular argument
     * @return Lookup array equivalent of list
     */
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
    
    /**
     * Instance of HTMLPurifier_DoctypeRegistry
     */
    public $doctypes;
    
    /**
     * Instance of current doctype
     */
    public $doctype;
    
    /**
     * Instance of HTMLPurifier_AttrTypes
     */
    public $attrTypes;
    
    /**
     * Active instances of modules for the specified doctype are
     * indexed, by name, in this array.
     */
    public $modules = array();
    
    /**
     * Array of recognized HTMLPurifier_Module instances, indexed by 
     * module's class name. This array is usually lazy loaded, but a
     * user can overload a module by pre-emptively registering it.
     */
    public $registeredModules = array();
    
    /**
     * List of extra modules that were added by the user using addModule().
     * These get unconditionally merged into the current doctype, whatever
     * it may be.
     */
    public $userModules = array();
    
    /**
     * Associative array of element name to list of modules that have
     * definitions for the element; this array is dynamically filled.
     */
    public $elementLookup = array();
    
    /** List of prefixes we should use for registering small names */
    public $prefixes = array('HTMLPurifier_HTMLModule_');
    
    public $contentSets;     /**< Instance of HTMLPurifier_ContentSets */
    public $attrCollections; /**< Instance of HTMLPurifier_AttrCollections */
    
    /** If set to true, unsafe elements and attributes will be allowed */
    public $trusted = false;
    
    public function __construct() {
        
        // editable internal objects
        $this->attrTypes = new HTMLPurifier_AttrTypes();
        $this->doctypes  = new HTMLPurifier_DoctypeRegistry();
        
        // setup basic modules
        $common = array(
            'CommonAttributes', 'Text', 'Hypertext', 'List',
            'Presentation', 'Edit', 'Bdo', 'Tables', 'Image',
            'StyleAttribute', 'Scripting', 'Object'
        );
        $transitional = array('Legacy', 'Target');
        $xml = array('XMLCommonAttributes');
        $non_xml = array('NonXMLCommonAttributes');
        
        // setup basic doctypes
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
            array('Tidy_Strict', 'Tidy_XHTML', 'Tidy_Proprietary', 'Tidy_Strict'), // Tidy_XHTML1_1
            array(),
            '-//W3C//DTD XHTML 1.1//EN',
            'http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd'
        );
        
    }
    
    public function registerModule($module, $overload = false) {
        if (is_string($module)) {
            // attempt to load the module
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
    
    /**
     * Adds a module to the current doctype by first registering it,
     * and then tacking it on to the active doctype
     */
    public function addModule($module) {
        $this->registerModule($module);
        if (is_object($module)) $module = $module->name;
        $this->userModules[] = $module;
    }
    
    /**
     * Adds a class prefix that registerModule() will use to resolve a
     * string name to a concrete class
     */
    public function addPrefix($prefix) {
        $this->prefixes[] = $prefix;
    }
    
    /**
     * Performs processing on modules, after being called you may
     * use getElement() and getElements()
     * @param $config Instance of HTMLPurifier_Config
     */
    public function setup($config) {
        
        $this->trusted = $config->get('HTML', 'Trusted');
        
        // generate
        $this->doctype = $this->doctypes->make($config);
        $modules = $this->doctype->modules;
        
        // take out the default modules that aren't allowed
        $lookup = $config->get('HTML', 'AllowedModules');
        $special_cases = $config->get('HTML', 'CoreModules');
        
        if (is_array($lookup)) {
            foreach ($modules as $k => $m) {
                if (isset($special_cases[$m])) continue;
                if (!isset($lookup[$m])) unset($modules[$k]);
            }
        }
        
        // merge in custom modules
        $modules = array_merge($modules, $this->userModules);
        
        // add proprietary module (this gets special treatment because
        // it is completely removed from doctypes, etc.)
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
        
        // setup lookup table based on all valid modules
        foreach ($this->modules as $module) {
            foreach ($module->info as $name => $def) {
                if (!isset($this->elementLookup[$name])) {
                    $this->elementLookup[$name] = array();
                }
                $this->elementLookup[$name][] = $module->name;
            }
        }
        
        // note the different choice
        $this->contentSets = new HTMLPurifier_ContentSets(
            // content set assembly deals with all possible modules,
            // not just ones deemed to be "safe"
            $this->modules
        );
        $this->attrCollections = new HTMLPurifier_AttrCollections(
            $this->attrTypes,
            // there is no way to directly disable a global attribute,
            // but using AllowedAttributes or simply not including
            // the module in your custom doctype should be sufficient
            $this->modules
        );
    }
    
    /**
     * Takes a module and adds it to the active module collection,
     * registering it if necessary.
     */
    public function processModule($module) {
        if (!isset($this->registeredModules[$module]) || is_object($module)) {
            $this->registerModule($module);
        }
        $this->modules[$module] = $this->registeredModules[$module];
    }
    
    /**
     * Retrieves merged element definitions.
     * @return Array of HTMLPurifier_ElementDef
     */
    public function getElements() {
        
        $elements = array();
        foreach ($this->modules as $module) {
            if (!$this->trusted && !$module->safe) continue;
            foreach ($module->info as $name => $v) {
                if (isset($elements[$name])) continue;
                $elements[$name] = $this->getElement($name);
            }
        }
        
        // remove dud elements, this happens when an element that
        // appeared to be safe actually wasn't
        foreach ($elements as $n => $v) {
            if ($v === false) unset($elements[$n]);
        }
        
        return $elements;
        
    }
    
    public function getElement($name, $trusted = null) {
        
        if (!isset($this->elementLookup[$name])) {
            return false;
        }
        
        // setup global state variables
        $def = false;
        if ($trusted === null) $trusted = $this->trusted;
        
        // iterate through each module that has registered itself to this
        // element
        foreach($this->elementLookup[$name] as $module_name) {
            
            $module = $this->modules[$module_name];
            
            // refuse to create/merge from a module that is deemed unsafe--
            // pretend the module doesn't exist--when trusted mode is not on.
            if (!$trusted && !$module->safe) {
                continue;
            }
            
            // clone is used because, ideally speaking, the original
            // definition should not be modified. Usually, this will
            // make no difference, but for consistency's sake
            $new_def = clone $module->info[$name];
            
            if (!$def && $new_def->standalone) {
                $def = $new_def;
            } elseif ($def) {
                // This will occur even if $new_def is standalone. In practice,
                // this will usually result in a full replacement.
                $def->mergeIn($new_def);
            } else {
                // :TODO:
                // non-standalone definitions that don't have a standalone
                // to merge into could be deferred to the end
                continue;
            }
            
            // attribute value expansions
            $this->attrCollections->performInclusions($def->attr);
            $this->attrCollections->expandIdentifiers($def->attr, $this->attrTypes);
            
            // descendants_are_inline, for ChildDef_Chameleon
            if (is_string($def->content_model) &&
                strpos($def->content_model, 'Inline') !== false) {
                if ($name != 'del' && $name != 'ins') {
                    // this is for you, ins/del
                    $def->descendants_are_inline = true;
                }
            }
            
            $this->contentSets->generateChildDef($def, $module);
        }
            
        // add information on required attributes
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
    
    /**
     * Lookup table of IDs we've accumulated.
     * @public
     */
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
    
    /**
     * Advisory name of injector, this is for friendly error messages
     */
    public $name;
    
    /**
     * Amount of tokens the injector needs to skip + 1. Because
     * the decrement is the first thing that happens, this needs to
     * be one greater than the "real" skip count.
     */
    public $skip = 1;
    
    /**
     * Instance of HTMLPurifier_HTMLDefinition
     */
    protected $htmlDefinition;
    
    /**
     * Reference to CurrentNesting variable in Context. This is an array
     * list of tokens that we are currently "inside"
     */
    protected $currentNesting;
    
    /**
     * Reference to InputTokens variable in Context. This is an array
     * list of the input tokens that are being processed.
     */
    protected $inputTokens;
    
    /**
     * Reference to InputIndex variable in Context. This is an integer
     * array index for $this->inputTokens that indicates what token
     * is currently being processed.
     */
    protected $inputIndex;
    
    /**
     * Array of elements and attributes this injector creates and therefore
     * need to be allowed by the definition. Takes form of
     * array('element' => array('attr', 'attr2'), 'element2')
     */
    public $needed = array();
    
    public function prepare($config, $context) {
        $this->htmlDefinition = $config->getHTMLDefinition();
        // perform $needed checks
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
    
    /**
     * Tests if the context node allows a certain element
     * @param $name Name of element to test for
     * @return True if element is allowed, false if it is not
     */
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
    
    /**
     * Handler that is called when a text token is processed
     */
    public function handleText(&$token) {}
    
    /**
     * Handler that is called when a start or empty token is processed
     */
    public function handleElement(&$token) {}
    
    /**
     * Notifier that is called when an end token is processed
     * @note This differs from handlers in that the token is read-only
     */
    public function notifyEnd($token) {}
    
    
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
            // auto-detection algorithm
            
            // once PHP DOM implements native line numbers, or we
            // hack out something using XSLT, remove this stipulation
            $line_numbers = $config->get('Core', 'MaintainLineNumbers');
            if (
                $line_numbers === true ||
                ($line_numbers === null && $config->get('Core', 'CollectErrors'))
            ) {
                $lexer = 'DirectLex';
                break;
            }
            
            if (class_exists('DOMDocument')) {
                // check for DOM support, because, surprisingly enough,
                // it's *not* part of the core!
                $lexer = 'DOMLex';
            } else {
                $lexer = 'DirectLex';
            }
            
        } while(0); } // do..while so we can break
        
        // instantiate recognized string names
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
    
    // -- CONVENIENCE MEMBERS ---------------------------------------------
    
    public function __construct() {
        $this->_entity_parser = new HTMLPurifier_EntityParser();
    }
    
    /**
     * Most common entity to raw value conversion table for special entities.
     */
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
        
        // following functions require at least one character
        if ($string === '') return '';
        
        // subtracts amps that cannot possibly be escaped
        $num_amp = substr_count($string, '&') - substr_count($string, '& ') -
            ($string[strlen($string)-1] === '&' ? 1 : 0);
        
        if (!$num_amp) return $string; // abort if no entities
        $num_esc_amp = substr_count($string, '&amp;');
        $string = strtr($string, $this->_special_entity2str);
        
        // code duplication for sake of optimization, see above
        $num_amp_2 = substr_count($string, '&') - substr_count($string, '& ') -
            ($string[strlen($string)-1] === '&' ? 1 : 0);
        
        if ($num_amp_2 <= $num_esc_amp) return $string;
        
        // hmm... now we have some uncommon entities. Use the callback.
        $string = $this->_entity_parser->substituteSpecialEntities($string);
        return $string;
    }
    
    /**
     * Lexes an HTML string into tokens.
     * 
     * @param $string String HTML.
     * @return HTMLPurifier_Token array representation of HTML.
     */
    public function tokenizeHTML($string, $config, $context) {
        trigger_error('Call to abstract class', E_USER_ERROR);
    }
    
    /**
     * Translates CDATA sections into regular sections (through escaping).
     * 
     * @param $string HTML string to process.
     * @returns HTML with CDATA sections escaped.
     */
    protected static function escapeCDATA($string) {
        return preg_replace_callback(
            '/<!\[CDATA\[(.+?)\]\]>/s',
            array('HTMLPurifier_Lexer', 'CDATACallback'),
            $string
        );
    }
    
    /**
     * Special CDATA case that is especially convoluted for <script>
     */
    protected static function escapeCommentedCDATA($string) {
        return preg_replace_callback(
            '#<!--//--><!\[CDATA\[//><!--(.+?)//--><!\]\]>#s',
            array('HTMLPurifier_Lexer', 'CDATACallback'),
            $string
        );
    }
    
    /**
     * Callback function for escapeCDATA() that does the work.
     * 
     * @warning Though this is public in order to let the callback happen,
     *          calling it directly is not recommended.
     * @params $matches PCRE matches array, with index 0 the entire match
     *                  and 1 the inside of the CDATA section.
     * @returns Escaped internals of the CDATA section.
     */
    protected static function CDATACallback($matches) {
        // not exactly sure why the character set is needed, but whatever
        return htmlspecialchars($matches[1], ENT_COMPAT, 'UTF-8');
    }
    
    /**
     * Takes a piece of HTML and normalizes it by converting entities, fixing
     * encoding, extracting bits, and other good stuff.
     * @todo Consider making protected
     */
    public function normalize($html, $config, $context) {
        
        // extract body from document if applicable
        if ($config->get('Core', 'ConvertDocumentToFragment')) {
            $html = $this->extractBody($html);
        }
        
        // normalize newlines to \n
        $html = str_replace("\r\n", "\n", $html);
        $html = str_replace("\r", "\n", $html);
        
        if ($config->get('HTML', 'Trusted')) {
            // escape convoluted CDATA
            $html = $this->escapeCommentedCDATA($html);
        }
        
        // escape CDATA
        $html = $this->escapeCDATA($html);
        
        // expand entities that aren't the big five
        $html = $this->_entity_parser->substituteNonSpecialEntities($html);
        
        // clean into wellformed UTF-8 string for an SGML context: this has
        // to be done after entity expansion because the entities sometimes
        // represent non-SGML characters (horror, horror!)
        $html = HTMLPurifier_Encoder::cleanUTF8($html);
        
        return $html;
    }
    
    /**
     * Takes a string of HTML (fragment or document) and returns the content
     * @todo Consider making protected
     */
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


/**
 * Class that handles operations involving percent-encoding in URIs.
 *
 * @warning
 *      Be careful when reusing instances of PercentEncoder. The object
 *      you use for normalize() SHOULD NOT be used for encode(), or
 *      vice-versa.
 */
class HTMLPurifier_PercentEncoder
{
    
    /**
     * Reserved characters to preserve when using encode().
     */
    protected $preserve = array();
    
    /**
     * String of characters that should be preserved while using encode().
     */
    public function __construct($preserve = false) {
        // unreserved letters, ought to const-ify
        for ($i = 48; $i <= 57;  $i++) $this->preserve[$i] = true; // digits
        for ($i = 65; $i <= 90;  $i++) $this->preserve[$i] = true; // upper-case
        for ($i = 97; $i <= 122; $i++) $this->preserve[$i] = true; // lower-case
        $this->preserve[45] = true; // Dash         -
        $this->preserve[46] = true; // Period       .
        $this->preserve[95] = true; // Underscore   _
        $this->preserve[126]= true; // Tilde        ~
        
        // extra letters not to escape
        if ($preserve !== false) {
            for ($i = 0, $c = strlen($preserve); $i < $c; $i++) {
                $this->preserve[ord($preserve[$i])] = true;
            }
        }
    }
    
    /**
     * Our replacement for urlencode, it encodes all non-reserved characters,
     * as well as any extra characters that were instructed to be preserved.
     * @note
     *      Assumes that the string has already been normalized, making any
     *      and all percent escape sequences valid. Percents will not be
     *      re-escaped, regardless of their status in $preserve
     * @param $string String to be encoded
     * @return Encoded string.
     */
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
    
    /**
     * Fix up percent-encoding by decoding unreserved characters and normalizing.
     * @warning This function is affected by $preserve, even though the
     *          usual desired behavior is for this not to preserve those
     *          characters. Be careful when reusing instances of PercentEncoder!
     * @param $string String to normalize
     */
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




/**
 * Supertype for classes that define a strategy for modifying/purifying tokens.
 * 
 * While HTMLPurifier's core purpose is fixing HTML into something proper, 
 * strategies provide plug points for extra configuration or even extra
 * features, such as custom tags, custom parsing of text, etc.
 */

 
abstract class HTMLPurifier_Strategy
{
    
    /**
     * Executes the strategy on the tokens.
     * 
     * @param $tokens Array of HTMLPurifier_Token objects to be operated on.
     * @param $config Configuration options
     * @returns Processed array of token objects.
     */
    abstract public function execute($tokens, $config, $context);
    
}


/**
 * This is in almost every respect equivalent to an array except
 * that it keeps track of which keys were accessed.
 *
 * @warning For the sake of backwards compatibility with early versions
 *     of PHP 5, you must not use the $hash[$key] syntax; if you do
 *     our version of offsetGet is never called.
 */
class HTMLPurifier_StringHash extends ArrayObject
{
    protected $accessed = array();
    
    /**
     * Retrieves a value, and logs the access.
     */
    public function offsetGet($index) {
        $this->accessed[$index] = true;
        return parent::offsetGet($index);
    }
    
    /**
     * Returns a lookup array of all array indexes that have been accessed.
     * @return Array in form array($index => true).
     */
    public function getAccessed() {
        return $this->accessed;
    }
    
    /**
     * Resets the access array.
     */
    public function resetAccessed() {
        $this->accessed = array();
    }
}

class HTMLPurifier_StringHashParser
{
    
    public $default = 'ID';
    
    /**
     * Parses a file that contains a single string-hash.
     */
    public function parseFile($file) {
        if (!file_exists($file)) return false;
        $fh = fopen($file, 'r');
        if (!$fh) return false;
        $ret = $this->parseHandle($fh);
        fclose($fh);
        return $ret;
    }
    
    /**
     * Parses a file that contains multiple string-hashes delimited by '----'
     */
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
    
    /**
     * Internal parser that acepts a file handle.
     * @note While it's possible to simulate in-memory parsing by using
     *       custom stream wrappers, if such a use-case arises we should
     *       factor out the file handle into its own class.
     * @param $fh File handle with pointer at start of valid string-hash
     *            block.
     */
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
                // Multiline declaration
                $state = trim($line, '- ');
                continue;
            } elseif (!$state) {
                $single = true;
                if (strpos($line, ':') !== false) {
                    // Single-line declaration
                    list($state, $line) = explode(': ', $line, 2);
                } else {
                    // Use default declaration
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

/**
 * Defines a mutation of an obsolete tag into a valid tag.
 */
abstract class HTMLPurifier_TagTransform
{
    
    /**
     * Tag name to transform the tag to.
     */
    public $transform_to;
    
    /**
     * Transforms the obsolete tag into the valid tag.
     * @param $tag Tag to be transformed.
     * @param $config Mandatory HTMLPurifier_Config object
     * @param $context Mandatory HTMLPurifier_Context object
     */
    abstract public function transform($tag, $config, $context);
    
    /**
     * Prepends CSS properties to the style attribute, creating the
     * attribute if it doesn't exist.
     * @warning Copied over from AttrTransform, be sure to keep in sync
     * @param $attr Attribute array to process (passed by reference)
     * @param $css CSS to prepend
     */
    protected function prependCSS(&$attr, $css) {
        $attr['style'] = isset($attr['style']) ? $attr['style'] : '';
        $attr['style'] = $css . $attr['style'];
    }
    
}

/**
 * Abstract base token class that all others inherit from.
 */
class HTMLPurifier_Token {
    public $type; /**< Type of node to bypass <tt>is_a()</tt>. */
    public $line; /**< Line number node was on in source document. Null if unknown. */
    
    /**
     * Lookup array of processing that this token is exempt from.
     * Currently, valid values are "ValidateAttributes" and
     * "MakeWellFormed_TagClosedError"
     */
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
    
    /**
     * Prototypes that will be cloned.
     * @private
     */
    // p stands for prototype
    private $p_start, $p_end, $p_empty, $p_text, $p_comment;
    
    /**
     * Generates blank prototypes for cloning.
     */
    public function __construct() {
        $this->p_start  = new HTMLPurifier_Token_Start('', array());
        $this->p_end    = new HTMLPurifier_Token_End('');
        $this->p_empty  = new HTMLPurifier_Token_Empty('', array());
        $this->p_text   = new HTMLPurifier_Token_Text('');
        $this->p_comment= new HTMLPurifier_Token_Comment('');
    }
    
    /**
     * Creates a HTMLPurifier_Token_Start.
     * @param $name Tag name
     * @param $attr Associative array of attributes
     * @return Generated HTMLPurifier_Token_Start
     */
    public function createStart($name, $attr = array()) {
        $p = clone $this->p_start;
        $p->__construct($name, $attr);
        return $p;
    }
    
    /**
     * Creates a HTMLPurifier_Token_End.
     * @param $name Tag name
     * @return Generated HTMLPurifier_Token_End
     */
    public function createEnd($name) {
        $p = clone $this->p_end;
        $p->__construct($name);
        return $p;
    }
    
    /**
     * Creates a HTMLPurifier_Token_Empty.
     * @param $name Tag name
     * @param $attr Associative array of attributes
     * @return Generated HTMLPurifier_Token_Empty
     */
    public function createEmpty($name, $attr = array()) {
        $p = clone $this->p_empty;
        $p->__construct($name, $attr);
        return $p;
    }
    
    /**
     * Creates a HTMLPurifier_Token_Text.
     * @param $data Data of text token
     * @return Generated HTMLPurifier_Token_Text
     */
    public function createText($data) {
        $p = clone $this->p_text;
        $p->__construct($data);
        return $p;
    }
    
    /**
     * Creates a HTMLPurifier_Token_Comment.
     * @param $data Data of comment token
     * @return Generated HTMLPurifier_Token_Comment
     */
    public function createComment($data) {
        $p = clone $this->p_comment;
        $p->__construct($data);
        return $p;
    }
    
}

class HTMLPurifier_URI
{
    
    public $scheme, $userinfo, $host, $port, $path, $query, $fragment;
    
    /**
     * @note Automatically normalizes scheme and port
     */
    public function __construct($scheme, $userinfo, $host, $port, $path, $query, $fragment) {
        $this->scheme = is_null($scheme) || ctype_lower($scheme) ? $scheme : strtolower($scheme);
        $this->userinfo = $userinfo;
        $this->host = $host;
        $this->port = is_null($port) ? $port : (int) $port;
        $this->path = $path;
        $this->query = $query;
        $this->fragment = $fragment;
    }
    
    /**
     * Retrieves a scheme object corresponding to the URI's scheme/default
     * @param $config Instance of HTMLPurifier_Config
     * @param $context Instance of HTMLPurifier_Context
     * @return Scheme object appropriate for validating this URI
     */
    public function getSchemeObj($config, $context) {
        $registry = HTMLPurifier_URISchemeRegistry::instance();
        if ($this->scheme !== null) {
            $scheme_obj = $registry->getScheme($this->scheme, $config, $context);
            if (!$scheme_obj) return false; // invalid scheme, clean it out
        } else {
            // no scheme: retrieve the default one
            $def = $config->getDefinition('URI');
            $scheme_obj = $registry->getScheme($def->defaultScheme, $config, $context);
            if (!$scheme_obj) {
                // something funky happened to the default scheme object
                trigger_error(
                    'Default scheme object "' . $def->defaultScheme . '" was not readable',
                    E_USER_WARNING
                );
                return false;
            }
        }
        return $scheme_obj;
    }
    
    /**
     * Generic validation method applicable for all schemes. May modify
     * this URI in order to get it into a compliant form.
     * @param $config Instance of HTMLPurifier_Config
     * @param $context Instance of HTMLPurifier_Context
     * @return True if validation/filtering succeeds, false if failure
     */
    public function validate($config, $context) {
        
        // ABNF definitions from RFC 3986
        $chars_sub_delims = '!$&\'()*+,;=';
        $chars_gen_delims = ':/?#[]@';
        $chars_pchar = $chars_sub_delims . ':@';
        
        // validate scheme (MUST BE FIRST!)
        if (!is_null($this->scheme) && is_null($this->host)) {
            $def = $config->getDefinition('URI');
            if ($def->defaultScheme === $this->scheme) {
                $this->scheme = null;
            }
        }
        
        // validate host
        if (!is_null($this->host)) {
            $host_def = new HTMLPurifier_AttrDef_URI_Host();
            $this->host = $host_def->validate($this->host, $config, $context);
            if ($this->host === false) $this->host = null;
        }
        
        // validate username
        if (!is_null($this->userinfo)) {
            $encoder = new HTMLPurifier_PercentEncoder($chars_sub_delims . ':');
            $this->userinfo = $encoder->encode($this->userinfo);
        }
        
        // validate port
        if (!is_null($this->port)) {
            if ($this->port < 1 || $this->port > 65535) $this->port = null;
        }
        
        // validate path
        $path_parts = array();
        $segments_encoder = new HTMLPurifier_PercentEncoder($chars_pchar . '/');
        if (!is_null($this->host)) {
            // path-abempty (hier and relative)
            $this->path = $segments_encoder->encode($this->path);
        } elseif ($this->path !== '' && $this->path[0] === '/') {
            // path-absolute (hier and relative)
            if (strlen($this->path) >= 2 && $this->path[1] === '/') {
                // This shouldn't ever happen!
                $this->path = '';
            } else {
                $this->path = $segments_encoder->encode($this->path);
            }
        } elseif (!is_null($this->scheme) && $this->path !== '') {
            // path-rootless (hier)
            // Short circuit evaluation means we don't need to check nz
            $this->path = $segments_encoder->encode($this->path);
        } elseif (is_null($this->scheme) && $this->path !== '') {
            // path-noscheme (relative)
            // (once again, not checking nz)
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
            // path-empty (hier and relative)
            $this->path = ''; // just to be safe
        }
        
        return true;
        
    }
    
    /**
     * Convert URI back to string
     * @return String URI appropriate for output
     */
    public function toString() {
        // reconstruct authority
        $authority = null;
        if (!is_null($this->host)) {
            $authority = '';
            if(!is_null($this->userinfo)) $authority .= $this->userinfo . '@';
            $authority .= $this->host;
            if(!is_null($this->port))     $authority .= ':' . $this->port;
        }
        
        // reconstruct the result
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
    
    /**
     * HTMLPurifier_URI object of the base specified at %URI.Base
     */
    public $base;
    
    /**
     * String host to consider "home" base, derived off of $base
     */
    public $host;
    
    /**
     * Name of default scheme based on %URI.DefaultScheme and %URI.Base
     */
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



/**
 * Chainable filters for custom URI processing.
 * 
 * These filters can perform custom actions on a URI filter object,
 * including transformation or blacklisting.
 * 
 * @warning This filter is called before scheme object validation occurs.
 *          Make sure, if you require a specific scheme object, you
 *          you check that it exists. This allows filters to convert
 *          proprietary URI schemes into regular ones.
 */
abstract class HTMLPurifier_URIFilter
{
    
    /**
     * Unique identifier of filter
     */
    public $name;
    
    /**
     * Performs initialization for the filter
     */
    public function prepare($config) {}
    
    /**
     * Filter a URI object
     * @param $uri Reference to URI object variable
     * @param $config Instance of HTMLPurifier_Config
     * @param $context Instance of HTMLPurifier_Context
     * @return bool Whether or not to continue processing: false indicates
     *         URL is no good, true indicates continue processing. Note that
     *         all changes are committed directly on the URI object
     */
    abstract public function filter(&$uri, $config, $context);
    
}



/**
 * Parses a URI into the components and fragment identifier as specified
 * by RFC 3986.
 */
class HTMLPurifier_URIParser
{
    
    /**
     * Instance of HTMLPurifier_PercentEncoder to do normalization with.
     */
    protected $percentEncoder;
    
    public function __construct() {
        $this->percentEncoder = new HTMLPurifier_PercentEncoder();
    }
    
    /**
     * Parses a URI.
     * @param $uri string URI to parse
     * @return HTMLPurifier_URI representation of URI. This representation has
     *         not been validated yet and may not conform to RFC.
     */
    public function parse($uri) {
        
        $uri = $this->percentEncoder->normalize($uri);
        
        // Regexp is as per Appendix B.
        // Note that ["<>] are an addition to the RFC's recommended 
        // characters, because they represent external delimeters.
        $r_URI = '!'.
            '(([^:/?#"<>]+):)?'. // 2. Scheme
            '(//([^/?#"<>]*))?'. // 4. Authority
            '([^?#"<>]*)'.       // 5. Path
            '(\?([^#"<>]*))?'.   // 7. Query
            '(#([^"<>]*))?'.     // 8. Fragment
            '!';
        
        $matches = array();
        $result = preg_match($r_URI, $uri, $matches);
        
        if (!$result) return false; // *really* invalid URI
        
        // seperate out parts
        $scheme     = !empty($matches[1]) ? $matches[2] : null;
        $authority  = !empty($matches[3]) ? $matches[4] : null;
        $path       = $matches[5]; // always present, can be empty
        $query      = !empty($matches[6]) ? $matches[7] : null;
        $fragment   = !empty($matches[8]) ? $matches[9] : null;
        
        // further parse authority
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




/**
 * Validator for the components of a URI for a specific scheme
 */
class HTMLPurifier_URIScheme
{
    
    /**
     * Scheme's default port (integer)
     */
    public $default_port = null;
    
    /**
     * Whether or not URIs of this schem are locatable by a browser
     * http and ftp are accessible, while mailto and news are not.
     */
    public $browsable = false;
    
    /**
     * Whether or not the URI always uses <hier_part>, resolves edge cases
     * with making relative URIs absolute
     */
    public $hierarchical = false;
    
    /**
     * Validates the components of a URI
     * @note This implementation should be called by children if they define
     *       a default port, as it does port processing.
     * @param $uri Instance of HTMLPurifier_URI
     * @param $config HTMLPurifier_Config object
     * @param $context HTMLPurifier_Context object
     * @return Bool success or failure
     */
    public function validate(&$uri, $config, $context) {
        if ($this->default_port == $uri->port) $uri->port = null;
        return true;
    }
    
}

/**
 * Registry for retrieving specific URI scheme validator objects.
 */
class HTMLPurifier_URISchemeRegistry
{
    
    /**
     * Retrieve sole instance of the registry.
     * @param $prototype Optional prototype to overload sole instance with,
     *                   or bool true to reset to default registry.
     * @note Pass a registry object $prototype with a compatible interface and
     *       the function will copy it and return it all further times.
     */
    public static function instance($prototype = null) {
        static $instance = null;
        if ($prototype !== null) {
            $instance = $prototype;
        } elseif ($instance === null || $prototype == true) {
            $instance = new HTMLPurifier_URISchemeRegistry();
        }
        return $instance;
    }
    
    /**
     * Cache of retrieved schemes.
     */
    protected $schemes = array();
    
    /**
     * Retrieves a scheme validator object
     * @param $scheme String scheme name like http or mailto
     * @param $config HTMLPurifier_Config object
     * @param $config HTMLPurifier_Context object
     */
    public function getScheme($scheme, $config, $context) {
        if (!$config) $config = HTMLPurifier_Config::createDefault();
        $null = null; // for the sake of passing by reference
        
        // important, otherwise attacker could include arbitrary file
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
    
    /**
     * Registers a custom scheme to the cache, bypassing reflection.
     * @param $scheme Scheme name
     * @param $scheme_obj HTMLPurifier_URIScheme object
     */
    public function register($scheme, $scheme_obj) {
        $this->schemes[$scheme] = $scheme_obj;
    }
    
}


/**
 * Parses string representations into their corresponding native PHP
 * variable type. The base implementation does a simple type-check.
 */
class HTMLPurifier_VarParser
{
    
    /**
     * Lookup table of allowed types.
     */
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
    
    /**
     * Lookup table of types that are string, and can have aliases or
     * allowed value lists.
     */
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
        // These are basic checks, to make sure nothing horribly wrong
        // happened in our implementations.
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
    
    /**
     * Actually implements the parsing. Base implementation is to not
     * do anything to $var. Subclasses should overload this!
     */
    protected function parseImplementation($var, $type, $allow_null) {
        return $var;
    }
    
    /**
     * Throws an exception.
     */
    protected function error($msg) {
        throw new HTMLPurifier_VarParserException($msg);
    }
    
    /**
     * Throws an inconsistency exception.
     * @note This should not ever be called. It would be called if we
     *       extend the allowed values of HTMLPurifier_VarParser without
     *       updating subclasses.
     */
    protected function errorInconsistent($class, $type) {
        throw new HTMLPurifier_Exception("Inconsistency in $class: $type not implemented");
    }
    
    /**
     * Generic error for if a type didn't work.
     */
    protected function errorGeneric($var, $type) {
        $vtype = gettype($var);
        $this->error("Expected type $type, got $vtype");
    }
    
}

/**
 * Exception type for HTMLPurifier_VarParser
 */
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
            // inefficient call, since the validator will do this again
            if (strtolower(trim($value)) !== 'inherit') {
                // inherit works for everything (but only on the base property)
                $result = $definition->info[$property]->validate(
                    $value, $config, $context );
            } else {
                $result = 'inherit';
            }
            if ($result === false) continue;
            $propvalues[$property] = $result;
        }
        
        // procedure does not write the new CSS simultaneously, so it's
        // slightly inefficient, but it's the only way of getting rid of
        // duplicates. Perhaps config to optimize it, but not now.
        
        $new_declarations = '';
        foreach ($propvalues as $prop => $value) {
            $new_declarations .= "$prop:$value;";
        }
        
        return $new_declarations ? $new_declarations : false;
        
    }
    
}

// Enum = Enumerated
/**
 * Validates a keyword against a list of valid values.
 * @warning The case-insensitive compare of this function uses PHP's
 *          built-in strtolower and ctype_lower functions, which may
 *          cause problems with international comparisons
 */
class HTMLPurifier_AttrDef_Enum extends HTMLPurifier_AttrDef
{
    
    /**
     * Lookup table of valid values.
     * @todo Make protected
     */
    public $valid_values   = array();
    
    /**
     * Bool indicating whether or not enumeration is case sensitive.
     * @note In general this is always case insensitive.
     */
    protected $case_sensitive = false; // values according to W3C spec
    
    /**
     * @param $valid_values List of valid values
     * @param $case_sensitive Bool indicating whether or not case sensitive
     */
    public function __construct(
        $valid_values = array(), $case_sensitive = false
    ) {
        $this->valid_values = array_flip($valid_values);
        $this->case_sensitive = $case_sensitive;
    }
    
    public function validate($string, $config, $context) {
        $string = trim($string);
        if (!$this->case_sensitive) {
            // we may want to do full case-insensitive libraries
            $string = ctype_lower($string) ? $string : strtolower($string);
        }
        $result = isset($this->valid_values[$string]);
        
        return $result ? $string : false;
    }
    
    /**
     * @param $string In form of comma-delimited list of case-insensitive
     *      valid values. Example: "foo,bar,baz". Prepend "s:" to make
     *      case sensitive
     */
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
    
    /**
     * Bool indicating whether or not negative values are allowed
     */
    protected $negative = true;
    
    /**
     * Bool indicating whether or not zero is allowed
     */
    protected $zero = true;
    
    /**
     * Bool indicating whether or not positive values are allowed
     */
    protected $positive = true;
    
    /**
     * @param $negative Bool indicating whether or not negative values are allowed
     * @param $zero Bool indicating whether or not zero is allowed
     * @param $positive Bool indicating whether or not positive values are allowed
     */
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
        
        // we could possibly simply typecast it to integer, but there are
        // certain fringe cases that must not return an integer.
        
        // clip leading sign
        if ( $this->negative && $integer[0] === '-' ) {
            $digits = substr($integer, 1);
            if ($digits === '0') $integer = '0'; // rm minus sign for zero
        } elseif( $this->positive && $integer[0] === '+' ) {
            $digits = $integer = substr($integer, 1); // rm unnecessary plus
        } else {
            $digits = $integer;
        }
        
        // test if it's numeric
        if (!ctype_digit($digits)) return false;
        
        // perform scope tests
        if (!$this->zero     && $integer == 0) return false;
        if (!$this->positive && $integer > 0) return false;
        if (!$this->negative && $integer < 0) return false;
        
        return $integer;
        
    }
    
}

/**
 * Validates the HTML attribute lang, effectively a language code.
 * @note Built according to RFC 3066, which obsoleted RFC 1766
 */
class HTMLPurifier_AttrDef_Lang extends HTMLPurifier_AttrDef
{
    
    public function validate($string, $config, $context) {
        
        $string = trim($string);
        if (!$string) return false;
        
        $subtags = explode('-', $string);
        $num_subtags = count($subtags);
        
        if ($num_subtags == 0) return false; // sanity check
        
        // process primary subtag : $subtags[0]
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
        
        // process second subtag : $subtags[1]
        $length = strlen($subtags[1]);
        if ($length == 0 || ($length == 1 && $subtags[1] != 'x') || $length > 8 || !ctype_alnum($subtags[1])) {
            return $new_string;
        }
        if (!ctype_lower($subtags[1])) $subtags[1] = strtolower($subtags[1]);
        
        $new_string .= '-' . $subtags[1];
        if ($num_subtags == 2) return $new_string;
        
        // process all other subtags, index 2 and up
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

/**
 * Validates arbitrary text according to the HTML spec.
 */
class HTMLPurifier_AttrDef_Text extends HTMLPurifier_AttrDef
{
    
    public function validate($string, $config, $context) {
        return $this->parseCDATA($string);
    }
    
}

/**
 * Validates a URI as defined by RFC 3986.
 * @note Scheme-specific mechanics deferred to HTMLPurifier_URIScheme
 */
class HTMLPurifier_AttrDef_URI extends HTMLPurifier_AttrDef
{
    
    protected $parser;
    protected $embedsResource;
    
    /**
     * @param $embeds_resource_resource Does the URI here result in an extra HTTP request?
     */
    public function __construct($embeds_resource = false) {
        $this->parser = new HTMLPurifier_URIParser();
        $this->embedsResource = (bool) $embeds_resource;
    }
    
    public function validate($uri, $config, $context) {
        
        if ($config->get('URI', 'Disable')) return false;
        
        $uri = $this->parseCDATA($uri);
        
        // parse the URI
        $uri = $this->parser->parse($uri);
        if ($uri === false) return false;
        
        // add embedded flag to context for validators
        $context->register('EmbeddedURI', $this->embedsResource); 
        
        $ok = false;
        do {
            
            // generic validation
            $result = $uri->validate($config, $context);
            if (!$result) break;
            
            // chained filtering
            $uri_def = $config->getDefinition('URI');
            $result = $uri_def->filter($uri, $config, $context);
            if (!$result) break;
            
            // scheme-specific validation 
            $scheme_obj = $uri->getSchemeObj($config, $context);
            if (!$scheme_obj) break;
            if ($this->embedsResource && !$scheme_obj->browsable) break;
            $result = $scheme_obj->validate($uri, $config, $context);
            if (!$result) break;
            
            // survived gauntlet
            $ok = true;
            
        } while (false);
        
        $context->destroy('EmbeddedURI');
        if (!$ok) return false;
        
        // back to string
        $result = $uri->toString();
        
        // munge entire URI if necessary
        if (
            !is_null($uri->host) && // indicator for authority
            !empty($scheme_obj->browsable) &&
            !is_null($munge = $config->get('URI', 'Munge'))
        ) {
            $result = str_replace('%s', rawurlencode($result), $munge);
        }
        
        return $result;
        
    }
    
}

/**
 * Validates a number as defined by the CSS spec.
 */
class HTMLPurifier_AttrDef_CSS_Number extends HTMLPurifier_AttrDef
{
    
    /**
     * Bool indicating whether or not only positive values allowed.
     */
    protected $non_negative = false;
    
    /**
     * @param $non_negative Bool indicating whether negatives are forbidden
     */
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
        
        // Period is the only non-numeric character allowed
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
        parent::__construct(false); // opacity is non-negative, but we will clamp it
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

/**
 * Validates shorthand CSS property background.
 * @warning Does not support url tokens that have internal spaces.
 */
class HTMLPurifier_AttrDef_CSS_Background extends HTMLPurifier_AttrDef
{
    
    /**
     * Local copy of component validators.
     * @note See HTMLPurifier_AttrDef_Font::$info for a similar impl.
     */
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
        
        // regular pre-processing
        $string = $this->parseCDATA($string);
        if ($string === '') return false;
        
        // munge rgb() decl if necessary
        $string = $this->mungeRgb($string);
        
        // assumes URI doesn't have spaces in it
        $bits = explode(' ', strtolower($string)); // bits to process
        
        $caught = array();
        $caught['color']    = false;
        $caught['image']    = false;
        $caught['repeat']   = false;
        $caught['attachment'] = false;
        $caught['position'] = false;
        
        $i = 0; // number of catches
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




/* W3C says:
    [ // adjective and number must be in correct order, even if
      // you could switch them without introducing ambiguity.
      // some browsers support that syntax
        [
            <percentage> | <length> | left | center | right
        ]
        [ 
            <percentage> | <length> | top | center | bottom
        ]?
    ] |
    [ // this signifies that the vertical and horizontal adjectives
      // can be arbitrarily ordered, however, there can only be two,
      // one of each, or none at all
        [
            left | center | right
        ] ||
        [
            top | center | bottom
        ]
    ]
    top, left = 0%
    center, (none) = 50%
    bottom, right = 100%
*/


/**
 * Validates the value of background-position.
 */
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
        $keywords['h'] = false; // left, right
        $keywords['v'] = false; // top, bottom
        $keywords['c'] = false; // center
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
            
            // test for keyword
            $lbit = ctype_lower($bit) ? $bit : strtolower($bit);
            if (isset($lookup[$lbit])) {
                $status = $lookup[$lbit];
                $keywords[$status] = $lbit;
                $i++;
            }
            
            // test for length
            $r = $this->length->validate($bit, $config, $context);
            if ($r !== false) {
                $measures[] = $r;
                $i++;
            }
            
            // test for percentage
            $r = $this->percentage->validate($bit, $config, $context);
            if ($r !== false) {
                $measures[] = $r;
                $i++;
            }
            
        }
        
        if (!$i) return false; // no valid values were caught
        
        
        $ret = array();
        
        // first keyword
        if     ($keywords['h'])     $ret[] = $keywords['h'];
        elseif (count($measures))   $ret[] = array_shift($measures);
        elseif ($keywords['c']) {
            $ret[] = $keywords['c'];
            $keywords['c'] = false; // prevent re-use: center = center center
        }
        
        if     ($keywords['v'])     $ret[] = $keywords['v'];
        elseif (count($measures))   $ret[] = array_shift($measures);
        elseif ($keywords['c'])     $ret[] = $keywords['c'];
        
        if (empty($ret)) return false;
        return implode(' ', $ret);
        
    }
    
}


/**
 * Validates the border property as defined by CSS.
 */
class HTMLPurifier_AttrDef_CSS_Border extends HTMLPurifier_AttrDef
{
    
    /**
     * Local copy of properties this property is shorthand for.
     */
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
        $done = array(); // segments we've finished
        $ret = ''; // return value
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

/**
 * Validates Color as defined by CSS.
 */
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
            // rgb literal handling
            $length = strlen($color);
            if (strpos($color, ')') !== $length - 1) return false;
            $triad = substr($color, 4, $length - 4 - 1);
            $parts = explode(',', $triad);
            if (count($parts) !== 3) return false;
            $type = false; // to ensure that they're all the same type
            $new_parts = array();
            foreach ($parts as $part) {
                $part = trim($part);
                if ($part === '') return false;
                $length = strlen($part);
                if ($part[$length - 1] === '%') {
                    // handle percents
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
                    // handle integers
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
            // hexadecimal handling
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




/**
 * Allows multiple validators to attempt to validate attribute.
 * 
 * Composite is just what it sounds like: a composite of many validators.
 * This means that multiple HTMLPurifier_AttrDef objects will have a whack
 * at the string.  If one of them passes, that's what is returned.  This is
 * especially useful for CSS values, which often are a choice between
 * an enumerated set of predefined values or a flexible data type.
 */
class HTMLPurifier_AttrDef_CSS_Composite extends HTMLPurifier_AttrDef
{
    
    /**
     * List of HTMLPurifier_AttrDef objects that may process strings
     * @todo Make protected
     */
    public $defs;
    
    /**
     * @param $defs List of HTMLPurifier_AttrDef objects
     */
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




/**
 * Decorator which enables CSS properties to be disabled for specific elements.
 */
class HTMLPurifier_AttrDef_CSS_DenyElementDecorator extends HTMLPurifier_AttrDef
{
    protected $def, $element;
    
    /**
     * @param $def Definition to wrap
     * @param $element Element to deny
     */
    public function __construct($def, $element) {
        $this->def = $def;
        $this->element = $element;
    }
    /**
     * Checks if CurrentToken is set and equal to $this->element
     */
    public function validate($string, $config, $context) {
        $token = $context->get('CurrentToken', true);
        if ($token && $token->name == $this->element) return false;
        return $this->def->validate($string, $config, $context);
    }
}



/**
 * Microsoft's proprietary filter: CSS property
 * @note Currently supports the alpha filter. In the future, this will
 *       probably need an extensible framework
 */
class HTMLPurifier_AttrDef_CSS_Filter extends HTMLPurifier_AttrDef
{
    
    protected $intValidator;
    
    public function __construct() {
        $this->intValidator = new HTMLPurifier_AttrDef_Integer();
    }
    
    public function validate($value, $config, $context) {
        $value = $this->parseCDATA($value);
        if ($value === 'none') return $value;
        // if we looped this we could support multiple filters
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



/**
 * Validates shorthand CSS property font.
 */
class HTMLPurifier_AttrDef_CSS_Font extends HTMLPurifier_AttrDef
{
    
    /**
     * Local copy of component validators.
     * 
     * @note If we moved specific CSS property definitions to their own
     *       classes instead of having them be assembled at run time by
     *       CSSDefinition, this wouldn't be necessary.  We'd instantiate
     *       our own copies.
     */
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
        
        // regular pre-processing
        $string = $this->parseCDATA($string);
        if ($string === '') return false;
        
        // check if it's one of the keywords
        $lowercase_string = strtolower($string);
        if (isset($system_fonts[$lowercase_string])) {
            return $lowercase_string;
        }
        
        $bits = explode(' ', $string); // bits to process
        $stage = 0; // this indicates what we're looking for
        $caught = array(); // which stage 0 properties have we caught?
        $stage_1 = array('font-style', 'font-variant', 'font-weight');
        $final = ''; // output
        
        for ($i = 0, $size = count($bits); $i < $size; $i++) {
            if ($bits[$i] === '') continue;
            switch ($stage) {
                
                // attempting to catch font-style, font-variant or font-weight
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
                    // all three caught, continue on
                    if (count($caught) >= 3) $stage = 1;
                    if ($r !== false) break;
                
                // attempting to catch font-size and perhaps line-height
                case 1:
                    $found_slash = false;
                    if (strpos($bits[$i], '/') !== false) {
                        list($font_size, $line_height) =
                                                    explode('/', $bits[$i]);
                        if ($line_height === '') {
                            // ooh, there's a space after the slash!
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
                        // attempt to catch line-height
                        if ($line_height === false) {
                            // we need to scroll forward
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
                            // slash already found
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
                
                // attempting to catch font-family
                case 2:
                    $font_family =
                        implode(' ', array_slice($bits, $i, $size - $i));
                    $r = $this->info['font-family']->validate(
                                              $font_family, $config, $context);
                    if ($r !== false) {
                        $final .= $r . ' ';
                        // processing completed successfully
                        return rtrim($final);
                    }
                    return false;
            }
        }
        return false;
    }
    
}




/**
 * Validates a font family list according to CSS spec
 * @todo whitelisting allowed fonts would be nice
 */
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
        // assume that no font names contain commas in them
        $fonts = explode(',', $string);
        $final = '';
        foreach($fonts as $font) {
            $font = trim($font);
            if ($font === '') continue;
            // match a generic name
            if (isset($generic_names[$font])) {
                $final .= $font . ', ';
                continue;
            }
            // match a quoted name
            if ($font[0] === '"' || $font[0] === "'") {
                $length = strlen($font);
                if ($length <= 2) continue;
                $quote = $font[0];
                if ($font[$length - 1] !== $quote) continue;
                $font = substr($font, 1, $length - 2);
                // double-backslash processing is buggy
                $font = str_replace("\\$quote", $quote, $font); // de-escape quote
                $font = str_replace("\\\n", "\n", $font);       // de-escape newlines
            }
            // $font is a pure representation of the font name
            
            if (ctype_alnum($font)) {
                // very simple font, allow it in unharmed
                $final .= $font . ', ';
                continue;
            }
            
            // complicated font, requires quoting
            
            // armor single quotes and new lines
            $font = str_replace("'", "\\'", $font);
            $font = str_replace("\n", "\\\n", $font);
            $final .= "'$font', ";
        }
        $final = rtrim($final, ', ');
        if ($final === '') return false;
        return $final;
    }
    
}




/**
 * Decorator which enables !important to be used in CSS values.
 */
class HTMLPurifier_AttrDef_CSS_ImportantDecorator extends HTMLPurifier_AttrDef
{
    protected $def, $allow;
    
    /**
     * @param $def Definition to wrap
     * @param $allow Whether or not to allow !important
     */
    public function __construct($def, $allow = false) {
        $this->def = $def;
        $this->allow = $allow;
    }
    /**
     * Intercepts and removes !important if necessary
     */
    public function validate($string, $config, $context) {
        // test for ! and important tokens
        $string = trim($string);
        $is_important = false;
        // :TODO: optimization: test directly for !important and ! important
        if (strlen($string) >= 9 && substr($string, -9) === 'important') {
            $temp = rtrim(substr($string, 0, -9));
            // use a temp, because we might want to restore important
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



/**
 * Represents a Length as defined by CSS.
 */
class HTMLPurifier_AttrDef_CSS_Length extends HTMLPurifier_AttrDef
{
    
    /**
     * Valid unit lookup table.
     * @warning The code assumes all units are two characters long.  Be careful
     *          if we have to change this behavior!
     */
    protected $units = array('em' => true, 'ex' => true, 'px' => true, 'in' => true,
         'cm' => true, 'mm' => true, 'pt' => true, 'pc' => true);
    /**
     * Instance of HTMLPurifier_AttrDef_Number to defer number validation to
     */
    protected $number_def;
    
    /**
     * @param $non_negative Bool indication whether or not negative values are
     *                      allowed.
     */
    public function __construct($non_negative = false) {
        $this->number_def = new HTMLPurifier_AttrDef_CSS_Number($non_negative);
    }
    
    public function validate($length, $config, $context) {
        
        $length = $this->parseCDATA($length);
        if ($length === '') return false;
        if ($length === '0') return '0';
        $strlen = strlen($length);
        if ($strlen === 1) return false; // impossible!
        
        // we assume all units are two characters
        $unit = substr($length, $strlen - 2);
        if (!ctype_lower($unit)) $unit = strtolower($unit);
        $number = substr($length, 0, $strlen - 2);
        
        if (!isset($this->units[$unit])) return false;
        
        $number = $this->number_def->validate($number, $config, $context);
        if ($number === false) return false;
        
        return $number . $unit;
        
    }
    
}




/**
 * Validates shorthand CSS property list-style.
 * @warning Does not support url tokens that have internal spaces.
 */
class HTMLPurifier_AttrDef_CSS_ListStyle extends HTMLPurifier_AttrDef
{
    
    /**
     * Local copy of component validators.
     * @note See HTMLPurifier_AttrDef_CSS_Font::$info for a similar impl.
     */
    protected $info;
    
    public function __construct($config) {
        $def = $config->getCSSDefinition();
        $this->info['list-style-type']     = $def->info['list-style-type'];
        $this->info['list-style-position'] = $def->info['list-style-position'];
        $this->info['list-style-image'] = $def->info['list-style-image'];
    }
    
    public function validate($string, $config, $context) {
        
        // regular pre-processing
        $string = $this->parseCDATA($string);
        if ($string === '') return false;
        
        // assumes URI doesn't have spaces in it
        $bits = explode(' ', strtolower($string)); // bits to process
        
        $caught = array();
        $caught['type']     = false;
        $caught['position'] = false;
        $caught['image']    = false;
        
        $i = 0; // number of catches
        $none = false;
        
        foreach ($bits as $bit) {
            if ($i >= 3) return; // optimization bit
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
        
        // construct type
        if ($caught['type']) $ret[] = $caught['type'];
        
        // construct image
        if ($caught['image']) $ret[] = $caught['image'];
        
        // construct position
        if ($caught['position']) $ret[] = $caught['position'];
        
        if (empty($ret)) return false;
        return implode(' ', $ret);
        
    }
    
}




/**
 * Framework class for strings that involve multiple values.
 * 
 * Certain CSS properties such as border-width and margin allow multiple
 * lengths to be specified.  This class can take a vanilla border-width
 * definition and multiply it, usually into a max of four.
 * 
 * @note Even though the CSS specification isn't clear about it, inherit
 *       can only be used alone: it will never manifest as part of a multi
 *       shorthand declaration.  Thus, this class does not allow inherit.
 */
class HTMLPurifier_AttrDef_CSS_Multiple extends HTMLPurifier_AttrDef
{
    
    /**
     * Instance of component definition to defer validation to.
     * @todo Make protected
     */
    public $single;
    
    /**
     * Max number of values allowed.
     * @todo Make protected
     */
    public $max;
    
    /**
     * @param $single HTMLPurifier_AttrDef to multiply
     * @param $max Max number of values allowed (usually four)
     */
    public function __construct($single, $max = 4) {
        $this->single = $single;
        $this->max = $max;
    }
    
    public function validate($string, $config, $context) {
        $string = $this->parseCDATA($string);
        if ($string === '') return false;
        $parts = explode(' ', $string); // parseCDATA replaced \r, \t and \n
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




/**
 * Validates a Percentage as defined by the CSS spec.
 */
class HTMLPurifier_AttrDef_CSS_Percentage extends HTMLPurifier_AttrDef
{
    
    /**
     * Instance of HTMLPurifier_AttrDef_CSS_Number to defer number validation
     */
    protected $number_def;
    
    /**
     * @param Bool indicating whether to forbid negative values
     */
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




/**
 * Validates the value for the CSS property text-decoration
 * @note This class could be generalized into a version that acts sort of
 *       like Enum except you can compound the allowed values.
 */
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




/**
 * Validates a URI in CSS syntax, which uses url('http://example.com')
 * @note While theoretically speaking a URI in a CSS document could
 *       be non-embedded, as of CSS2 there is no such usage so we're
 *       generalizing it. This may need to be changed in the future.
 * @warning Since HTMLPurifier_AttrDef_CSS blindly uses semicolons as
 *          the separator, you cannot put a literal semicolon in
 *          in the URI. Try percent encoding it, in that case.
 */
class HTMLPurifier_AttrDef_CSS_URI extends HTMLPurifier_AttrDef_URI
{
    
    public function __construct() {
        parent::__construct(true); // always embedded
    }
    
    public function validate($uri_string, $config, $context) {
        // parse the URI out of the string and then pass it onto
        // the parent object
        
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
        
        // escape necessary characters according to CSS spec
        // except for the comma, none of these should appear in the
        // URI at all
        $result = str_replace($keys, $values, $result);
        
        return "url($result)";
        
    }
    
}




/**
 * Validates a boolean attribute
 */
class HTMLPurifier_AttrDef_HTML_Bool extends HTMLPurifier_AttrDef
{
    
    protected $name;
    public $minimized = true;
    
    public function __construct($name = false) {$this->name = $name;}
    
    public function validate($string, $config, $context) {
        if (empty($string)) return false;
        return $this->name;
    }
    
    /**
     * @param $string Name of attribute
     */
    public function make($string) {
        return new HTMLPurifier_AttrDef_HTML_Bool($string);
    }
    
}




/**
 * Validates a color according to the HTML spec.
 */
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




/**
 * Special-case enum attribute definition that lazy loads allowed frame targets
 */
class HTMLPurifier_AttrDef_HTML_FrameTarget extends HTMLPurifier_AttrDef_Enum
{
    
    public $valid_values = false; // uninitialized value
    protected $case_sensitive = false;
    
    public function __construct() {}
    
    public function validate($string, $config, $context) {
        if ($this->valid_values === false) $this->valid_values = $config->get('Attr', 'AllowedFrameTargets');
        return parent::validate($string, $config, $context);
    }
    
}




/**
 * Validates the HTML attribute ID.
 * @warning Even though this is the id processor, it
 *          will ignore the directive Attr:IDBlacklist, since it will only
 *          go according to the ID accumulator. Since the accumulator is
 *          automatically generated, it will have already absorbed the
 *          blacklist. If you're hacking around, make sure you use load()!
 */

class HTMLPurifier_AttrDef_HTML_ID extends HTMLPurifier_AttrDef
{
    
    // ref functionality disabled, since we also have to verify
    // whether or not the ID it refers to exists
    
    public function validate($id, $config, $context) {
        
        if (!$config->get('Attr', 'EnableID')) return false;
        
        $id = trim($id); // trim it first
        
        if ($id === '') return false;
        
        $prefix = $config->get('Attr', 'IDPrefix');
        if ($prefix !== '') {
            $prefix .= $config->get('Attr', 'IDPrefixLocal');
            // prevent re-appending the prefix
            if (strpos($id, $prefix) !== 0) $id = $prefix . $id;
        } elseif ($config->get('Attr', 'IDPrefixLocal') !== '') {
            trigger_error('%Attr.IDPrefixLocal cannot be used unless '.
                '%Attr.IDPrefix is set', E_USER_WARNING);
        }
        
        //if (!$this->ref) {
            $id_accumulator =& $context->get('IDAccumulator');
            if (isset($id_accumulator->ids[$id])) return false;
        //}
        
        // we purposely avoid using regex, hopefully this is faster
        
        if (ctype_alpha($id)) {
            $result = true;
        } else {
            if (!ctype_alpha(@$id[0])) return false;
            $trim = trim( // primitive style of regexps, I suppose
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
        
        // if no change was made to the ID, return the result
        // else, return the new id if stripping whitespace made it
        //     valid, or return false.
        return $result ? $id : false;
        
    }
    
}




/**
 * Validates an integer representation of pixels according to the HTML spec.
 */
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
        
        // upper-bound value, extremely high values can
        // crash operating systems, see <http://ha.ckers.org/imagecrash.html>
        // WARNING, above link WILL crash you if you're using Windows
        
        if ($int > 1200) return '1200';
        
        return (string) $int;
        
    }
    
}




/**
 * Validates the HTML type length (not to be confused with CSS's length).
 * 
 * This accepts integer pixels or percentages as lengths for certain
 * HTML attributes.
 */

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




/**
 * Validates a rel/rev link attribute against a directive of allowed values
 * @note We cannot use Enum because link types allow multiple
 *       values.
 * @note Assumes link types are ASCII text
 */
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
        
        // lookup to prevent duplicates
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




/**
 * Validates a MultiLength as defined by the HTML spec.
 * 
 * A multilength is either a integer (pixel count), a percentage, or
 * a relative number.
 */
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




/**
 * Validates contents based on NMTOKENS attribute type.
 * @note The only current use for this is the class attribute in HTML
 * @note Could have some functionality factored out into Nmtoken class
 * @warning We cannot assume this class will be used only for 'class'
 *          attributes. Not sure how to hook in magic behavior, then.
 */
class HTMLPurifier_AttrDef_HTML_Nmtokens extends HTMLPurifier_AttrDef
{
    
    public function validate($string, $config, $context) {
        
        $string = trim($string);
        
        // early abort: '' and '0' (strings that convert to false) are invalid
        if (!$string) return false;
        
        // OPTIMIZABLE!
        // do the preg_match, capture all subpatterns for reformulation
        
        // we don't support U+00A1 and up codepoints or
        // escaping because I don't know how to do that with regexps
        // and plus it would complicate optimization efforts (you never
        // see that anyway).
        $matches = array();
        $pattern = '/(?:(?<=\s)|\A)'. // look behind for space or string start
                   '((?:--|-?[A-Za-z_])[A-Za-z_\-0-9]*)'.
                   '(?:(?=\s)|\z)/'; // look ahead for space or string end
        preg_match_all($pattern, $string, $matches);
        
        if (empty($matches[1])) return false;
        
        // reconstruct string
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
    
    /**
     * Unpacks a mailbox into its display-name and address
     */
    function unpack($string) {
        // needs to be implemented
    }
    
}

// sub-implementations



/**
 * Validates a host according to the IPv4, IPv6 and DNS (future) specifications.
 */
class HTMLPurifier_AttrDef_URI_Host extends HTMLPurifier_AttrDef
{
    
    /**
     * Instance of HTMLPurifier_AttrDef_URI_IPv4 sub-validator
     */
    protected $ipv4;
    
    /**
     * Instance of HTMLPurifier_AttrDef_URI_IPv6 sub-validator
     */
    protected $ipv6;
    
    public function __construct() {
        $this->ipv4 = new HTMLPurifier_AttrDef_URI_IPv4();
        $this->ipv6 = new HTMLPurifier_AttrDef_URI_IPv6();
    }
    
    public function validate($string, $config, $context) {
        $length = strlen($string);
        if ($string === '') return '';
        if ($length > 1 && $string[0] === '[' && $string[$length-1] === ']') {
            //IPv6
            $ip = substr($string, 1, $length - 2);
            $valid = $this->ipv6->validate($ip, $config, $context);
            if ($valid === false) return false;
            return '['. $valid . ']';
        }
        
        // need to do checks on unusual encodings too
        $ipv4 = $this->ipv4->validate($string, $config, $context);
        if ($ipv4 !== false) return $ipv4;
        
        // A regular domain name.
        
        // This breaks I18N domain names, but we don't have proper IRI support,
        // so force users to insert Punycode. If there's complaining we'll 
        // try to fix things into an international friendly form.
        
        // The productions describing this are:
        $a   = '[a-z]';     // alpha
        $an  = '[a-z0-9]';  // alphanum
        $and = '[a-z0-9-]'; // alphanum | "-"
        // domainlabel = alphanum | alphanum *( alphanum | "-" ) alphanum
        $domainlabel   = "$an($and*$an)?";
        // toplabel    = alpha | alpha *( alphanum | "-" ) alphanum
        $toplabel      = "$a($and*$an)?";
        // hostname    = *( domainlabel "." ) toplabel [ "." ]
        $match = preg_match("/^($domainlabel\.)*$toplabel\.?$/i", $string);
        if (!$match) return false;
        
        return $string;
    }
    
}




/**
 * Validates an IPv4 address
 * @author Feyd @ forums.devnetwork.net (public domain)
 */
class HTMLPurifier_AttrDef_URI_IPv4 extends HTMLPurifier_AttrDef
{
    
    /**
     * IPv4 regex, protected so that IPv6 can reuse it
     */
    protected $ip4;
    
    public function validate($aIP, $config, $context) {
        
        if (!$this->ip4) $this->_loadRegex();
        
        if (preg_match('#^' . $this->ip4 . '$#s', $aIP))
        {
                return $aIP;
        }
        
        return false;
        
    }
    
    /**
     * Lazy load function to prevent regex from being stuffed in
     * cache.
     */
    protected function _loadRegex() {
        $oct = '(?:25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9][0-9]|[0-9])'; // 0-255
        $this->ip4 = "(?:{$oct}\\.{$oct}\\.{$oct}\\.{$oct})";
    }
    
}




/**
 * Validates an IPv6 address.
 * @author Feyd @ forums.devnetwork.net (public domain)
 * @note This function requires brackets to have been removed from address
 *       in URI.
 */
class HTMLPurifier_AttrDef_URI_IPv6 extends HTMLPurifier_AttrDef_URI_IPv4
{
    
    public function validate($aIP, $config, $context) {
        
        if (!$this->ip4) $this->_loadRegex();
        
        $original = $aIP;
        
        $hex = '[0-9a-fA-F]';
        $blk = '(?:' . $hex . '{1,4})';
        $pre = '(?:/(?:12[0-8]|1[0-1][0-9]|[1-9][0-9]|[0-9]))';   // /0 - /128
        
        //      prefix check
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
        
        //      IPv4-compatiblity check       
        if (preg_match('#(?<=:'.')' . $this->ip4 . '$#s', $aIP, $find))
        {
                $aIP = substr($aIP, 0, 0-strlen($find[0]));
                $ip = explode('.', $find[0]);
                $ip = array_map('dechex', $ip);
                $aIP .= $ip[0] . $ip[1] . ':' . $ip[2] . $ip[3];
                unset($find, $ip);
        }
        
        //      compression check
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
       
        //      All the pieces should be 16-bit hex strings. Are they?
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




/**
 * Primitive email validation class based on the regexp found at 
 * http://www.regular-expressions.info/email.html
 */
class HTMLPurifier_AttrDef_URI_Email_SimpleCheck extends HTMLPurifier_AttrDef_URI_Email
{
    
    public function validate($string, $config, $context) {
        // no support for named mailboxes i.e. "Bob <bob@example.com>"
        // that needs more percent encoding to be done
        if ($string == '') return false;
        $string = trim($string);
        $result = preg_match('/^[A-Z0-9._%-]+@[A-Z0-9.-]+\.[A-Z]{2,4}$/i', $string);
        return $result ? $string : false;
    }
    
}




// this MUST be placed in post, as it assumes that any value in dir is valid

/**
 * Post-trasnform that ensures that bdo tags have the dir attribute set.
 */
class HTMLPurifier_AttrTransform_BdoDir extends HTMLPurifier_AttrTransform
{
    
    public function transform($attr, $config, $context) {
        if (isset($attr['dir'])) return $attr;
        $attr['dir'] = $config->get('Attr', 'DefaultTextDir');
        return $attr;
    }
    
}




/**
 * Pre-transform that changes deprecated bgcolor attribute to CSS.
 */
class HTMLPurifier_AttrTransform_BgColor extends HTMLPurifier_AttrTransform {

    public function transform($attr, $config, $context) {
        
        if (!isset($attr['bgcolor'])) return $attr;
        
        $bgcolor = $this->confiscateAttr($attr, 'bgcolor');
        // some validation should happen here
        
        $this->prependCSS($attr, "background-color:$bgcolor;");
        
        return $attr;
        
    }
    
}




/**
 * Pre-transform that changes converts a boolean attribute to fixed CSS
 */
class HTMLPurifier_AttrTransform_BoolToCSS extends HTMLPurifier_AttrTransform {
    
    /**
     * Name of boolean attribute that is trigger
     */
    protected $attr;
    
    /**
     * CSS declarations to add to style, needs trailing semicolon
     */
    protected $css;
    
    /**
     * @param $attr string attribute name to convert from
     * @param $css string CSS declarations to add to style (needs semicolon)
     */
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




/**
 * Pre-transform that changes deprecated border attribute to CSS.
 */
class HTMLPurifier_AttrTransform_Border extends HTMLPurifier_AttrTransform {

    public function transform($attr, $config, $context) {
        if (!isset($attr['border'])) return $attr;
        $border_width = $this->confiscateAttr($attr, 'border');
        // some validation should happen here
        $this->prependCSS($attr, "border:{$border_width}px solid;");
        return $attr;
    }
    
}




/**
 * Generic pre-transform that converts an attribute with a fixed number of
 * values (enumerated) to CSS.
 */
class HTMLPurifier_AttrTransform_EnumToCSS extends HTMLPurifier_AttrTransform {
    
    /**
     * Name of attribute to transform from
     */
    protected $attr;
    
    /**
     * Lookup array of attribute values to CSS
     */
    protected $enumToCSS = array();
    
    /**
     * Case sensitivity of the matching
     * @warning Currently can only be guaranteed to work with ASCII
     *          values.
     */
    protected $caseSensitive = false;
    
    /**
     * @param $attr String attribute name to transform from
     * @param $enumToCSS Lookup array of attribute values to CSS
     * @param $case_sensitive Boolean case sensitivity indicator, default false
     */
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




// must be called POST validation

/**
 * Transform that supplies default values for the src and alt attributes
 * in img tags, as well as prevents the img tag from being removed
 * because of a missing alt tag. This needs to be registered as both
 * a pre and post attribute transform.
 */
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




/**
 * Pre-transform that changes deprecated hspace and vspace attributes to CSS
 */
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
        // some validation could happen here
        
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




/**
 * Post-transform that copies lang's value to xml:lang (and vice-versa)
 * @note Theoretically speaking, this could be a pre-transform, but putting
 *       post is more efficient.
 */
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




/**
 * Class for handling width/height length attribute transformations to CSS
 */
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




/**
 * Pre-transform that changes deprecated name attribute to ID if necessary
 */
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




/**
 * Implements required attribute stipulation for <script>
 */
class HTMLPurifier_AttrTransform_ScriptRequired extends HTMLPurifier_AttrTransform
{
    public function transform($attr, $config, $context) {
        if (!isset($attr['type'])) {
            $attr['type'] = 'text/javascript';
        }
        return $attr;
    }
}



/**
 * Definition that uses different definitions depending on context.
 * 
 * The del and ins tags are notable because they allow different types of
 * elements depending on whether or not they're in a block or inline context.
 * Chameleon allows this behavior to happen by using two different
 * definitions depending on context.  While this somewhat generalized,
 * it is specifically intended for those two tags.
 */
class HTMLPurifier_ChildDef_Chameleon extends HTMLPurifier_ChildDef
{
    
    /**
     * Instance of the definition object to use when inline. Usually stricter.
     */
    public $inline;
    
    /**
     * Instance of the definition object to use when block.
     */
    public $block;
    
    public $type = 'chameleon';
    
    /**
     * @param $inline List of elements to allow when inline.
     * @param $block List of elements to allow when block.
     */
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




/**
 * Custom validation class, accepts DTD child definitions
 * 
 * @warning Currently this class is an all or nothing proposition, that is,
 *          it will only give a bool return value.
 * @note This class is currently not used by any code, although it is unit
 *       tested.
 */
class HTMLPurifier_ChildDef_Custom extends HTMLPurifier_ChildDef
{
    public $type = 'custom';
    public $allow_empty = false;
    /**
     * Allowed child pattern as defined by the DTD
     */
    public $dtd_regex;
    /**
     * PCRE regex derived from $dtd_regex
     * @private
     */
    private $_pcre_regex;
    /**
     * @param $dtd_regex Allowed child pattern from the DTD
     */
    public function __construct($dtd_regex) {
        $this->dtd_regex = $dtd_regex;
        $this->_compileRegex();
    }
    /**
     * Compiles the PCRE regex from a DTD regex ($dtd_regex to $_pcre_regex)
     */
    protected function _compileRegex() {
        $raw = str_replace(' ', '', $this->dtd_regex);
        if ($raw{0} != '(') {
            $raw = "($raw)";
        }
        $el = '[#a-zA-Z0-9_.-]+';
        $reg = $raw;
        
        // COMPLICATED! AND MIGHT BE BUGGY! I HAVE NO CLUE WHAT I'M
        // DOING! Seriously: if there's problems, please report them.
        
        // collect all elements into the $elements array
        preg_match_all("/$el/", $reg, $matches);
        foreach ($matches[0] as $match) {
            $this->elements[$match] = true;
        }
        
        // setup all elements as parentheticals with leading commas
        $reg = preg_replace("/$el/", '(,\\0)', $reg);
        
        // remove commas when they were not solicited
        $reg = preg_replace("/([^,(|]\(+),/", '\\1', $reg);
        
        // remove all non-paranthetical commas: they are handled by first regex
        $reg = preg_replace("/,\(/", '(', $reg);
        
        $this->_pcre_regex = $reg;
    }
    public function validateChildren($tokens_of_children, $config, $context) {
        $list_of_children = '';
        $nesting = 0; // depth into the nest
        foreach ($tokens_of_children as $token) {
            if (!empty($token->is_whitespace)) continue;
            
            $is_child = ($nesting == 0); // direct
            
            if ($token instanceof HTMLPurifier_Token_Start) {
                $nesting++;
            } elseif ($token instanceof HTMLPurifier_Token_End) {
                $nesting--;
            }
            
            if ($is_child) {
                $list_of_children .= $token->name . ',';
            }
        }
        // add leading comma to deal with stray comma declarations
        $list_of_children = ',' . rtrim($list_of_children, ',');
        $okay =
            preg_match(
                '/^,?'.$this->_pcre_regex.'$/',
                $list_of_children
            );
        
        return (bool) $okay;
    }
}




/**
 * Definition that disallows all elements.
 * @warning validateChildren() in this class is actually never called, because
 *          empty elements are corrected in HTMLPurifier_Strategy_MakeWellFormed
 *          before child definitions are parsed in earnest by
 *          HTMLPurifier_Strategy_FixNesting.
 */
class HTMLPurifier_ChildDef_Empty extends HTMLPurifier_ChildDef
{
    public $allow_empty = true;
    public $type = 'empty';
    public function __construct() {}
    public function validateChildren($tokens_of_children, $config, $context) {
        return array();
    }
}




/**
 * Definition that allows a set of elements, but disallows empty children.
 */
class HTMLPurifier_ChildDef_Required extends HTMLPurifier_ChildDef
{
    /**
     * Lookup table of allowed elements.
     * @public
     */
    public $elements = array();
    /**
     * @param $elements List of allowed element names (lowercase).
     */
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
                if (empty($i)) unset($elements[$i]); // remove blank
            }
        }
        $this->elements = $elements;
    }
    public $allow_empty = false;
    public $type = 'required';
    public function validateChildren($tokens_of_children, $config, $context) {
        // if there are no tokens, delete parent node
        if (empty($tokens_of_children)) return false;
        
        // the new set of children
        $result = array();
        
        // current depth into the nest
        $nesting = 0;
        
        // whether or not we're deleting a node
        $is_deleting = false;
        
        // whether or not parsed character data is allowed
        // this controls whether or not we silently drop a tag
        // or generate escaped HTML from it
        $pcdata_allowed = isset($this->elements['#PCDATA']);
        
        // a little sanity check to make sure it's not ALL whitespace
        $all_whitespace = true;
        
        // some configuration
        $escape_invalid_children = $config->get('Core', 'EscapeInvalidChildren');
        
        // generator
        static $gen = null;
        if ($gen === null) {
            $gen = new HTMLPurifier_Generator();
        }
        
        foreach ($tokens_of_children as $token) {
            if (!empty($token->is_whitespace)) {
                $result[] = $token;
                continue;
            }
            $all_whitespace = false; // phew, we're not talking about whitespace
            
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
                // drop silently
            }
        }
        if (empty($result)) return false;
        if ($all_whitespace) return false;
        if ($tokens_of_children == $result) return true;
        return $result;
    }
}




/**
 * Definition that allows a set of elements, and allows no children.
 * @note This is a hack to reuse code from HTMLPurifier_ChildDef_Required,
 *       really, one shouldn't inherit from the other.  Only altered behavior
 *       is to overload a returned false with an array.  Thus, it will never
 *       return false.
 */
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




/**
 * Takes the contents of blockquote when in strict and reformats for validation.
 */
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
            // allow all inline elements
            $this->real_elements = $this->elements;
            $this->fake_elements = $def->info_content_sets['Flow'];
            $this->fake_elements['#PCDATA'] = true;
            $this->init = true;
        }
        
        // trick the parent class into thinking it allows more
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
        
        // assuming that there are no comment tokens
        foreach ($result as $i => $token) {
            $token = $result[$i];
            // ifs are nested for readability
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
                    // starting tokens have been inline text / empty
                    if ($token instanceof HTMLPurifier_Token_Start || $token instanceof HTMLPurifier_Token_Empty) {
                        if (isset($this->elements[$token->name])) {
                            // ended
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




/**
 * Definition for tables
 */
class HTMLPurifier_ChildDef_Table extends HTMLPurifier_ChildDef
{
    public $allow_empty = false;
    public $type = 'table';
    public $elements = array('tr' => true, 'tbody' => true, 'thead' => true,
        'tfoot' => true, 'caption' => true, 'colgroup' => true, 'col' => true);
    public function __construct() {}
    public function validateChildren($tokens_of_children, $config, $context) {
        if (empty($tokens_of_children)) return false;
        
        // this ensures that the loop gets run one last time before closing
        // up. It's a little bit of a hack, but it works! Just make sure you
        // get rid of the token later.
        $tokens_of_children[] = false;
        
        // only one of these elements is allowed in a table
        $caption = false;
        $thead   = false;
        $tfoot   = false;
        
        // as many of these as you want
        $cols    = array();
        $content = array();
        
        $nesting = 0; // current depth so we can determine nodes
        $is_collecting = false; // are we globbing together tokens to package
                                // into one of the collectors?
        $collection = array(); // collected nodes
        $tag_index = 0; // the first node might be whitespace,
                            // so this tells us where the start tag is
        
        foreach ($tokens_of_children as $token) {
            $is_child = ($nesting == 0);
            
            if ($token === false) {
                // terminating sequence started
            } elseif ($token instanceof HTMLPurifier_Token_Start) {
                $nesting++;
            } elseif ($token instanceof HTMLPurifier_Token_End) {
                $nesting--;
            }
            
            // handle node collection
            if ($is_collecting) {
                if ($is_child) {
                    // okay, let's stash the tokens away
                    // first token tells us the type of the collection
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
                            // access the appropriate variable, $thead or $tfoot
                            $var = $collection[$tag_index]->name;
                            if ($$var === false) {
                                $$var = $collection;
                            } else {
                                // transmutate the first and less entries into
                                // tbody tags, and then put into content
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
                    // add the node to the collection
                    $collection[] = $token;
                }
            }
            
            // terminate
            if ($token === false) break;
            
            if ($is_child) {
                // determine what we're dealing with
                if ($token->name == 'col') {
                    // the only empty tag in the possie, we can handle it
                    // immediately
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
            // grab the trailing space
            $ret = array_merge($ret, $collection);
        }
        
        array_pop($tokens_of_children); // remove phantom token
        
        return ($ret === $tokens_of_children) ? true : $ret;
        
    }
}




/**
 * Structure object containing definition of a directive.
 * @note This structure does not contain default values
 */
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
    
    /**
     * Allowed type of the directive. Values are:
     *      - string
     *      - istring (case insensitive string)
     *      - int
     *      - float
     *      - bool
     *      - lookup (array of value => true)
     *      - list (regular numbered index array)
     *      - hash (array of key => value)
     *      - mixed (anything goes)
     */
    public $type = 'mixed';
    
    /**
     * Is null allowed? Has no effect for mixed type.
     * @bool
     */
    public $allow_null = false;
    
    /**
     * Lookup table of allowed values of the element, bool true if all allowed.
     */
    public $allowed = true;
    
    /**
     * Hash of value aliases, i.e. values that are equivalent.
     */
    public $aliases = array();
    
}




/**
 * Structure object describing a directive alias
 */
class HTMLPurifier_ConfigDef_DirectiveAlias extends HTMLPurifier_ConfigDef
{
    public $class = 'alias';
    
    /**
     * Namespace being aliased to
     */
    public $namespace;
    /**
     * Directive being aliased to
     */
    public $name;
    
    public function __construct($namespace, $name) {
        $this->namespace = $namespace;
        $this->name = $name;
    }
}




/**
 * Structure object describing of a namespace
 */
class HTMLPurifier_ConfigDef_Namespace extends HTMLPurifier_ConfigDef
{
    public $class = 'namespace';
}




class HTMLPurifier_DefinitionCache_Decorator extends HTMLPurifier_DefinitionCache
{
    
    /**
     * Cache object we are decorating
     */
    public $cache;
    
    public function __construct() {}
    
    /**
     * Lazy decorator function
     * @param $cache Reference to cache object to decorate
     */
    public function decorate(&$cache) {
        $decorator = $this->copy();
        // reference is necessary for mocks in PHP 4
        $decorator->cache =& $cache;
        $decorator->type  = $cache->type;
        return $decorator;
    }
    
    /**
     * Cross-compatible clone substitute
     */
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




/**
 * Null cache object to use when no caching is on.
 */
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
    
    /**
     * Generates the file path to the serial file corresponding to
     * the configuration and definition name
     * @todo Make protected
     */
    public function generateFilePath($config) {
        $key = $this->generateKey($config);
        return $this->generateDirectoryPath($config) . '/' . $key . '.ser';
    }
    
    /**
     * Generates the path to the directory contain this cache's serial files
     * @note No trailing slash
     * @todo Make protected
     */
    public function generateDirectoryPath($config) {
        return AEOLUS_HOME.'/tmp/guard';
    }
    
    /**
     * Convenience wrapper function for file_put_contents
     * @param $file File name to write to
     * @param $data Data to write into file
     * @return Number of bytes written if success, or false if failure.
     */
    private function _write($file, $data) {
        return file_put_contents($file, $data);
    }
    
    /**
     * Prepares the directory that this type stores the serials in
     * @return True if successful
     */
    private function _prepareDir($config) {
        $directory = $this->generateDirectoryPath($config);
        if (!is_dir($directory)) {
            $base = $this->generateBaseDirectoryPath($config);
            if (!is_dir($base)) {
                trigger_error('Base directory '.$base.' does not exist,
                    please create or change using %Cache.SerializerPath',
                    E_USER_ERROR);
                return false;
            } elseif (!$this->_testPermissions($base)) {
                return false;
            }
            $old = umask(0022); // disable group and world writes
            mkdir($directory);
            umask($old);
        } elseif (!$this->_testPermissions($directory)) {
            return false;
        }
        return true;
    }
    
    /**
     * Tests permissions on a directory and throws out friendly
     * error messages and attempts to chmod it itself if possible
     */
    private function _testPermissions($dir) {
        // early abort, if it is writable, everything is hunky-dory
        if (is_writable($dir)) return true;
        if (!is_dir($dir)) {
            // generally, you'll want to handle this beforehand
            // so a more specific error message can be given
            trigger_error('Directory '.$dir.' does not exist',
                E_USER_ERROR);
            return false;
        }
        if (function_exists('posix_getuid')) {
            // POSIX system, we can give more specific advice
            if (fileowner($dir) === posix_getuid()) {
                // we can chmod it ourselves
                chmod($dir, 0755);
                return true;
            } elseif (filegroup($dir) === posix_getgid()) {
                $chmod = '775';
            } else {
                // PHP's probably running as nobody, so we'll
                // need to give global permissions
                $chmod = '777';
            }
            trigger_error('Directory '.$dir.' not writable, '.
                'please chmod to ' . $chmod,
                E_USER_ERROR);
        } else {
            // generic error message
            trigger_error('Directory '.$dir.' not writable, '.
                'please alter file permissions',
                E_USER_ERROR);
        }
        return false;
    }
    
}




/**
 * Definition cache decorator class that cleans up the cache
 * whenever there is a cache miss.
 */
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




/**
 * Definition cache decorator class that saves all cache retrievals
 * to PHP's memory; good for unit tests or circumstances where 
 * there are lots of configuration objects floating around.
 */
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




/**
 * XHTML 1.1 Bi-directional Text Module, defines elements that
 * declare directionality of content. Text Extension Module.
 */
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
                'dir' => 'Enum#ltr,rtl', // required
                // The Abstract Module specification has the attribute
                // inclusions wrong for bdo: bdo allows Lang
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
            // 'xml:space' => false,
            'class' => 'NMTOKENS',
            'id' => 'ID',
            'title' => 'CDATA',
        ),
        'Lang' => array(),
        'I18N' => array(
            0 => array('Lang'), // proprietary, for xml:lang/lang
        ),
        'Common' => array(
            0 => array('Core', 'I18N')
        )
    );
}




/**
 * XHTML 1.1 Edit Module, defines editing-related elements. Text Extension
 * Module.
 */
class HTMLPurifier_HTMLModule_Edit extends HTMLPurifier_HTMLModule
{
    
    public $name = 'Edit';
    
    public function __construct() {
        $contents = 'Chameleon: #PCDATA | Inline ! #PCDATA | Flow';
        $attr = array(
            'cite' => 'URI',
            // 'datetime' => 'Datetime', // not implemented
        );
        $this->addElement('del', 'Inline', $contents, 'Common', $attr);
        $this->addElement('ins', 'Inline', $contents, 'Common', $attr);
    }
    
    // HTML 4.01 specifies that ins/del must not contain block
    // elements when used in an inline context, chameleon is
    // a complicated workaround to acheive this effect
    
    // Inline context ! Block context (exclamation mark is
    // separator, see getChildDef for parsing)
    
    public $defines_child_def = true;
    public function getChildDef($def) {
        if ($def->content_model_type != 'chameleon') return false;
        $value = explode('!', $def->content_model);
        return new HTMLPurifier_ChildDef_Chameleon($value[0], $value[1]);
    }
    
}




/**
 * XHTML 1.1 Hypertext Module, defines hypertext links. Core Module.
 */
class HTMLPurifier_HTMLModule_Hypertext extends HTMLPurifier_HTMLModule
{
    
    public $name = 'Hypertext';
    
    public function __construct() {
        $a = $this->addElement(
            'a', 'Inline', 'Inline', 'Common',
            array(
                // 'accesskey' => 'Character',
                // 'charset' => 'Charset',
                'href' => 'URI',
                // 'hreflang' => 'LanguageCode',
                'rel' => new HTMLPurifier_AttrDef_HTML_LinkTypes('rel'),
                'rev' => new HTMLPurifier_AttrDef_HTML_LinkTypes('rev'),
                // 'tabindex' => 'Number',
                // 'type' => 'ContentType',
            )
        );
        $a->excludes = array('a' => true);
    }
    
}




/**
 * XHTML 1.1 Image Module provides basic image embedding.
 * @note There is specialized code for removing empty images in
 *       HTMLPurifier_Strategy_RemoveForeignElements
 */
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
                'src*' => new HTMLPurifier_AttrDef_URI(true), // embedded
                'width' => 'Length'
            )
        );
        // kind of strange, but splitting things up would be inefficient
        $img->attr_transform_pre[] =
        $img->attr_transform_post[] =
            new HTMLPurifier_AttrTransform_ImgRequired();
    }
    
}




/**
 * XHTML 1.1 Legacy module defines elements that were previously 
 * deprecated.
 * 
 * @note Not all legacy elements have been implemented yet, which
 *       is a bit of a reverse problem as compared to browsers! In
 *       addition, this legacy module may implement a bit more than
 *       mandated by XHTML 1.1.
 * 
 * This module can be used in combination with TransformToStrict in order
 * to transform as many deprecated elements as possible, but retain
 * questionably deprecated elements that do not have good alternatives
 * as well as transform elements that don't have an implementation.
 * See docs/ref-strictness.txt for more details.
 */

class HTMLPurifier_HTMLModule_Legacy extends HTMLPurifier_HTMLModule
{
    
    public $name = 'Legacy';
    
    public function __construct() {
        
        $this->addElement('basefont', 'Inline', 'Empty', false, array(
            'color' => 'Color',
            'face' => 'Text', // extremely broad, we should
            'size' => 'Text', // tighten it
            'id' => 'ID'
        ));
        $this->addElement('center', 'Block', 'Flow', 'Common');
        $this->addElement('dir', 'Block', 'Required: li', 'Common', array(
            'compact' => 'Bool#compact'
        ));
        $this->addElement('font', 'Inline', 'Inline', array('Core', 'I18N'), array(
            'color' => 'Color',
            'face' => 'Text', // extremely broad, we should
            'size' => 'Text', // tighten it
        ));
        $this->addElement('menu', 'Block', 'Required: li', 'Common', array(
            'compact' => 'Bool#compact'
        ));
        $this->addElement('s', 'Inline', 'Inline', 'Common');
        $this->addElement('strike', 'Inline', 'Inline', 'Common');
        $this->addElement('u', 'Inline', 'Inline', 'Common');
        
        // setup modifications to old elements
        
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
        
        // figure out this integer business
        
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
        
        // script omitted
        
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




/**
 * XHTML 1.1 List Module, defines list-oriented elements. Core Module.
 */
class HTMLPurifier_HTMLModule_List extends HTMLPurifier_HTMLModule
{
    
    public $name = 'List';
    
    // According to the abstract schema, the List content set is a fully formed
    // one or more expr, but it invariably occurs in an optional declaration
    // so we're not going to do that subtlety. It might cause trouble
    // if a user defines "List" and expects that multiple lists are
    // allowed to be specified, but then again, that's not very intuitive.
    // Furthermore, the actual XML Schema may disagree. Regardless,
    // we don't have support for such nested expressions without using
    // the incredibly inefficient and draconic Custom ChildDef.
    
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




/**
 * XHTML 1.1 Object Module, defines elements for generic object inclusion
 * @warning Users will commonly use <embed> to cater to legacy browsers: this
 *      module does not allow this sort of behavior
 */
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




/**
 * XHTML 1.1 Presentation Module, defines simple presentation-related
 * markup. Text Extension Module.
 * @note The official XML Schema and DTD specs further divide this into
 *       two modules:
 *          - Block Presentation (hr)
 *          - Inline Presentation (b, big, i, small, sub, sup, tt)
 *       We have chosen not to heed this distinction, as content_sets
 *       provides satisfactory disambiguation.
 */
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




/**
 * Module defines proprietary tags and attributes in HTML.
 * @warning If this module is enabled, standards-compliance is off!
 */
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




/**
 * XHTML 1.1 Ruby Annotation Module, defines elements that indicate
 * short runs of text alongside base text for annotation or pronounciation.
 */
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




/*

WARNING: THIS MODULE IS EXTREMELY DANGEROUS AS IT ENABLES INLINE SCRIPTING
INSIDE HTML PURIFIER DOCUMENTS. USE ONLY WITH TRUSTED USER INPUT!!!

*/

/**
 * XHTML 1.1 Scripting module, defines elements that are used to contain
 * information pertaining to executable scripts or the lack of support
 * for executable scripts.
 * @note This module does not contain inline scripting elements
 */
class HTMLPurifier_HTMLModule_Scripting extends HTMLPurifier_HTMLModule
{
    public $name = 'Scripting';
    public $elements = array('script', 'noscript');
    public $content_sets = array('Block' => 'script | noscript', 'Inline' => 'script | noscript');
    public $safe = false;
    
    public function __construct() {
        // TODO: create custom child-definition for noscript that
        // auto-wraps stray #PCDATA in a similar manner to 
        // blockquote's custom definition (we would use it but
        // blockquote's contents are optional while noscript's contents
        // are required)
        
        // TODO: convert this to new syntax, main problem is getting
        // both content sets working
        
        // In theory, this could be safe, but I don't see any reason to
        // allow it.
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




/**
 * XHTML 1.1 Edit Module, defines editing-related elements. Text Extension
 * Module.
 */
class HTMLPurifier_HTMLModule_StyleAttribute extends HTMLPurifier_HTMLModule
{
    
    public $name = 'StyleAttribute';
    public $attr_collections = array(
        // The inclusion routine differs from the Abstract Modules but
        // is in line with the DTD and XML Schemas.
        'Style' => array('style' => false), // see constructor
        'Core' => array(0 => array('Style'))
    );
    
    public function __construct() {
        $this->attr_collections['Style']['style'] = new HTMLPurifier_AttrDef_CSS();
    }
    
}




/**
 * XHTML 1.1 Tables Module, fully defines accessible table elements.
 */
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
        
        // common attributes
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




/**
 * XHTML 1.1 Target Module, defines target attribute in link elements.
 */
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




/**
 * XHTML 1.1 Text Module, defines basic text containers. Core Module.
 * @note In the normative XML Schema specification, this module
 *       is further abstracted into the following modules:
 *          - Block Phrasal (address, blockquote, pre, h1, h2, h3, h4, h5, h6)
 *          - Block Structural (div, p)
 *          - Inline Phrasal (abbr, acronym, cite, code, dfn, em, kbd, q, samp, strong, var)
 *          - Inline Structural (br, span)
 *       This module, functionally, does not distinguish between these
 *       sub-modules, but the code is internally structured to reflect
 *       these distinctions.
 */
class HTMLPurifier_HTMLModule_Text extends HTMLPurifier_HTMLModule
{
    
    public $name = 'Text';
    public $content_sets = array(
        'Flow' => 'Heading | Block | Inline'
    );
    
    public function __construct() {
        
        // Inline Phrasal -------------------------------------------------
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
        
        // Inline Structural ----------------------------------------------
        $this->addElement('span', 'Inline', 'Inline', 'Common');
        $this->addElement('br',   'Inline', 'Empty',  'Core');
        
        // Block Phrasal --------------------------------------------------
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
        
        // Block Structural -----------------------------------------------
        $this->addElement('p', 'Block', 'Inline', 'Common');
        $this->addElement('div', 'Block', 'Flow', 'Common');
        
    }
    
}




/**
 * Abstract class for a set of proprietary modules that clean up (tidy)
 * poorly written HTML.
 * @todo Figure out how to protect some of these methods/properties
 */
class HTMLPurifier_HTMLModule_Tidy extends HTMLPurifier_HTMLModule
{
    
    /**
     * List of supported levels. Index zero is a special case "no fixes"
     * level.
     */
    public $levels = array(0 => 'none', 'light', 'medium', 'heavy');
    
    /**
     * Default level to place all fixes in. Disabled by default
     */
    public $defaultLevel = null;
    
    /**
     * Lists of fixes used by getFixesForLevel(). Format is:
     *      HTMLModule_Tidy->fixesForLevel[$level] = array('fix-1', 'fix-2');
     */
    public $fixesForLevel = array(
        'light'  => array(),
        'medium' => array(),
        'heavy'  => array()
    );
    
    /**
     * Lazy load constructs the module by determining the necessary
     * fixes to create and then delegating to the populate() function.
     * @todo Wildcard matching and error reporting when an added or
     *       subtracted fix has no effect.
     */
    public function construct($config) {
        
        // create fixes, initialize fixesForLevel
        $fixes = $this->makeFixes();
        $this->makeFixesForLevel($fixes);
        
        // figure out which fixes to use
        $level = $config->get('HTML', 'TidyLevel');
        $fixes_lookup = $this->getFixesForLevel($level);
        
        // get custom fix declarations: these need namespace processing
        $add_fixes    = $config->get('HTML', 'TidyAdd');
        $remove_fixes = $config->get('HTML', 'TidyRemove');
        
        foreach ($fixes as $name => $fix) {
            // needs to be refactored a little to implement globbing
            if (
                isset($remove_fixes[$name]) ||
                (!isset($add_fixes[$name]) && !isset($fixes_lookup[$name]))
            ) {
                unset($fixes[$name]);
            }
        }
        
        // populate this module with necessary fixes
        $this->populate($fixes);
        
    }
    
    /**
     * Retrieves all fixes per a level, returning fixes for that specific
     * level as well as all levels below it.
     * @param $level String level identifier, see $levels for valid values
     * @return Lookup up table of fixes
     */
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
    
    /**
     * Dynamically populates the $fixesForLevel member variable using
     * the fixes array. It may be custom overloaded, used in conjunction
     * with $defaultLevel, or not used at all.
     */
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
    
    /**
     * Populates the module with transforms and other special-case code
     * based on a list of fixes passed to it
     * @param $lookup Lookup table of fixes to activate
     */
    public function populate($fixes) {
        foreach ($fixes as $name => $fix) {
            // determine what the fix is for
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
                    // PHP does some weird parsing when I do
                    // $e->$type[$attr], so I have to assign a ref.
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
    
    /**
     * Parses a fix name and determines what kind of fix it is, as well
     * as other information defined by the fix
     * @param $name String name of fix
     * @return array(string $fix_type, array $fix_parameters)
     * @note $fix_parameters is type dependant, see populate() for usage
     *       of these parameters
     */
    public function getFixType($name) {
        // parse it
        $property = $attr = null;
        if (strpos($name, '#') !== false) list($name, $property) = explode('#', $name);
        if (strpos($name, '@') !== false) list($name, $attr)     = explode('@', $name);
        
        // figure out the parameters
        $params = array();
        if ($name !== '')    $params['element'] = $name;
        if (!is_null($attr)) $params['attr'] = $attr;
        
        // special case: attribute transform
        if (!is_null($attr)) {
            if (is_null($property)) $property = 'pre';
            $type = 'attr_transform_' . $property;
            return array($type, $params);
        }
        
        // special case: tag transform
        if (is_null($property)) {
            return array('tag_transform', $params);
        }
        
        return array($property, $params);
        
    }
    
    /**
     * Defines all fixes the module will perform in a compact
     * associative array of fix name to fix implementation.
     */
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
        
        // == deprecated tag transforms ===================================
        
        $r['font']   = new HTMLPurifier_TagTransform_Font();
        $r['menu']   = new HTMLPurifier_TagTransform_Simple('ul');
        $r['dir']    = new HTMLPurifier_TagTransform_Simple('ul');
        $r['center'] = new HTMLPurifier_TagTransform_Simple('div',  'text-align:center;');
        $r['u']      = new HTMLPurifier_TagTransform_Simple('span', 'text-decoration:underline;');
        $r['s']      = new HTMLPurifier_TagTransform_Simple('span', 'text-decoration:line-through;');
        $r['strike'] = new HTMLPurifier_TagTransform_Simple('span', 'text-decoration:line-through;');
        
        // == deprecated attribute transforms =============================
        
        $r['caption@align'] = 
            new HTMLPurifier_AttrTransform_EnumToCSS('align', array(
                // we're following IE's behavior, not Firefox's, due
                // to the fact that no one supports caption-side:right,
                // W3C included (with CSS 2.1). This is a slightly
                // unreasonable attribute!
                'left'   => 'text-align:left;',
                'right'  => 'text-align:right;',
                'top'    => 'caption-side:top;',
                'bottom' => 'caption-side:bottom;' // not supported by IE
            ));
        
        // @align for img -------------------------------------------------
        $r['img@align'] =
            new HTMLPurifier_AttrTransform_EnumToCSS('align', array(
                'left'   => 'float:left;',
                'right'  => 'float:right;',
                'top'    => 'vertical-align:top;',
                'middle' => 'vertical-align:middle;',
                'bottom' => 'vertical-align:baseline;',
            ));
        
        // @align for table -----------------------------------------------
        $r['table@align'] =
            new HTMLPurifier_AttrTransform_EnumToCSS('align', array(
                'left'   => 'float:left;',
                'center' => 'margin-left:auto;margin-right:auto;',
                'right'  => 'float:right;'
            ));
        
        // @align for hr -----------------------------------------------
        $r['hr@align'] =
            new HTMLPurifier_AttrTransform_EnumToCSS('align', array(
                // we use both text-align and margin because these work
                // for different browsers (IE and Firefox, respectively)
                // and the melange makes for a pretty cross-compatible
                // solution
                'left'   => 'margin-left:0;margin-right:auto;text-align:left;',
                'center' => 'margin-left:auto;margin-right:auto;text-align:center;',
                'right'  => 'margin-left:auto;margin-right:0;text-align:right;'
            ));
        
        // @align for h1, h2, h3, h4, h5, h6, p, div ----------------------
        // {{{
            $align_lookup = array();
            $align_values = array('left', 'right', 'center', 'justify');
            foreach ($align_values as $v) $align_lookup[$v] = "text-align:$v;";
        // }}}
        $r['h1@align'] =
        $r['h2@align'] =
        $r['h3@align'] =
        $r['h4@align'] =
        $r['h5@align'] =
        $r['h6@align'] =
        $r['p@align']  =
        $r['div@align'] = 
            new HTMLPurifier_AttrTransform_EnumToCSS('align', $align_lookup);
        
        // @bgcolor for table, tr, td, th ---------------------------------
        $r['table@bgcolor'] =
        $r['td@bgcolor'] =
        $r['th@bgcolor'] =
            new HTMLPurifier_AttrTransform_BgColor();
        
        // @border for img ------------------------------------------------
        $r['img@border'] = new HTMLPurifier_AttrTransform_Border();
        
        // @clear for br --------------------------------------------------
        $r['br@clear'] =
            new HTMLPurifier_AttrTransform_EnumToCSS('clear', array(
                'left'  => 'clear:left;',
                'right' => 'clear:right;',
                'all'   => 'clear:both;',
                'none'  => 'clear:none;',
            ));
        
        // @height for td, th ---------------------------------------------
        $r['td@height'] = 
        $r['th@height'] =
            new HTMLPurifier_AttrTransform_Length('height');
        
        // @hspace for img ------------------------------------------------
        $r['img@hspace'] = new HTMLPurifier_AttrTransform_ImgSpace('hspace');
        
        // @name for img, a -----------------------------------------------
        $r['img@name'] = 
        $r['a@name'] = new HTMLPurifier_AttrTransform_Name();
        
        // @noshade for hr ------------------------------------------------
        // this transformation is not precise but often good enough.
        // different browsers use different styles to designate noshade
        $r['hr@noshade'] =
            new HTMLPurifier_AttrTransform_BoolToCSS(
                'noshade',
                'color:#808080;background-color:#808080;border:0;'
            );
        
        // @nowrap for td, th ---------------------------------------------
        $r['td@nowrap'] = 
        $r['th@nowrap'] =
            new HTMLPurifier_AttrTransform_BoolToCSS(
                'nowrap',
                'white-space:nowrap;'
            );
        
        // @size for hr  --------------------------------------------------
        $r['hr@size'] = new HTMLPurifier_AttrTransform_Length('size', 'height');
        
        // @type for li, ol, ul -------------------------------------------
        // {{{
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
        // }}}
        
        $r['ul@type'] = new HTMLPurifier_AttrTransform_EnumToCSS('type', $ul_types);
        $r['ol@type'] = new HTMLPurifier_AttrTransform_EnumToCSS('type', $ol_types, true);
        $r['li@type'] = new HTMLPurifier_AttrTransform_EnumToCSS('type', $li_types, true);
        
        // @vspace for img ------------------------------------------------
        $r['img@vspace'] = new HTMLPurifier_AttrTransform_ImgSpace('vspace');
        
        // @width for hr, td, th ------------------------------------------
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




/**
 * Injector that auto paragraphs text in the root node based on
 * double-spacing.
 */
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
            // case 1: we're in root node (and it allows paragraphs)
            $token = array($this->_pStart());
            $this->_splitText($text, $token);
        } elseif ($this->currentNesting[count($this->currentNesting)-1]->name == 'p') {
            // case 2: we're in a paragraph
            $token = array();
            $this->_splitText($text, $token);
        } elseif ($this->allowsElement('p')) {
            // case 3: we're in an element that allows paragraphs
            if (strpos($text, "\n\n") !== false) {
                // case 3.1: this text node has a double-newline
                $token = array($this->_pStart());
                $this->_splitText($text, $token);
            } else {
                $ok = false;
                // test if up-coming tokens are either block or have
                // a double newline in them
                $nesting = 0;
                for ($i = $this->inputIndex + 1; isset($this->inputTokens[$i]); $i++) {
                    if ($this->inputTokens[$i] instanceof HTMLPurifier_Token_Start){
                        if (!$this->_isInline($this->inputTokens[$i])) {
                            // we haven't found a double-newline, and
                            // we've hit a block element, so don't paragraph
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
                        // found it!
                        if (strpos($this->inputTokens[$i]->data, "\n\n") !== false) {
                            $ok = true;
                            break;
                        }
                    }
                }
                if ($ok) {
                    // case 3.2: this text node is next to another node
                    // that will start a paragraph
                    $token = array($this->_pStart(), $token);
                }
            }
        }
        
    }
    
    public function handleElement(&$token) {
        // check if we're inside a tag already
        if (!empty($this->currentNesting)) {
            if ($this->allowsElement('p')) {
                // special case: we're in an element that allows paragraphs
                
                // this token is already paragraph, abort
                if ($token->name == 'p') return;
                
                // this token is a block level, abort
                if (!$this->_isInline($token)) return;
                
                // check if this token is adjacent to the parent token
                $prev = $this->inputTokens[$this->inputIndex - 1];
                if (!$prev instanceof HTMLPurifier_Token_Start) {
                    // not adjacent, we can abort early
                    // add lead paragraph tag if our token is inline
                    // and the previous tag was an end paragraph
                    if (
                        $prev->name == 'p' && $prev instanceof HTMLPurifier_Token_End &&
                        $this->_isInline($token)
                    ) {
                        $token = array($this->_pStart(), $token);
                    }
                    return;
                }
                
                // this token is the first child of the element that allows
                // paragraph. We have to peek ahead and see whether or not
                // there is anything inside that suggests that a paragraph
                // will be needed
                $ok = false;
                // maintain a mini-nesting counter, this lets us bail out
                // early if possible
                $j = 1; // current nesting, one is due to parent (we recalculate current token)
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
        
        // check if the start tag counts as a "block" element
        if (!$this->_isInline($token)) return;
        
        // append a paragraph tag before the token
        $token = array($this->_pStart(), $token);
    }
    
    /**
     * Splits up a text in paragraph tokens and appends them
     * to the result stream that will replace the original
     * @param $data String text data that will be processed
     *    into paragraphs
     * @param $result Reference to array of tokens that the
     *    tags will be appended onto
     * @param $config Instance of HTMLPurifier_Config
     * @param $context Instance of HTMLPurifier_Context
     */
    private function _splitText($data, &$result) {
        $raw_paragraphs = explode("\n\n", $data);
        
        // remove empty paragraphs
        $paragraphs = array();
        $needs_start = false;
        $needs_end   = false;
        
        $c = count($raw_paragraphs);
        if ($c == 1) {
            // there were no double-newlines, abort quickly
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
                // The empty result indicates that the AutoParagraph
                // injector did not add any start paragraph tokens.
                // The fact that the first paragraph is empty indicates
                // that there was a double-newline at the start of the
                // data.
                // Combined together, this means that we are in a paragraph,
                // and the newline means we should start a new one.
                $result[] = new HTMLPurifier_Token_End('p');
                // However, the start token should only be added if 
                // there is more processing to be done (i.e. there are
                // real paragraphs in here). If there are none, the
                // next start paragraph tag will be handled by the
                // next run-around the injector
                $needs_start = true;
            } elseif ($i + 1 == $c) {
                // a double-paragraph at the end indicates that
                // there is an overriding need to start a new paragraph
                // for the next section. This has no effect until
                // we've processed all of the other paragraphs though
                $needs_end = true;
            }
        }
        
        // check if there are no "real" paragraphs to be processed
        if (empty($paragraphs)) {
            return;
        }
        
        // add a start tag if an end tag was added while processing
        // the raw paragraphs (that happens if there's a leading double
        // newline)
        if ($needs_start) $result[] = $this->_pStart();
        
        // append the paragraphs onto the result
        foreach ($paragraphs as $par) {
            $result[] = new HTMLPurifier_Token_Text($par);
            $result[] = new HTMLPurifier_Token_End('p');
            $result[] = $this->_pStart();
        }
        
        // remove trailing start token, if one is needed, it will
        // be handled the next time this injector is called
        array_pop($result);
        
        // check the outside to determine whether or not the
        // end paragraph tag should be removed. It should be removed
        // unless the next non-whitespace token is a paragraph
        // or a block element.
        $remove_paragraph_end = true;
        
        if (!$needs_end) {
            // Start of the checks one after the current token's index
            for ($i = $this->inputIndex + 1; isset($this->inputTokens[$i]); $i++) {
                if ($this->inputTokens[$i] instanceof HTMLPurifier_Token_Start || $this->inputTokens[$i] instanceof HTMLPurifier_Token_Empty) {
                    $remove_paragraph_end = $this->_isInline($this->inputTokens[$i]);
                }
                // check if we can abort early (whitespace means we carry-on!)
                if ($this->inputTokens[$i] instanceof HTMLPurifier_Token_Text && !$this->inputTokens[$i]->is_whitespace) break;
                // end tags will automatically be handled by MakeWellFormed,
                // so we don't have to worry about them
                if ($this->inputTokens[$i] instanceof HTMLPurifier_Token_End) break;
            }
        } else {
            $remove_paragraph_end = false;
        }
        
        // check the outside to determine whether or not the
        // end paragraph tag should be removed
        if ($remove_paragraph_end) {
            array_pop($result);
        }
        
    }
    
    /**
     * Returns true if passed token is inline (and, ergo, allowed in
     * paragraph tags)
     */
    private function _isInline($token) {
        return isset($this->htmlDefinition->info['p']->child->elements[$token->name]);
    }
    
}




/**
 * Injector that converts http, https and ftp text URLs to actual links.
 */
class HTMLPurifier_Injector_Linkify extends HTMLPurifier_Injector
{
    
    public $name = 'Linkify';
    public $needed = array('a' => array('href'));
    
    public function handleText(&$token) {
        if (!$this->allowsElement('a')) return;
        
        if (strpos($token->data, '://') === false) {
            // our really quick heuristic failed, abort
            // this may not work so well if we want to match things like
            // "google.com", but then again, most people don't
            return;
        }
        
        // there is/are URL(s). Let's split the string:
        // Note: this regex is extremely permissive
        $bits = preg_split('#((?:https?|ftp)://[^\s\'"<>()]+)#S', $token->data, -1, PREG_SPLIT_DELIM_CAPTURE);
        
        $token = array();
        
        // $i = index
        // $c = count
        // $l = is link
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




/**
 * Injector that converts configuration directive syntax %Namespace.Directive
 * to links
 */
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
        
        // $i = index
        // $c = count
        // $l = is link
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




/**
 * Parser that uses PHP 5's DOM extension (part of the core).
 * 
 * In PHP 5, the DOM XML extension was revamped into DOM and added to the core.
 * It gives us a forgiving HTML parser, which we use to transform the HTML
 * into a DOM, and then into the tokens.  It is blazingly fast (for large
 * documents, it performs twenty times faster than
 * HTMLPurifier_Lexer_DirectLex,and is the default choice for PHP 5. 
 * 
 * @note Any empty elements will have empty tokens associated with them, even if
 * this is prohibited by the spec. This is cannot be fixed until the spec
 * comes into play.
 * 
 * @note PHP's DOM extension does not actually parse any entities, we use
 *       our own function to do that.
 * 
 * @warning DOM tends to drop whitespace, which may wreak havoc on indenting.
 *          If this is a huge problem, due to the fact that HTML is hand
 *          edited and you are unable to get a parser cache that caches the
 *          the output of HTML Purifier while keeping the original HTML lying
 *          around, you may want to run Tidy on the resulting output or use
 *          HTMLPurifier_DirectLex
 */

class HTMLPurifier_Lexer_DOMLex extends HTMLPurifier_Lexer
{
    
    private $factory;
    
    public function __construct() {
        // setup the factory
        parent::__construct();
        $this->factory = new HTMLPurifier_TokenFactory();
    }
    
    public function tokenizeHTML($html, $config, $context) {
        
        $html = $this->normalize($html, $config, $context);
        
        // attempt to armor stray angled brackets that cannot possibly
        // form tags and thus are probably being used as emoticons
        if ($config->get('Core', 'AggressivelyFixLt')) {
            $char = '[^a-z!\/]';
            $comment = "/<!--(.*?)(-->|\z)/is";
            $html = preg_replace_callback($comment, array($this, 'callbackArmorCommentEntities'), $html);
            $html = preg_replace("/<($char)/i", '&lt;\\1', $html);
            $html = preg_replace_callback($comment, array($this, 'callbackUndoCommentSubst'), $html); // fix comments
        }
        
        // preprocess html, essential for UTF-8
        $html = $this->wrapHTML($html, $config, $context);
        
        $doc = new DOMDocument();
        $doc->encoding = 'UTF-8'; // theoretically, the above has this covered
        
        set_error_handler(array($this, 'muteErrorHandler'));
        $doc->loadHTML($html);
        restore_error_handler();
        
        $tokens = array();
        $this->tokenizeDOM(
            $doc->getElementsByTagName('html')->item(0)-> // <html>
                  getElementsByTagName('body')->item(0)-> //   <body>
                  getElementsByTagName('div')->item(0)    //     <div>
            , $tokens);
        return $tokens;
    }
    
    /**
     * Recursive function that tokenizes a node, putting it into an accumulator.
     * 
     * @param $node     DOMNode to be tokenized.
     * @param $tokens   Array-list of already tokenized tokens.
     * @param $collect  Says whether or start and close are collected, set to
     *                  false at first recursion because it's the implicit DIV
     *                  tag you're dealing with.
     * @returns Tokens of node appended to previously passed tokens.
     */
    protected function tokenizeDOM($node, &$tokens, $collect = false) {
        
        // intercept non element nodes. WE MUST catch all of them,
        // but we're not getting the character reference nodes because
        // those should have been preprocessed
        if ($node->nodeType === XML_TEXT_NODE) {
            $tokens[] = $this->factory->createText($node->data);
            return;
        } elseif ($node->nodeType === XML_CDATA_SECTION_NODE) {
            // undo libxml's special treatment of <script> and <style> tags
            $last = end($tokens);
            $data = $node->data;
            // (note $node->tagname is already normalized)
            if ($last instanceof HTMLPurifier_Token_Start && ($last->name == 'script' || $last->name == 'style')) {
                $new_data = trim($data);
                if (substr($new_data, 0, 4) === '<!--') {
                    $data = substr($new_data, 4);
                    if (substr($data, -3) === '-->') {
                        $data = substr($data, 0, -3);
                    } else {
                        // Highly suspicious! Not sure what to do...
                    }
                }
            }
            $tokens[] = $this->factory->createText($this->parseData($data));
            return;
        } elseif ($node->nodeType === XML_COMMENT_NODE) {
            // this is code is only invoked for comments in script/style in versions
            // of libxml pre-2.6.28 (regular comments, of course, are still
            // handled regularly)
            $tokens[] = $this->factory->createComment($node->data);
            return;
        } elseif (
            // not-well tested: there may be other nodes we have to grab
            $node->nodeType !== XML_ELEMENT_NODE
        ) {
            return;
        }
        
        $attr = $node->hasAttributes() ?
            $this->transformAttrToAssoc($node->attributes) :
            array();
        
        // We still have to make sure that the element actually IS empty
        if (!$node->childNodes->length) {
            if ($collect) {
                $tokens[] = $this->factory->createEmpty($node->tagName, $attr);
            }
        } else {
            if ($collect) { // don't wrap on first iteration
                $tokens[] = $this->factory->createStart(
                    $tag_name = $node->tagName, // somehow, it get's dropped
                    $attr
                );
            }
            foreach ($node->childNodes as $node) {
                // remember, it's an accumulator. Otherwise, we'd have
                // to use array_merge
                $this->tokenizeDOM($node, $tokens, true);
            }
            if ($collect) {
                $tokens[] = $this->factory->createEnd($tag_name);
            }
        }
        
    }
    
    /**
     * Converts a DOMNamedNodeMap of DOMAttr objects into an assoc array.
     * 
     * @param $attribute_list DOMNamedNodeMap of DOMAttr objects.
     * @returns Associative array of attributes.
     */
    protected function transformAttrToAssoc($node_map) {
        // NamedNodeMap is documented very well, so we're using undocumented
        // features, namely, the fact that it implements Iterator and
        // has a ->length attribute
        if ($node_map->length === 0) return array();
        $array = array();
        foreach ($node_map as $attr) {
            $array[$attr->name] = $attr->value;
        }
        return $array;
    }
    
    /**
     * An error handler that mutes all errors
     */
    public function muteErrorHandler($errno, $errstr) {}
    
    /**
     * Callback function for undoing escaping of stray angled brackets
     * in comments
     */
    public function callbackUndoCommentSubst($matches) {
        return '<!--' . strtr($matches[1], array('&amp;'=>'&','&lt;'=>'<')) . $matches[2];
    }
    
    /**
     * Callback function that entity-izes ampersands in comments so that
     * callbackUndoCommentSubst doesn't clobber them
     */
    public function callbackArmorCommentEntities($matches) {
        return '<!--' . str_replace('&', '&amp;', $matches[1]) . $matches[2];
    }
    
    /**
     * Wraps an HTML fragment in the necessary HTML
     */
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
        // No protection if $html contains a stray </div>!
        $ret .= '</head><body><div>'.$html.'</div></body></html>';
        return $ret;
    }
    
}




/**
 * Our in-house implementation of a parser.
 * 
 * A pure PHP parser, DirectLex has absolutely no dependencies, making
 * it a reasonably good default for PHP4.  Written with efficiency in mind,
 * it can be four times faster than HTMLPurifier_Lexer_PEARSax3, although it
 * pales in comparison to HTMLPurifier_Lexer_DOMLex.
 * 
 * @todo Reread XML spec and document differences.
 */
class HTMLPurifier_Lexer_DirectLex extends HTMLPurifier_Lexer
{
    
    /**
     * Whitespace characters for str(c)spn.
     */
    protected $_whitespace = "\x20\x09\x0D\x0A";
    
    /**
     * Callback function for script CDATA fudge
     * @param $matches, in form of array(opening tag, contents, closing tag)
     */
    protected function scriptCallback($matches) {
        return $matches[1] . htmlspecialchars($matches[2], ENT_COMPAT, 'UTF-8') . $matches[3];
    }
    
    public function tokenizeHTML($html, $config, $context) {
        
        // special normalization for script tags without any armor
        // our "armor" heurstic is a < sign any number of whitespaces after
        // the first script tag
        if ($config->get('HTML', 'Trusted')) {
            $html = preg_replace_callback('#(<script[^>]*>)(\s*[^<].+?)(</script>)#si',
                array($this, 'scriptCallback'), $html);
        }
        
        $html = $this->normalize($html, $config, $context);
        
        $cursor = 0; // our location in the text
        $inside_tag = false; // whether or not we're parsing the inside of a tag
        $array = array(); // result array
        
        $maintain_line_numbers = $config->get('Core', 'MaintainLineNumbers');
        
        if ($maintain_line_numbers === null) {
            // automatically determine line numbering by checking
            // if error collection is on
            $maintain_line_numbers = $config->get('Core', 'CollectErrors');
        }
        
        if ($maintain_line_numbers) $current_line = 1;
        else $current_line = false;
        $context->register('CurrentLine', $current_line);
        $nl = "\n";
        // how often to manually recalculate. This will ALWAYS be right,
        // but it's pretty wasteful. Set to 0 to turn off
        $synchronize_interval = $config->get('Core', 'DirectLexLineNumberSyncInterval'); 
        
        $e = false;
        if ($config->get('Core', 'CollectErrors')) {
            $e =& $context->get('ErrorCollector');
        }
        
        // for testing synchronization
        $loops = 0;
        
        while(++$loops) {
            
            // recalculate lines
            if (
                $maintain_line_numbers && // line number tracking is on
                $synchronize_interval &&  // synchronization is on
                $cursor > 0 &&            // cursor is further than zero
                $loops % $synchronize_interval === 0 // time to synchronize!
            ) {
                $current_line = 1 + $this->substrCount($html, $nl, 0, $cursor);
            }
            
            $position_next_lt = strpos($html, '<', $cursor);
            $position_next_gt = strpos($html, '>', $cursor);
            
            // triggers on "<b>asdf</b>" but not "asdf <b></b>"
            // special case to set up context
            if ($position_next_lt === $cursor) {
                $inside_tag = true;
                $cursor++;
            }
            
            if (!$inside_tag && $position_next_lt !== false) {
                // We are not inside tag and there still is another tag to parse
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
                // We are not inside tag but there are no more tags
                // If we're already at the end, break
                if ($cursor === strlen($html)) break;
                // Create Text of rest of string
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
                // We are in tag and it is well formed
                // Grab the internals of the tag
                $strlen_segment = $position_next_gt - $cursor;
                
                if ($strlen_segment < 1) {
                    // there's nothing to process!
                    $token = new HTMLPurifier_Token_Text('<');
                    $cursor++;
                    continue;
                }
                
                $segment = substr($html, $cursor, $strlen_segment);
                
                if ($segment === false) {
                    // somehow, we attempted to access beyond the end of
                    // the string, defense-in-depth, reported by Nate Abele
                    break;
                }
                
                // Check if it's a comment
                if (
                    substr($segment, 0, 3) === '!--'
                ) {
                    // re-determine segment length, looking for -->
                    $position_comment_end = strpos($html, '-->', $cursor);
                    if ($position_comment_end === false) {
                        // uh oh, we have a comment that extends to
                        // infinity. Can't be helped: set comment
                        // end position to end of string
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
                
                // Check if it's an end tag
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
                
                // Check leading character is alnum, if not, we may
                // have accidently grabbed an emoticon. Translate into
                // text and go our merry way
                if (!ctype_alpha($segment[0])) {
                    // XML:  $segment[0] !== '_' && $segment[0] !== ':'
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
                
                // Check if it is explicitly self closing, if so, remove
                // trailing slash. Remember, we could have a tag like <br>, so
                // any later token processing scripts must convert improperly
                // classified EmptyTags from StartTags.
                $is_self_closing = (strrpos($segment,'/') === $strlen_segment-1);
                if ($is_self_closing) {
                    $strlen_segment--;
                    $segment = substr($segment, 0, $strlen_segment);
                }
                
                // Check if there are any attributes
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
                
                // Grab out all the data
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
                // inside tag, but there's no ending > sign
                if ($e) $e->send(E_WARNING, 'Lexer: Missing gt');
                $token = new
                    HTMLPurifier_Token_Text(
                        '<' .
                        $this->parseData(
                            substr($html, $cursor)
                        )
                    );
                if ($maintain_line_numbers) $token->line = $current_line;
                // no cursor scroll? Hmm...
                $array[] = $token;
                break;
            }
            break;
        }
        
        $context->destroy('CurrentLine');
        return $array;
    }
    
    /**
     * PHP 5.0.x compatible substr_count that implements offset and length
     */
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
    
    /**
     * Takes the inside of an HTML tag and makes an assoc array of attributes.
     * 
     * @param $string Inside of tag excluding name.
     * @returns Assoc array of attributes.
     */
    public function parseAttributeString($string, $config, $context) {
        $string = (string) $string; // quick typecast
        
        if ($string == '') return array(); // no attributes
        
        $e = false;
        if ($config->get('Core', 'CollectErrors')) {
            $e =& $context->get('ErrorCollector');
        }
        
        // let's see if we can abort as quickly as possible
        // one equal sign, no spaces => one attribute
        $num_equal = substr_count($string, '=');
        $has_space = strpos($string, ' ');
        if ($num_equal === 0 && !$has_space) {
            // bool attribute
            return array($string => $string);
        } elseif ($num_equal === 1 && !$has_space) {
            // only one attribute
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
                // well behaved
                $value = substr($quoted_value, 1, strlen($quoted_value) - 2);
            } else {
                // not well behaved
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
        
        // setup loop environment
        $array  = array(); // return assoc array of attributes
        $cursor = 0; // current position in string (moves forward)
        $size   = strlen($string); // size of the string (stays the same)
        
        // if we have unquoted attributes, the parser expects a terminating
        // space, so let's guarantee that there's always a terminating space.
        $string .= ' ';
        
        while(true) {
            
            if ($cursor >= $size) {
                break;
            }
            
            $cursor += ($value = strspn($string, $this->_whitespace, $cursor));
            // grab the key
            
            $key_begin = $cursor; //we're currently at the start of the key
            
            // scroll past all characters that are the key (not whitespace or =)
            $cursor += strcspn($string, $this->_whitespace . '=', $cursor);
            
            $key_end = $cursor; // now at the end of the key
            
            $key = substr($string, $key_begin, $key_end - $key_begin);
            
            if (!$key) {
                if ($e) $e->send(E_ERROR, 'Lexer: Missing attribute key');
                $cursor += strcspn($string, $this->_whitespace, $cursor + 1); // prevent infinite loop
                continue; // empty key
            }
            
            // scroll past all whitespace
            $cursor += strspn($string, $this->_whitespace, $cursor);
            
            if ($cursor >= $size) {
                $array[$key] = $key;
                break;
            }
            
            // if the next character is an equal sign, we've got a regular
            // pair, otherwise, it's a bool attribute
            $first_char = @$string[$cursor];
            
            if ($first_char == '=') {
                // key="value"
                
                $cursor++;
                $cursor += strspn($string, $this->_whitespace, $cursor);
                
                if ($cursor === false) {
                    $array[$key] = '';
                    break;
                }
                
                // we might be in front of a quote right now
                
                $char = @$string[$cursor];
                
                if ($char == '"' || $char == "'") {
                    // it's quoted, end bound is $char
                    $cursor++;
                    $value_begin = $cursor;
                    $cursor = strpos($string, $char, $cursor);
                    $value_end = $cursor;
                } else {
                    // it's not quoted, end bound is whitespace
                    $value_begin = $cursor;
                    $cursor += strcspn($string, $this->_whitespace, $cursor);
                    $value_end = $cursor;
                }
                
                // we reached a premature end
                if ($cursor === false) {
                    $cursor = $size;
                    $value_end = $cursor;
                }
                
                $value = substr($string, $value_begin, $value_end - $value_begin);
                if ($value === false) $value = '';
                $array[$key] = $this->parseData($value);
                $cursor++;
                
            } else {
                // boolattr
                if ($key !== '') {
                    $array[$key] = $key;
                } else {
                    // purely theoretical
                    if ($e) $e->send(E_ERROR, 'Lexer: Missing attribute key');
                }
                
            }
        }
        return $array;
    }
    
}




/**
 * Composite strategy that runs multiple strategies on tokens.
 */
abstract class HTMLPurifier_Strategy_Composite extends HTMLPurifier_Strategy
{
    
    /**
     * List of strategies to run tokens through.
     */
    protected $strategies = array();
    
    abstract public function __construct();
    
    public function execute($tokens, $config, $context) {
        foreach ($this->strategies as $strategy) {
            $tokens = $strategy->execute($tokens, $config, $context);
        }
        return $tokens;
    }
    
}




/**
 * Core strategy composed of the big four strategies.
 */
class HTMLPurifier_Strategy_Core extends HTMLPurifier_Strategy_Composite
{
    
    public function __construct() {
        $this->strategies[] = new HTMLPurifier_Strategy_RemoveForeignElements();
        $this->strategies[] = new HTMLPurifier_Strategy_MakeWellFormed();
        $this->strategies[] = new HTMLPurifier_Strategy_FixNesting();
        $this->strategies[] = new HTMLPurifier_Strategy_ValidateAttributes();
    }
    
}




/**
 * Takes a well formed list of tokens and fixes their nesting.
 * 
 * HTML elements dictate which elements are allowed to be their children,
 * for example, you can't have a p tag in a span tag.  Other elements have
 * much more rigorous definitions: tables, for instance, require a specific
 * order for their elements.  There are also constraints not expressible by
 * document type definitions, such as the chameleon nature of ins/del
 * tags and global child exclusions.
 * 
 * The first major objective of this strategy is to iterate through all the
 * nodes (not tokens) of the list of tokens and determine whether or not
 * their children conform to the element's definition.  If they do not, the
 * child definition may optionally supply an amended list of elements that
 * is valid or require that the entire node be deleted (and the previous
 * node rescanned).
 * 
 * The second objective is to ensure that explicitly excluded elements of
 * an element do not appear in its children.  Code that accomplishes this
 * task is pervasive through the strategy, though the two are distinct tasks
 * and could, theoretically, be seperated (although it's not recommended).
 * 
 * @note Whether or not unrecognized children are silently dropped or
 *       translated into text depends on the child definitions.
 * 
 * @todo Enable nodes to be bubbled out of the structure.
 */

class HTMLPurifier_Strategy_FixNesting extends HTMLPurifier_Strategy
{
    
    public function execute($tokens, $config, $context) {
        //####################################################################//
        // Pre-processing
        
        // get a copy of the HTML definition
        $definition = $config->getHTMLDefinition();
        
        // insert implicit "parent" node, will be removed at end.
        // DEFINITION CALL
        $parent_name = $definition->info_parent;
        array_unshift($tokens, new HTMLPurifier_Token_Start($parent_name));
        $tokens[] = new HTMLPurifier_Token_End($parent_name);
        
        // setup the context variable 'IsInline', for chameleon processing
        // is 'false' when we are not inline, 'true' when it must always
        // be inline, and an integer when it is inline for a certain
        // branch of the document tree
        $is_inline = $definition->info_parent_def->descendants_are_inline;
        $context->register('IsInline', $is_inline);
        
        // setup error collector
        $e =& $context->get('ErrorCollector', true);
        
        //####################################################################//
        // Loop initialization
        
        // stack that contains the indexes of all parents,
        // $stack[count($stack)-1] being the current parent
        $stack = array();
        
        // stack that contains all elements that are excluded
        // it is organized by parent elements, similar to $stack, 
        // but it is only populated when an element with exclusions is
        // processed, i.e. there won't be empty exclusions.
        $exclude_stack = array();
        
        // variable that contains the start token while we are processing
        // nodes. This enables error reporting to do its job
        $start_token = false;
        $context->register('CurrentToken', $start_token);
        
        //####################################################################//
        // Loop
        
        // iterate through all start nodes. Determining the start node
        // is complicated so it has been omitted from the loop construct
        for ($i = 0, $size = count($tokens) ; $i < $size; ) {
            
            //################################################################//
            // Gather information on children
            
            // child token accumulator
            $child_tokens = array();
            
            // scroll to the end of this node, report number, and collect
            // all children
            for ($j = $i, $depth = 0; ; $j++) {
                if ($tokens[$j] instanceof HTMLPurifier_Token_Start) {
                    $depth++;
                    // skip token assignment on first iteration, this is the
                    // token we currently are on
                    if ($depth == 1) continue;
                } elseif ($tokens[$j] instanceof HTMLPurifier_Token_End) {
                    $depth--;
                    // skip token assignment on last iteration, this is the
                    // end token of the token we're currently on
                    if ($depth == 0) break;
                }
                $child_tokens[] = $tokens[$j];
            }
            
            // $i is index of start token
            // $j is index of end token
            
            $start_token = $tokens[$i]; // to make token available via CurrentToken
            
            //################################################################//
            // Gather information on parent
            
            // calculate parent information
            if ($count = count($stack)) {
                $parent_index = $stack[$count-1];
                $parent_name  = $tokens[$parent_index]->name;
                if ($parent_index == 0) {
                    $parent_def   = $definition->info_parent_def;
                } else {
                    $parent_def   = $definition->info[$parent_name];
                }
            } else {
                // processing as if the parent were the "root" node
                // unknown info, it won't be used anyway, in the future,
                // we may want to enforce one element only (this is 
                // necessary for HTML Purifier to clean entire documents
                $parent_index = $parent_name = $parent_def = null;
            }
            
            // calculate context
            if ($is_inline === false) {
                // check if conditions make it inline
                if (!empty($parent_def) && $parent_def->descendants_are_inline) {
                    $is_inline = $count - 1;
                }
            } else {
                // check if we're out of inline
                if ($count === $is_inline) {
                    $is_inline = false;
                }
            }
            
            //################################################################//
            // Determine whether element is explicitly excluded SGML-style
            
            // determine whether or not element is excluded by checking all
            // parent exclusions. The array should not be very large, two
            // elements at most.
            $excluded = false;
            if (!empty($exclude_stack)) {
                foreach ($exclude_stack as $lookup) {
                    if (isset($lookup[$tokens[$i]->name])) {
                        $excluded = true;
                        // no need to continue processing
                        break;
                    }
                }
            }
            
            //################################################################//
            // Perform child validation
            
            if ($excluded) {
                // there is an exclusion, remove the entire node
                $result = false;
                $excludes = array(); // not used, but good to initialize anyway
            } else {
                // DEFINITION CALL
                if ($i === 0) {
                    // special processing for the first node
                    $def = $definition->info_parent_def;
                } else {
                    $def = $definition->info[$tokens[$i]->name];
                    
                }
                
                if (!empty($def->child)) {
                    // have DTD child def validate children
                    $result = $def->child->validateChildren(
                        $child_tokens, $config, $context);
                } else {
                    // weird, no child definition, get rid of everything
                    $result = false;
                }
                
                // determine whether or not this element has any exclusions
                $excludes = $def->excludes;
            }
            
            // $result is now a bool or array
            
            //################################################################//
            // Process result by interpreting $result
            
            if ($result === true || $child_tokens === $result) {
                // leave the node as is
                
                // register start token as a parental node start
                $stack[] = $i;
                
                // register exclusions if there are any
                if (!empty($excludes)) $exclude_stack[] = $excludes;
                
                // move cursor to next possible start node
                $i++;
                
            } elseif($result === false) {
                // remove entire node
                
                if ($e) {
                    if ($excluded) {
                        $e->send(E_ERROR, 'Strategy_FixNesting: Node excluded');
                    } else {
                        $e->send(E_ERROR, 'Strategy_FixNesting: Node removed');
                    }
                }
                
                // calculate length of inner tokens and current tokens
                $length = $j - $i + 1;
                
                // perform removal
                array_splice($tokens, $i, $length);
                
                // update size
                $size -= $length;
                
                // there is no start token to register,
                // current node is now the next possible start node
                // unless it turns out that we need to do a double-check
                
                // this is a rought heuristic that covers 100% of HTML's
                // cases and 99% of all other cases. A child definition
                // that would be tricked by this would be something like:
                // ( | a b c) where it's all or nothing. Fortunately,
                // our current implementation claims that that case would
                // not allow empty, even if it did
                if (!$parent_def->child->allow_empty) {
                    // we need to do a double-check
                    $i = $parent_index;
                    array_pop($stack);
                }
                
                // PROJECTED OPTIMIZATION: Process all children elements before
                // reprocessing parent node.
                
            } else {
                // replace node with $result
                
                // calculate length of inner tokens
                $length = $j - $i - 1;
                
                if ($e) {
                    if (empty($result) && $length) {
                        $e->send(E_ERROR, 'Strategy_FixNesting: Node contents removed');
                    } else {
                        $e->send(E_WARNING, 'Strategy_FixNesting: Node reorganized');
                    }
                }
                
                // perform replacement
                array_splice($tokens, $i + 1, $length, $result);
                
                // update size
                $size -= $length;
                $size += count($result);
                
                // register start token as a parental node start
                $stack[] = $i;
                
                // register exclusions if there are any
                if (!empty($excludes)) $exclude_stack[] = $excludes;
                
                // move cursor to next possible start node
                $i++;
                
            }
            
            //################################################################//
            // Scroll to next start node
            
            // We assume, at this point, that $i is the index of the token
            // that is the first possible new start point for a node.
            
            // Test if the token indeed is a start tag, if not, move forward
            // and test again.
            $size = count($tokens);
            while ($i < $size and !$tokens[$i] instanceof HTMLPurifier_Token_Start) {
                if ($tokens[$i] instanceof HTMLPurifier_Token_End) {
                    // pop a token index off the stack if we ended a node
                    array_pop($stack);
                    // pop an exclusion lookup off exclusion stack if
                    // we ended node and that node had exclusions
                    if ($i == 0 || $i == $size - 1) {
                        // use specialized var if it's the super-parent
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
        // Post-processing
        
        // remove implicit parent tokens at the beginning and end
        array_shift($tokens);
        array_pop($tokens);
        
        // remove context variables
        $context->destroy('IsInline');
        $context->destroy('CurrentToken');
        
        //####################################################################//
        // Return
        
        return $tokens;
        
    }
    
}





/**
 * Takes tokens makes them well-formed (balance end tags, etc.)
 */
class HTMLPurifier_Strategy_MakeWellFormed extends HTMLPurifier_Strategy
{
    
    /**
     * Locally shared variable references
     */
    protected $inputTokens, $inputIndex, $outputTokens, $currentNesting,
        $currentInjector, $injectors;
    
    public function execute($tokens, $config, $context) {
        
        $definition = $config->getHTMLDefinition();
        
        // local variables
        $result = array();
        $generator = new HTMLPurifier_Generator();
        $escape_invalid_tags = $config->get('Core', 'EscapeInvalidTags');
        $e = $context->get('ErrorCollector', true);
        
        // member variables
        $this->currentNesting = array();
        $this->inputIndex     = false;
        $this->inputTokens    =& $tokens;
        $this->outputTokens   =& $result;
        
        // context variables
        $context->register('CurrentNesting', $this->currentNesting);
        $context->register('InputIndex',     $this->inputIndex);
        $context->register('InputTokens',    $tokens);
        
        // -- begin INJECTOR --
        
        $this->injectors = array();
        
        $injectors = $config->getBatch('AutoFormat');
        $custom_injectors = $injectors['Custom'];
        unset($injectors['Custom']); // special case
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
        
        // array index of the injector that resulted in an array
        // substitution. This enables processTokens() to know which
        // injectors are affected by the added tokens and which are
        // not (namely, the ones after the current injector are not
        // affected)
        $this->currentInjector = false;
        
        // give the injectors references to the definition and context
        // variables for performance reasons
        foreach ($this->injectors as $i => $injector) {
            $error = $injector->prepare($config, $context);
            if (!$error) continue;
            array_splice($this->injectors, $i, 1); // rm the injector
            trigger_error("Cannot enable {$injector->name} injector because $error is not allowed", E_USER_WARNING);
        }
        
        // warning: most foreach loops follow the convention $i => $injector.
        // Don't define these as loop-wide variables, please!
        
        // -- end INJECTOR --
        
        $token = false;
        $context->register('CurrentToken', $token);
        
        // isset is in loop because $tokens size changes during loop exec
        for ($this->inputIndex = 0; isset($tokens[$this->inputIndex]); $this->inputIndex++) {
            
            // if all goes well, this token will be passed through unharmed
            $token = $tokens[$this->inputIndex];
            
            //printTokens($tokens, $this->inputIndex);
            
            foreach ($this->injectors as $injector) {
                if ($injector->skip > 0) $injector->skip--;
            }
            
            // quick-check: if it's not a tag, no need to process
            if (empty( $token->is_tag )) {
                if ($token instanceof HTMLPurifier_Token_Text) {
                     // injector handler code; duplicated for performance reasons
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
            
            // quick tag checks: anything that's *not* an end tag
            $ok = false;
            if ($info->type === 'empty' && $token instanceof HTMLPurifier_Token_Start) {
                // test if it claims to be a start tag but is empty
                $token = new HTMLPurifier_Token_Empty($token->name, $token->attr);
                $ok = true;
            } elseif ($info->type !== 'empty' && $token instanceof HTMLPurifier_Token_Empty) {
                // claims to be empty but really is a start tag
                $token = array(
                    new HTMLPurifier_Token_Start($token->name, $token->attr),
                    new HTMLPurifier_Token_End($token->name)
                );
                $ok = true;
            } elseif ($token instanceof HTMLPurifier_Token_Empty) {
                // real empty token
                $ok = true;
            } elseif ($token instanceof HTMLPurifier_Token_Start) {
                // start tag
                
                // ...unless they also have to close their parent
                if (!empty($this->currentNesting)) {
                    
                    $parent = array_pop($this->currentNesting);
                    $parent_info = $definition->info[$parent->name];
                    
                    // this can be replaced with a more general algorithm:
                    // if the token is not allowed by the parent, auto-close
                    // the parent
                    if (!isset($parent_info->child->elements[$token->name])) {
                        if ($e) $e->send(E_NOTICE, 'Strategy_MakeWellFormed: Tag auto closed', $parent);
                        // close the parent, then re-loop to reprocess token
                        $result[] = new HTMLPurifier_Token_End($parent->name);
                        $this->inputIndex--;
                        continue;
                    }
                    
                    $this->currentNesting[] = $parent; // undo the pop
                }
                $ok = true;
            }
            
            // injector handler code; duplicated for performance reasons
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
            
            // sanity check: we should be dealing with a closing tag
            if (!$token instanceof HTMLPurifier_Token_End) continue;
            
            // make sure that we have something open
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
            
            // first, check for the simplest case: everything closes neatly
            $current_parent = array_pop($this->currentNesting);
            if ($current_parent->name == $token->name) {
                $result[] = $token;
                foreach ($this->injectors as $i => $injector) {
                    $injector->notifyEnd($token);
                }
                continue;
            }
            
            // okay, so we're trying to close the wrong tag
            
            // undo the pop previous pop
            $this->currentNesting[] = $current_parent;
            
            // scroll back the entire nest, trying to find our tag.
            // (feature could be to specify how far you'd like to go)
            $size = count($this->currentNesting);
            // -2 because -1 is the last element, but we already checked that
            $skipped_tags = false;
            for ($i = $size - 2; $i >= 0; $i--) {
                if ($this->currentNesting[$i]->name == $token->name) {
                    // current nesting is modified
                    $skipped_tags = array_splice($this->currentNesting, $i);
                    break;
                }
            }
            
            // we still didn't find the tag, so remove
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
            
            // okay, we found it, close all the skipped tags
            // note that skipped tags contains the element we need closed
            for ($i = count($skipped_tags) - 1; $i >= 0; $i--) {
                // please don't redefine $i!
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
        
        // we're at the end now, fix all still unclosed tags (this is
        // duplicated from the end of the loop with some slight modifications)
        // not using $skipped_tags since it would invariably be all of them
        if (!empty($this->currentNesting)) {
            for ($i = count($this->currentNesting) - 1; $i >= 0; $i--) {
                // please don't redefine $i!
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
            // the original token was overloaded by an injector, time
            // to some fancy acrobatics
            
            // $this->inputIndex is decremented so that the entire set gets
            // re-processed
            array_splice($this->inputTokens, $this->inputIndex--, 1, $token);
            
            // adjust the injector skips based on the array substitution
            if ($this->injectors) {
                $offset = count($token);
                for ($i = 0; $i <= $this->currentInjector; $i++) {
                    // because of the skip back, we need to add one more
                    // for uninitialized injectors. I'm not exactly
                    // sure why this is the case, but I think it has to
                    // do with the fact that we're decrementing skips
                    // before re-checking text
                    if (!$this->injectors[$i]->skip) $this->injectors[$i]->skip++;
                    $this->injectors[$i]->skip += $offset;
                }
            }
        } elseif ($token) {
            // regular case
            $this->outputTokens[] = $token;
            if ($token instanceof HTMLPurifier_Token_Start) {
                $this->currentNesting[] = $token;
            } elseif ($token instanceof HTMLPurifier_Token_End) {
                array_pop($this->currentNesting); // not actually used
            }
        }
    }
    
}




/**
 * Removes all unrecognized tags from the list of tokens.
 * 
 * This strategy iterates through all the tokens and removes unrecognized
 * tokens. If a token is not recognized but a TagTransform is defined for
 * that element, the element will be transformed accordingly.
 */

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
        
        // remove script contents compatibility
        if ($remove_script_contents === true) {
            $hidden_elements['script'] = true;
        } elseif ($remove_script_contents === false && isset($hidden_elements['script'])) {
            unset($hidden_elements['script']);
        }
        
        $attr_validator = new HTMLPurifier_AttrValidator();
        
        // removes tokens until it reaches a closing tag with its value
        $remove_until = false;
        
        // converts comments into text tokens when this is equal to a tag name
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
                // DEFINITION CALL
                
                // before any processing, try to transform the element
                if (
                    isset($definition->info_tag_transform[$token->name])
                ) {
                    $original_name = $token->name;
                    // there is a transformation for this tag
                    // DEFINITION CALL
                    $token = $definition->
                                info_tag_transform[$token->name]->
                                    transform($token, $config, $context);
                    if ($e) $e->send(E_NOTICE, 'Strategy_RemoveForeignElements: Tag transform', $original_name);
                }
                
                if (isset($definition->info[$token->name])) {
                    
                    // mostly everything's good, but
                    // we need to make sure required attributes are in order
                    if (
                        ($token instanceof HTMLPurifier_Token_Start || $token instanceof HTMLPurifier_Token_Empty) &&
                        $definition->info[$token->name]->required_attr &&
                        ($token->name != 'img' || $remove_invalid_img) // ensure config option still works
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
                    // invalid tag, generate HTML representation and insert in
                    if ($e) $e->send(E_WARNING, 'Strategy_RemoveForeignElements: Foreign element to text');
                    $token = new HTMLPurifier_Token_Text(
                        $generator->generateFromToken($token, $config, $context)
                    );
                } else {
                    // check if we need to destroy all of the tag's children
                    // CAN BE GENERICIZED
                    if (isset($hidden_elements[$token->name])) {
                        if ($token instanceof HTMLPurifier_Token_Start) {
                            $remove_until = $token->name;
                        } elseif ($token instanceof HTMLPurifier_Token_Empty) {
                            // do nothing: we're still looking
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
                // textify comments in script tags when they are allowed
                if ($textify_comments !== false) {
                    $data = $token->data;
                    $token = new HTMLPurifier_Token_Text($data);
                } else {
                    // strip comments
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
            // we removed tokens until the end, throw error
            $e->send(E_ERROR, 'Strategy_RemoveForeignElements: Token removed to end', $remove_until);
        }
        
        $context->destroy('CurrentToken');
        
        return $result;
    }
    
}




/**
 * Validate all attributes in the tokens.
 */

class HTMLPurifier_Strategy_ValidateAttributes extends HTMLPurifier_Strategy
{
    
    public function execute($tokens, $config, $context) {
        
        // setup validator
        $validator = new HTMLPurifier_AttrValidator();
        
        $token = false;
        $context->register('CurrentToken', $token);
        
        foreach ($tokens as $key => $token) {
            
            // only process tokens that have attributes,
            //   namely start and empty tags
            if (!$token instanceof HTMLPurifier_Token_Start && !$token instanceof HTMLPurifier_Token_Empty) continue;
            
            // skip tokens that are armored
            if (!empty($token->armor['ValidateAttributes'])) continue;
            
            // note that we have no facilities here for removing tokens
            $validator->validateToken($token, $config, $context);
            
            $tokens[$key] = $token; // for PHP 4
        }
        $context->destroy('CurrentToken');
        
        return $tokens;
    }
    
}




/**
 * Transforms FONT tags to the proper form (SPAN with CSS styling)
 * 
 * This transformation takes the three proprietary attributes of FONT and
 * transforms them into their corresponding CSS attributes.  These are color,
 * face, and size.
 * 
 * @note Size is an interesting case because it doesn't map cleanly to CSS.
 *       Thanks to
 *       http://style.cleverchimp.com/font_size_intervals/altintervals.html
 *       for reasonable mappings.
 */
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
        
        // handle color transform
        if (isset($attr['color'])) {
            $prepend_style .= 'color:' . $attr['color'] . ';';
            unset($attr['color']);
        }
        
        // handle face transform
        if (isset($attr['face'])) {
            $prepend_style .= 'font-family:' . $attr['face'] . ';';
            unset($attr['face']);
        }
        
        // handle size transform
        if (isset($attr['size'])) {
            // normalize large numbers
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




/**
 * Simple transformation, just change tag name to something else,
 * and possibly add some styling. This will cover most of the deprecated
 * tag cases.
 */
class HTMLPurifier_TagTransform_Simple extends HTMLPurifier_TagTransform
{
    
    protected $style;
    
    /**
     * @param $transform_to Tag name to transform to.
     * @param $style CSS style to add to the tag
     */
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




/**
 * Concrete comment token class. Generally will be ignored.
 */
class HTMLPurifier_Token_Comment extends HTMLPurifier_Token
{
    public $data; /**< Character data within comment. */
    /**
     * Transparent constructor.
     * 
     * @param $data String comment data.
     */
    public function __construct($data, $line = null) {
        $this->data = $data;
        $this->line = $line;
    }
}




/**
 * Abstract class of a tag token (start, end or empty), and its behavior.
 */
class HTMLPurifier_Token_Tag extends HTMLPurifier_Token
{
    /**
     * Static bool marker that indicates the class is a tag.
     * 
     * This allows us to check objects with <tt>!empty($obj->is_tag)</tt>
     * without having to use a function call <tt>is_a()</tt>.
     */
    public $is_tag = true;
    
    /**
     * The lower-case name of the tag, like 'a', 'b' or 'blockquote'.
     * 
     * @note Strictly speaking, XML tags are case sensitive, so we shouldn't
     * be lower-casing them, but these tokens cater to HTML tags, which are
     * insensitive.
     */
    public $name;
    
    /**
     * Associative array of the tag's attributes.
     */
    public $attr = array();
    
    /**
     * Non-overloaded constructor, which lower-cases passed tag name.
     * 
     * @param $name String name.
     * @param $attr Associative array of attributes.
     */
    public function __construct($name, $attr = array(), $line = null) {
        $this->name = ctype_lower($name) ? $name : strtolower($name);
        foreach ($attr as $key => $value) {
            // normalization only necessary when key is not lowercase
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



/**
 * Concrete empty token class.
 */
class HTMLPurifier_Token_Empty extends HTMLPurifier_Token_Tag
{
    
}



/**
 * Concrete end token class.
 * 
 * @warning This class accepts attributes even though end tags cannot. This
 * is for optimization reasons, as under normal circumstances, the Lexers
 * do not pass attributes.
 */
class HTMLPurifier_Token_End extends HTMLPurifier_Token_Tag
{
    
}



/**
 * Concrete start token class.
 */
class HTMLPurifier_Token_Start extends HTMLPurifier_Token_Tag
{
    
}



/**
 * Concrete text token class.
 * 
 * Text tokens comprise of regular parsed character data (PCDATA) and raw
 * character data (from the CDATA sections). Internally, their
 * data is parsed with all entities expanded. Surprisingly, the text token
 * does have a "tag name" called #PCDATA, which is how the DTD represents it
 * in permissible child nodes.
 */
class HTMLPurifier_Token_Text extends HTMLPurifier_Token
{
    
    public $name = '#PCDATA'; /**< PCDATA tag name compatible with DTD. */
    public $data; /**< Parsed character data of text. */
    public $is_whitespace; /**< Bool indicating if node is whitespace. */
    
    /**
     * Constructor, accepts data and determines if it is whitespace.
     * 
     * @param $data String parsed character data.
     */
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



// does not support network paths

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
        $this->base->fragment = null; // fragment is invalid for base URI
        $stack = explode('/', $this->base->path);
        array_pop($stack); // discard last segment
        $stack = $this->_collapseStack($stack); // do pre-parsing
        $this->basePathStack = $stack;
    }
    public function filter(&$uri, $config, $context) {
        if (is_null($this->base)) return true; // abort early
        if (
            $uri->path === '' && is_null($uri->scheme) &&
            is_null($uri->host) && is_null($uri->query) && is_null($uri->fragment)
        ) {
            // reference to current document
            $uri = clone $this->base;
            return true;
        }
        if (!is_null($uri->scheme)) {
            // absolute URI already: don't change
            if (!is_null($uri->host)) return true;
            $scheme_obj = $uri->getSchemeObj($config, $context);
            if (!$scheme_obj) {
                // scheme not recognized
                return false;
            }
            if (!$scheme_obj->hierarchical) {
                // non-hierarchal URI with explicit scheme, don't change
                return true;
            }
            // special case: had a scheme but always is hierarchical and had no authority
        }
        if (!is_null($uri->host)) {
            // network path, don't bother
            return true;
        }
        if ($uri->path === '') {
            $uri->path = $this->base->path;
        }elseif ($uri->path[0] !== '/') {
            // relative path, needs more complicated processing
            $stack = explode('/', $uri->path);
            $new_stack = array_merge($this->basePathStack, $stack);
            $new_stack = $this->_collapseStack($new_stack);
            $uri->path = implode('/', $new_stack);
        }
        // re-combine
        $uri->scheme = $this->base->scheme;
        if (is_null($uri->userinfo)) $uri->userinfo = $this->base->userinfo;
        if (is_null($uri->host))     $uri->host     = $this->base->host;
        if (is_null($uri->port))     $uri->port     = $this->base->port;
        return true;
    }
    
    /**
     * Resolve dots and double-dots in a path stack
     */
    private function _collapseStack($stack) {
        $result = array();
        for ($i = 0; isset($stack[$i]); $i++) {
            $is_folder = false;
            // absorb an internally duplicated slash
            if ($stack[$i] == '' && $i && isset($stack[$i+1])) continue;
            if ($stack[$i] == '..') {
                if (!empty($result)) {
                    $segment = array_pop($result);
                    if ($segment === '' && empty($result)) {
                        // error case: attempted to back out too far:
                        // restore the leading slash
                        $result[] = '';
                    } elseif ($segment === '..') {
                        $result[] = '..'; // cannot remove .. with ..
                    }
                } else {
                    // relative path, preserve the double-dots
                    $result[] = '..';
                }
                $is_folder = true;
                continue;
            }
            if ($stack[$i] == '.') {
                // silently absorb
                $is_folder = true;
                continue;
            }
            $result[] = $stack[$i];
        }
        if ($is_folder) $result[] = '';
        return $result;
    }
}

/**
 * Validates ftp (File Transfer Protocol) URIs as defined by generic RFC 1738.
 */
class HTMLPurifier_URIScheme_ftp extends HTMLPurifier_URIScheme {
    
    public $default_port = 21;
    public $browsable = true; // usually
    public $hierarchical = true;
    
    public function validate(&$uri, $config, $context) {
        parent::validate($uri, $config, $context);
        $uri->query    = null;
        
        // typecode check
        $semicolon_pos = strrpos($uri->path, ';'); // reverse
        if ($semicolon_pos !== false) {
            $type = substr($uri->path, $semicolon_pos + 1); // no semicolon
            $uri->path = substr($uri->path, 0, $semicolon_pos);
            $type_ret = '';
            if (strpos($type, '=') !== false) {
                // figure out whether or not the declaration is correct
                list($key, $typecode) = explode('=', $type, 2);
                if ($key !== 'type') {
                    // invalid key, tack it back on encoded
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




/**
 * Validates http (HyperText Transfer Protocol) as defined by RFC 2616
 */
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




/**
 * Validates https (Secure HTTP) according to http scheme.
 */
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
        // we need to validate path against RFC 2368's addr-spec
        return true;
    }
    
}

/**
 * Validates news (Usenet) as defined by generic RFC 1738
 */
class HTMLPurifier_URIScheme_news extends HTMLPurifier_URIScheme {
    
    public $browsable = false;
    
    public function validate(&$uri, $config, $context) {
        parent::validate($uri, $config, $context);
        $uri->userinfo = null;
        $uri->host     = null;
        $uri->port     = null;
        $uri->query    = null;
        // typecode check needed on path
        return true;
    }
    
}

/**
 * Validates nntp (Network News Transfer Protocol) as defined by generic RFC 1738
 */
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

/**
 * Performs safe variable parsing based on types which can be used by
 * users. This may not be able to represent all possible data inputs,
 * however.
 */
class HTMLPurifier_VarParser_Flexible extends HTMLPurifier_VarParser
{
    
    protected function parseImplementation($var, $type, $allow_null) {
        if ($allow_null && $var === null) return null;
        switch ($type) {
            // Note: if code "breaks" from the switch, it triggers a generic
            // exception to be thrown. Specific errors can be specifically
            // done here.
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
                    // special case: technically, this is an array with
                    // a single empty string item, but having an empty
                    // array is more intuitive
                    if ($var == '') return array();
                    if (strpos($var, "\n") === false && strpos($var, "\r") === false) {
                        // simplistic string to array method that only works
                        // for simple lists of tag names or alphanumeric characters
                        $var = explode(',',$var);
                    } else {
                        $var = preg_split('/(,|[\n\r]+)/', $var);
                    }
                    // remove spaces
                    foreach ($var as $i => $j) $var[$i] = trim($j);
                    if ($type === 'hash') {
                        // key:value,key2:value2
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

/**
 * This variable parser uses PHP's internal code engine. Because it does
 * this, it can represent all inputs; however, it is dangerous and cannot
 * be used by users.
 */
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
