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
	 */
	function __construct()
	{
	  # Load configurations
	  require A_PREFIX.'/etc/cache/file.php';

	  if(! is_writable( CACHE_DIR)){
		$error = 'Fatal: directory <i>\''.CACHE_DIR.'\'</i> not writable, please chmod to 777';
	    die($error);
	  }
	}

	/**
	 * Fetch data from cache
	 *
	 * @access public
	 * @param string $id Cache id
	 */
	public function fetch($id)
	{
	  if(!$metadata = $this->is_fresh($id)){ return false;}	  
	  
	  $file = $this->getPath($id) . $this->getFile($id);
	  $data = $this->readFrom($file);
	  
	  # check sum
	  $hash = crc32($data);
	  if( $hash != $metadata['hash']){
	  	# Problem detected by the read control
	  	$this->remove($id);
	  	return false;
	  }
	  
	  return unserialize($data);
	}
	
	/**
	 * Remove a cache file and its related meta file
	 * 
	 * @access private
	 * @param string $id Cache id	 
	 */
	private function remove($id)
	{
	  $metafile =  $this->getPath($id) . $this->getMetaFile($id);
	  $file = $this->getPath($id) . $this->getFile($id);
	  # Remove file
      if(! is_file($metafile) || ! is_file($file)){
	    return false;
	  }
	  if(!@unlink($file)){ return false;}
	  
	  # Remove meta file
	  if( isset($this->metadata[$id])){
	    unset($this->metadata[$id]);
	  }
	  if(!@unlink($metafile)){
	    return false;
	  }

	  return true;
	}
	
	/**
	 * Test if the cache is still fresh
	 * 
	 * @access private
	 * @param string $id Cache id
	 * @return mixed $result Return meta data array or boolean false
	 */
	private function is_fresh($id)
	{
	  $meta = $this->getMetadata($id);
	  if(! $meta){ return false;}
	  
	  if( time() <= $meta['expire']){ return $meta;}
	  
	  return false;
	}

	/**
	 * Store data into file
	 *
	 * @access public
	 * @param mixed $data Datas to cache
	 * @param string $id Cache id
	 * @param int $lifetime Time to live(in seconds)
	 */
	public function store($data, $id, $lifetime)
	{
	  $data = serialize($data);
	  clearstatcache();

	  $path = $this->getPath($id);	 
	  $file = $path . $this->getFile($id);

	  # Build cache directory structure
	  if( HASHED_DIR_LEVEL > 0 ){
	    if(! file_exists($path)){
		  @mkdir($path, HASHED_DIR_UMASK, true);
		  @chmod($path, HASHED_DIR_UMASK);
		}

		if(! is_writable($path)){ return false;}
	  }

	  # Add read control hash
	  $hash = crc32($data);

	  # Meta data
	  $metadata = array(
	    'hash' => $hash,
		'expire' => time() + $lifetime
	  );

	  # Save meta data
	  $res = $this->saveMetadata($id, $metadata);	  
	  if(! $res){ return false;}

      # Save data into file
	  $res = $this->writeTo($file, $data);	  
	  
	  return $res;
	}

	/**
	 * Delete cache
	 *
	 * @access private
	 * @param string $id Cache id
	 */
	private function delete($id)
	{
	}

	/**
	 * Set cache path
	 *
	 * @access private
	 * @param string $id Cache id
	 */
	private function getPath($id)
	{
	  if( null == $this->path){
	  	$this->path = CACHE_DIR;
	  		  
	    if( HASHED_DIR_LEVEL > 0){
	  	  $hash = hash('adler32', $id);
	  	  for($i=0; $i < HASHED_DIR_LEVEL; $i++){
	  	    $this->path .= DIRECTORY_SEPARATOR . CACHE_FILE_PREFIX . '-' . substr($hash, 0, $i+1);
	  	  }
	    }
	  }
	  
	  return $this->path;
	}
	
	/**
	 * Get cache file name
	 * 
	 * @access private 
	 * @param string $id Cache id
	 * @return string $filename Cache filename
	 */
	private function getFile($id)
	{
	  return DIRECTORY_SEPARATOR . CACHE_FILE_PREFIX . '-' . $id;
	}
	
	/**
	 * Get metadata file name
	 * 
	 * @access private
	 * @param string $id Cache id
	 * @return string $filename Metadata filename
	 */
	private function getMetaFile($id)
	{
	  return DIRECTORY_SEPARATOR . CACHE_FILE_PREFIX . '-meta-' . $id;
	}

	/**
	 * Save meta data
	 * 
	 * @access private
	 * @param string $id Cache id
	 * @param array $meta meta data array
	 */
	private function saveMetadata($id, $meta)
	{
	  $metafile =  $this->getPath($id) . $this->getMetaFile($id);
	  
	  $res = $this->writeTo($metafile, serialize($meta));
	  if(! $res){ return false;}
	  
	  $this->metadata[$id] = $meta;
	  return true;
	}
	
	/**
	 * Get meta data
	 * 
	 * @access private
	 * @param string $id Cache id
	 */
	private function getMetadata($id)
	{
	  if(isset($this->metadata[$id])){
	  	return $this->metadata[$id];
	  }else{
	  	$meta = $this->loadMetadata($id);
	  	if(! $meta){ return false;}
	    
	  	return $meta;
	  }
	}
	
	/**
	 * Load meta data from file
	 * 
	 * @access private
	 * @param string $id Cache id
	 * @return mixed $data Return meta data array or boolean false
	 */
	private function loadMetadata($id)
	{
	  $metafile =  $this->getPath($id) . $this->getMetaFile($id);
	  
	  $data = $this->readFrom($metafile);
	  if(! $data){
	  	return false;
	  }
	  	  
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
	private function writeTo($file, $string)
	{
	  $result = false;
	   
	  $f = @fopen($file, 'wb');
	  if($f){
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
	private function readFrom($file)
	{
	  if(! is_file($file)){ return false;}
	  $mqr = get_magic_quotes_runtime();
	  set_magic_quotes_runtime(0);
	  $result = false;
	  	  
	  $f = @fopen($file, 'rb');
	  if($f){
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
