<?php
    /**
     * File backend for caching
     * 
     * @category cache
     * @author Qingcheng Zhang <kinch.zhang@gmail.com>
     * @copyright Copyright (c) 2008-2009, CityGeneration, Inc. (http://www.citygeneration.com)
     * 
     */

    class FileCache
	{
	    /**
		 * Caching Engine( We use PEAR::Cache_Lite )
		 *
		 */
		var $engine = null;

		/**
		 * Constructor
		 *
		 */
		function Cache_File()
		{
		}

		/**
		 * Set lifetime
		 *
		 */
		function set_lifetime($value)
		{
		}

		/**
		 * Save cache data
		 *
		 * @param mix $data Data to be cached
		 * @param string $id Unique ID to this cache data
		 * @param array $tags Array of tags 
		 * @param int|bool $lifetime Time during which the cache is fresh
		 */
		function save()
		{
		}

		/**
		 * Get cache data by an ID
		 *
		 */
		function fetch($id)
		{
		}

		/**
		 * Clean cache by ID
		 *
		 */
		function clean_by_id($id)
		{
		    $this->engine->remove($id);
		}

	}
?>
