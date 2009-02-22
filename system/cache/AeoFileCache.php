<?php
  /*
   * File-based caching
   */
  
  class AeoFileCache
  {
	private $metadata = array();
	
	private $path = null;

	function __construct()
	{
      // Load configuration
	  require A_PREFIX .'config/system/cache/file.php';

	  if (!is_writable(CACHE_DIR)) {
		$err = 'Error: '. CACHE_DIR .' not writable, please chmod to 777';
	    exit($err);
	  }
	}

	public function fetch($id)
	{
	  if (!$metadata = $this->is_fresh($id)) 
	    return false;
	  
	  $file = $this->get_path($id) . $this->get_file($id);
	  $data = $this->read_from($file);
	  /* check sum */
	  $hash = crc32($data);
	  if ($hash != $metadata['hash']) {
	  	$this->remove($id);
	  	return false;
	  }
	  return unserialize($data);
	}
	
	private function remove($id)
	{
	  $metafile =  $this->get_path($id) . $this->get_meta_file($id);
	  $file = $this->get_path($id) . $this->get_file($id);
      if (!is_file($metafile) || !is_file($file))
	    return false;

	  if (!@unlink($file))
	    return false;
	  
	  if (isset($this->metadata[$id]))
	    unset($this->metadata[$id]);

	  if (!@unlink($metafile))
	    return false;

	  return true;
	}
	
	private function is_fresh($id)
	{
	  $meta = $this->get_metadata($id);
	  if (!$meta)
	    return false;

	  if (time() <= $meta['expire'])
	    return $meta;
	  
	  return false;
	}

	public function store($data, $id, $lifetime)
	{
	  $data = serialize($data);
	  clearstatcache();

	  $path = $this->get_path($id);	 
	  $file = $path . $this->get_file($id);

	  /* Build cache directory structure */
	  if (HASHED_DIR_LEVEL > 0) {
	    if (!file_exists($path)) {
		  @mkdir($path, HASHED_DIR_UMASK, true);
		  @chmod($path, HASHED_DIR_UMASK);
		}

		if (!is_writable($path))
		  return false;
	  }

	  $hash = crc32($data);
	  $metadata = array(
	    'hash' => $hash,
		'expire' => time() + $lifetime
	  );

	  $res = $this->save_metadata($id, $metadata);	  
	  if (!$res)
	    return false;

	  $res = $this->write_to($file, $data);	  
	  return $res;
	}

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
	
	private function get_file($id)
	{
	  return DIRECTORY_SEPARATOR . CACHE_FILE_PREFIX . '-' . $id;
	}
	
	private function get_meta_file($id)
	{
	  return DIRECTORY_SEPARATOR . CACHE_FILE_PREFIX . '-meta-' . $id;
	}

	private function save_metadata($id, $meta)
	{
	  $metafile =  $this->get_path($id) . $this->get_meta_file($id);
	  $res = $this->write_to($metafile, serialize($meta));
	  if (!$res)
	    return false;
	  
	  $this->metadata[$id] = $meta;
	  return true;
	}
	
	private function get_metadata($id)
	{
	  if (isset($this->metadata[$id]))
	  	return $this->metadata[$id];
	  else {
	  	$meta = $this->load_metadata($id);
	  	if (!$meta)
		  return false;
	    
	  	return $meta;
	  }
	}
	
	private function load_metadata($id)
	{
	  $metafile =  $this->get_path($id) . $this->get_meta_file($id);
	  $data = $this->read_from($metafile);
	  if (!$data)
	  	return false;

	  return unserialize($data);
	}

	private function write_to($file, $string)
	{
	  $result = false;
	  $f = @fopen($file, 'wb');
	  if ($f) {
	  	@flock($f, LOCK_EX);

	  	$result = @fwrite($f, $string);

	  	@flock($f, LOCK_UN);
	  	@fclose($f);	  
	  }
	  @chmod($file, CACHE_FILE_UMASK);

	  return $result;
	}
	
	private function read_from($file)
	{
	  if (!is_file($file))
	    return false;

	  $mqr = get_magic_quotes_runtime();
	  set_magic_quotes_runtime(0);
	  $result = false;

	  $f = @fopen($file, 'rb');
	  if ($f) {
	    @flock($f, LOCK_SH);

	    $size = @filesize($file);
	    $result =  $size ? fread($f, $size) : '';

	    @flock($f, LOCK_UN);
	    @fclose($f);
	  }
	  set_magic_quotes_runtime($mqr);
	  
	  return $result;
	}
  }
?>
