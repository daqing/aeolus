<?php
    /**
	 * Application controller
	 *
	 * @author Qingcheng Zhang <kinch.zhang@gmail.com>
	 * @copyright Copyright (c) 2008-2009, CityGeneration, Inc. (http://citygeneration.com)
	 *
	 */
	
	/**
	 * Load a file from disk
	 *
	 * This function is the same as LifeType's lt_include function
	 *
	 */
	function aeolus_load($path)
	{	    
        if(!isset($GLOBALS['loaded'][$path]))
        {
            require($path);
            $GLOBALS['loaded'][$path] = TRUE;
        }
	}

	/**
	 * Show errors and exit
	 *
	 */
	function aeolus_error( $type = null )
	{
	  # default type is a '404 NOT FOUND' error
	  aeolus_render( AEOLUS_ROOT.'/static/res_not_found.html' );
      die();
	}

    /**
	 * Route a given URL according to the routing table
	 *
	 * @author Qingcheng Zhang <kinch.zhang@gmail.com>
	 * @access public
	 * @param string $url
	 * @return array $result
	 *
	 */
	
	function aeolus_route($url)
	{
	    /* Load routing table */
		$table = load_routing_rule();
		
		# Process requesting URL
		$table['subdir'] = rtrim($table['subdir'],'/\\');
		$length = strlen($table['subdir']);
		if(! $_SESSION['aeolus']['can_rewrite']){$length += strlen('index.php/');};
		$url = substr($url,$length);		    
		
		if(!defined('AEOLUS_SUBDIR')){		
		    define('AEOLUS_SUBDIR',$table['subdir']);			
		}		
		if(!defined('AEOLUS_OUTPUT')){
			if( $_SESSION['aeolus']['can_rewrite']){
			  define('AEOLUS_OUTPUT',$table['subdir']);
			}else{
			  
			  define('AEOLUS_OUTPUT',$table['subdir'].'/index.php');
			}
		}

		/* Manipulate URL, assuming that this application is installed on WEB_ROOT */
	    $url = strtolower( trim($url,'/\\') );

		$source = explode('/',$url);
		$number = count($source);

        /* The default case is an error result */
		$result['group'] = 'index';
		$result['controller'] = 'error';
		$result['action'] = 'index';
		$result['param'] = null;

		if( $number > 1 )
		{
		    if(!( is_string($source[0]) && is_string($source[1]) ) )
			{
			    /* The request is not valid */
			    return $result;
			}
		}

		switch ($number)
		{
		    case '1':
			    if( '' == $source[0] || '/' == $source[0] )
				{
				    /* Request for home */
        			$result['controller'] = 'index';
         		
				} elseif(array_key_exists($source[0],$table)){
					    /* $source[0] is a group name */
						$result['group'] = $source[0];
						$result['controller'] = 'index';
				}			
				
				return $result;
				break;

			case '2' :
                /**
				 * In this case,Only controllers in 'index' group are permitted.
				 *
				 */
				if(in_array($source[0],$table['index']))
				{
				    $result['controller'] = $source[0];
					$result['action'] = $source[1];
				}
                
				return $result;
				break;
            
			case '3' :
			    /*
				 * In this case, only two kind of request are permitted: 
			     *     a 'controller/action/param'-kind request
				 *     a 'group/controller/action'-kind request
			     *
			     */
			   if( array_key_exists($source[0],$table) )
			   {
			       /* The second kind */
				   $result['group'] = $source[0];
				   $result['controller'] = $source[1];
				   $result['action'] = $source[2];

			   }elseif( in_array($source[0],$table['index']) )
			   {
			       /* The first kind */
				   $result['controller'] = $source[0];
				   $result['action'] = $source[1];
				   $result['param'] = $source[2];
			   }

			   return $result;
			   break;
		    
			case '4' :
			   /*
			    * In this case, only one kind of request is permitted:
				*     a 'group/controller/action/param'-kind request
				*
				*/
			   if( array_key_exists($source[0],$table) && in_array($source[1],$table[$source[0]]))
			   {
			       $result['group'] = $source[0];
				   $result['controller'] = $source[1];
				   $result['action'] = $source[2];
				   $result['param'] = $source[3];
			   }

			   return $result;
			   break;
		    
			default :
			    /* 
				 * This default case will be matched if the number of request segment is greater than 4,
				 * and in this case , only two kinds of request are permitted:
				 *    (1)  'group/controller/action/param1/param2/param3/...'
				 *    (2)  'controller/action/param1/param2/param3/param4/...'
				 *
				 */
				if(is_string($source[2]) && array_key_exists($source[0],$table) && in_array($source[1],$table[$source[0]]) )
				{
				    /* The first kind */
					$result['group'] = $source[0];
					$result['controller'] = $source[1];
					$result['action'] = $source[2];			

					$length = count($source);

					for($i=3;$i<$length ;$i++)
					{
					    $result['param'][] = $source[$i];
					}

				} elseif( in_array($source[0],$table['index']) && is_string($source[1]) )
				{
				    $result['controller'] = $source[0];
					$result['action'] = $source[1];
					$result['param'] = array();

					$length = count($source);

					for($i=2;$i< $length ;$i++)
					{
					    $result['param'][] = $source[$i];
					}
				}

				return $result;
				break;

		}
		/* End of switch */
        
		/* Make sure that we always return the result */
		return $result;
    }

    /**
	 * Dispatch a given URL 
	 * 
	 * @author Qingcheng Zhang <kinch.zhang@gmail.com>
	 * @access public
	 * @param array $result
	 * @return void
	 *
	 */

	function aeolus_dispatch($result)
	{
				
		$group = $result['group'];
		$controller = $result['controller'];
		$action = $result['action'];
		$param = $result['param'];
		$result = null;
		        
		if( $controller_path = app_controller_exists($group,$controller) )
		{
		    # Load the request controller 
		    aeolus_load($controller_path);

			if( function_exists($action) )
			{
				if( 'ajax' != $group )
				{
			        # Load the view class 
				    aeolus_load(AEOLUS_ROOT.'/kernel/core/View.php');
				}
                
				aeolus_load(AEOLUS_ROOT.'/kernel/core/Model.php');
				
				# Execute the action 
			    $action($param);

			} else {
			    /* TODO: The requested action does not exist, redirect to the home page */
				die("The request -- $group::$controller::$action -- in which the action '$action' does not exist");
			}

		}else{
			/* TODO: Fatal error:controller doesn't exist , redirected to the home page */
			die("The request -- $group::$controller::$action -- in which the controller '$controller.php' in '$group' module does not exist");
		}
	}

	/**
	 * Check if a given application controller exists
	 *
	 * @return string $controller_path
	 */
	function app_controller_exists($group,$controller)
	{
      $controller_path = AEOLUS_ROOT."/app/$group/controller/$controller.php";
      
      if( file_exists($controller_path) )
      {
        return $controller_path;
      }
      
      return '';
	}
	
	/**
	 * Get an instance of a given application model
	 *
	 * @param string $group group name(case *sensitive*)
	 * @param string $model model name(case *sensitive*)
	 * @return object $model
	 */
	function app_model_factory($group,$model)
	{
	  $model_path = AEOLUS_ROOT."/app/$group/model/$model.php";
	  
	  if( file_exists($model_path) )
	  {
	    aeolus_load($model_path);
	    $object = new $model();
	    
	    return $object;
	  }else{
	      die( "Model file $model_path does not exist" );
	  }
	  
	}
	
	/**
	 * Get an instance of a given application view
	 *
	 * @param string $group group name(case *sensitive*)
	 * @param string $model view name(case *sensitive*)
	 * @return object $model
	 */
	function app_view_factory($group,$view,$data=null)
	{
	    $view_path = AEOLUS_ROOT."/app/$group/view/$view.php";
	    
	    if( file_exists($view_path) )
	    {
	      aeolus_load($view_path);
	      $object = new $view($data);
	      
	      return $object;
	    }else{
	      die( "View file $view_path does not exist" );
	    }
	    
	}

	/**
	 * Load a helper function
	 *
	 * @param $module the name of the module
	 * @param $helper the name of the helper
	 * @return void
	 *
	 */
	function app_helper_load($module,$helper)
	{
	  $path = AEOLUS_ROOT."/app/$module/helper/$helper.php";

	  if( file_exists($path) ){
	    aeolus_load($path);
		if( !function_exists($helper) ){
		  die("Helper function : $helper in $path doesn't exist");
		}
	  }else{
	    die("File : $path doesn't exist");
	  }
	}


	/**
	 * Load the routing rule array
	 *
	 * @access private
	 * @param void
	 * @return $rule|null array|null
	 */	
	function load_routing_rule()
	{
		if( file_exists(AEOLUS_ROOT.'/conf/core/rule.php') ){
			require AEOLUS_ROOT.'/conf/core/rule.php';
			return $rule;
		}

		return null;
	}

?>
