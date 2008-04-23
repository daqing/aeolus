<?php
    /**
     * File backend for caching
     * 
     * @category cache
     * @author Qingcheng Zhang <kinch.zhang@gmail.com>
     * @copyright Copyright (c) 2008-2009, CityGeneration, Inc. (http://www.citygeneration.com)
     * 
     * Subversion Keywords
     *     
     * $LastChangedBy$
     * $LastChangedRevision$
     * $URL$
     * $Id$
     * 
     */

    class FileCache
	{
	    /**
		 * Caching Engine ( default is Zend_Cache_File)
		 *
		 */
		var $engine = null;

		/**
		 * Lifetime
		 *
		 */
		var $lifetime = null;

		/**
		 * Constructor
		 *
		 */
		function Cache_File($lifetime = 300)
		{
		    /* Default caching lifetime is 5 mins */
		    $this->set_lifetime($lifetime);
			
			require('Zend/Cache.php');			

			$frontend = array(
			    'lifetime' => $this->lifetime,
				'automatic_serialization' => true
			);

			$backend = array(
			    'cache_dir' => AEOLUS_ROOT.'/static/cache/',
				'hashed_directory_level' => 1
			);

			$this->engine = Zend_Cache::Factory('Core','File',$frontend,$backend);

		}

		/**
		 * Set lifetime
		 *
		 */
		function set_lifetime($value)
		{
		    $this->lifetime = $value;
		}

		/**
		 * Get lifetime 
		 *
		 */
		function get_lifetime()
		{
		    return $this->lifetime;
		}

		/**
		 * Save data to cache 
		 *
		 * @param mix $data Data to be cached
		 * @param string $id Unique ID to this cache data
		 * @param array $tags Array of tags 
		 * @param int|bool $lifetime Time during which the cache is fresh
		 */
		function set($data,$id,$tags=array(),$lifetime=false)
		{
		    $this->engine->save($data,$id,$tags,$lifetime);
		}

		/**
		 * Get cache data by an ID
		 *
		 */
		function get($id)
		{
		    return $this->engine->load($id);
		}

		/**
		 * Clean cache by ID
		 *
		 */
		function clean_by_id($id)
		{
		    $this->engine->remove($id);
		}

		/**
		 * Clean cache by tags
		 *
		 */
		function clean_by_tags($tags=array(),$with=true)
		{
		    if( $with ) # clean all cache with those tags
			{
		        $this->engine->clean(Zend_Cache::CLEANING_MODE_MATCHING_TAG,$tags);

			} else {

			    $this->engine->clean(Zend_Cache::CLEANING_MODE_NOT_MATCHING_TAG,$tags);
			}
		}

		/**
		 * Clean all cache
		 *
		 */
		function clean_all()
		{
		    $this->engine->clean(Zend_Cache::CLEANING_MODE_ALL);
		}

		/**
		 * Clean outdated cache
		 *
		 */
		function clean_outdated()
		{
		    $this->engine->clean(Zend_Cache::CLEANING_MODE_OLD);
		}

	}
?>
