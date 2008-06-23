<?php
  /*
   * ACache class for caching
   */

  class ACache
  {
	private static $backend = null;

	private function set_backend()
	{
	  require A_PREFIX . 'etc/cache/backend.php';

	  $backend = 'A' . $backend . 'Cache';
	  A::ld("cache/$backend");

      self::$backend = new $backend();
	}

	public function fetch($id)
	{
	  if (null == self::$backend)
	    self::set_backend();

	  return self::$backend->fetch($id);
	}

	public function store($data, $id, $lifetime = 300)
	{
	  self::$backend->store($data, $id, $lifetime);
	}
  }
?>
