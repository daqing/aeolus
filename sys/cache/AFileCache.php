<?php
  /**
   * AFileCache class for caching data into files
   *
   * @author Kinch Zhang <kinch.zhang@gmail.com>
   */
  
  class AFileCache
  {
    /**
	 * Meta data
	 *
	 * @access private
	 */
	private $metadata = array();
	
	/**
	 * Cache file path
	 * 
	 * @access private
	 */
	private $path = null;

	/**
	 * Constructor
	 *
	 */
	function __construct()
	{
	  # Load configurations
	  require A_PREFIX.'etc/cache/file.php';

	  if (! is_writable(CACHE_DIR)) {
		$err = 'Fatal: directory <i>\'' . CACHE_DIR;
		$err .= '\'</i> not writable, please chmod to 777';
	    exit($err);
	  }
	}

	/**
	 * Fetch data from cache system
	 *
	 * @access public
	 * @param string $id Cache ID
	 */
	public function fetch($id)
	{
	  if (! $metadata = $this->is_fresh($id)) 
	    return false;
	  
	  $file = $this->get_path($id).$this->get_file($id);
	  $data = $this->read_from($file);
	  
	  # check sum
	  $hash = crc32($data);
	  if ($hash != $metadata['hash']) {
	  	# Problem detected by the read control
	  	$this->remove($id);
	  	return false;
	  }
	  
	  return unserialize($data);
	}
	
	/**
	 * Remove cache data and metadata
	 * 
	 * @access private
	 * @param string $id Cache ID
	 */
	private function remove($id)
	{
	  $metafile =  $this->get_path($id) . $this->get_meta_file($id);
	  $file = $this->get_path($id) . $this->get_file($id);

	  # Remove cache data
      if (! is_file($metafile) || ! is_file($file))
	    return false;

	  if (! @ unlink($file))
	    return false;
	  
	  # Remove metadata
	  if (isset($this->metadata[$id]))
	    unset($this->metadata[$id]);

	  if (! @ unlink($metafile))
	    return false;

	  return true;
	}
	
	/**
	 * Test if the cache is still fresh
	 * 
	 * @access private
	 * @param string $id Cache ID
	 * @return mixed $result Return metadata array or boolean false
	 */
	private function is_fresh($id)
	{
	  $meta = $this->get_metadata($id);
	  if (! $meta)
	    return false;
	  
	  if (time() <= $meta['expire'])
	    return $meta;
	  
	  return false;
	}

	/**
	 * Store data into files
	 *
	 * @access public
	 * @param mixed $data Data to cache
	 * @param string $id Cache Id
	 * @param int $lifetime Time to live(in seconds)
	 */
	public function store($data, $id, $lifetime)
	{
	  $data = serialize($data);
	  clearstatcache();

	  $path = $this->get_path($id);	 
	  $file = $path . $this->get_file($id);

	  # Build cache directory structure
	  if (HASHED_DIR_LEVEL > 0) {
	    if (! file_exists($path)) {
		  @mkdir($path, HASHED_DIR_UMASK, true);
		  @chmod($path, HASHED_DIR_UMASK);
		}

		if (! is_writable($path))
		  return false;
	  }

	  # Add read control hash
	  $hash = crc32($data);

	  # Metadata
	  $metadata = array(
	    'hash' => $hash,
		'expire' => time() + $lifetime
	  );

	  # Save metadata
	  $res = $this->save_metadata($id, $metadata);	  
	  if (! $res)
	    return false;

      # Save data into file
	  $res = $this->write_to($file, $data);	  
	  
	  return $res;
	}

	/**
	 * Set cache path
	 *
	 * @access private
	 * @param string $id Cache ID
	 */
	private function get_path($id)
	{
	  if (null == $this->path) {
	  	$this->path = CACHE_DIR;
	    if (HASHED_DIR_LEVEL > 0) {
	  	  $hash = hash('adler32', $id);
	  	  for ($i=0; $i < HASHED_DIR_LEVEL; $i++)
		    $this->path .= DIRECTORY_SEPARATOR . CACHE_FILE_PREFIX . '-' . substr($hash, 0, $i+1);
	    }
	  }
	  
	  return $this->path;
	}
	
	/**
	 * Get cache file name
	 * 
	 * @access private 
	 * @param string $id Cache ID
	 * @return string $filename Cache filename
	 */
	private function get_file($id)
	{
	  return DIRECTORY_SEPARATOR . CACHE_FILE_PREFIX . '-' . $id;
	}
	
	/**
	 * Get metadata file name
	 * 
	 * @access private
	 * @param string $id Cache ID
	 * @return string $filename Metadata filename
	 */
	private function get_meta_file($id)
	{
	  return DIRECTORY_SEPARATOR . CACHE_FILE_PREFIX . '-meta-' . $id;
	}

	/**
	 * Save metadata
	 * 
	 * @access private
	 * @param string $id Cache ID
	 * @param array $meta metadata array
	 */
	private function save_metadata($id, $meta)
	{
	  $metafile =  $this->get_path($id) . $this->get_meta_file($id);
	  
	  $res = $this->write_to($metafile, serialize($meta));
	  if (! $res)
	    return false;
	  
	  $this->metadata[$id] = $meta;

	  return true;
	}
	
	/**
	 * Get metadata
	 * 
	 * @access private
	 * @param string $id Cache ID
	 */
	private function get_metadata($id)
	{
	  if (isset($this->metadata[$id])) {
	  	return $this->metadata[$id];
	  }
	  else{
	  	$meta = $this->load_metadata($id);
	  	if (! $meta)
		  return false;
	    
	  	return $meta;
	  }
	}
	
	/**
	 * Load metadata from file
	 * 
	 * @access private
	 * @param string $id Cache ID
	 * @return mixed $data Return meta data array or boolean false
	 */
	private function load_metadata($id)
	{
	  $metafile =  $this->get_path($id) . $this->get_meta_file($id);
	  $data = $this->read_from($metafile);
	  if (! $data)
	  	return false;

	  return unserialize($data);
	}
	/**
	 * Write data to a file
	 * 
	 * @access private
	 * @param string $file File name 
	 * @param string $string String to write
	 * @return boolean $result True if file was successfully created
	 */
	private function write_to($file, $string)
	{
	  $result = false;
	  $f = @fopen($file, 'wb');

	  if ($f) {
	  	# Lock file
	  	@flock($f, LOCK_EX);
	  	$result = @fwrite($f, $string);

	  	# Unlock and close file
	  	@flock($f, LOCK_UN);
	  	@fclose($f);	  
	  }

	  @chmod($file, CACHE_FILE_UMASK);
	  
	  return $result;
	}
	
	/**
	 * Read contents from a file
	 * 
	 * @access private
	 * @param string $file Path to the file 
	 */
	private function read_from($file)
	{
	  if (! is_file($file))
	    return false;

	  $mqr = get_magic_quotes_runtime();
	  set_magic_quotes_runtime(0);
	  $result = false;

	  $f = @fopen($file, 'rb');
	  if ($f) {
	    # Lock file
	    @flock($f, LOCK_SH);
	    $size = @filesize($file);

	    $result =  $size ? fread($f, $size) : '';

	    # Unlock and close file
	    @flock($f, LOCK_UN);
	    @fclose($f);
	  }

	  set_magic_quotes_runtime($mqr);
	  
	  return $result;
	}
  }
?>
