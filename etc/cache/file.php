<?php
  /**
   * File backend cache configurations
   *
   * @author Kinch Zhang <kinch.zhang@gmail.com>
   */

   # Cache directory
   define('CACHE_DIR', A_PREFIX.'/tmp/cache');

   # Filename prefix
   define('CACHE_FILE_PREFIX', 'aeolus');

   # Hashed directory level
   define('HASHED_DIR_LEVEL', 0);

   # Hashed directory umask
   define('HASHED_DIR_UMASK', 0700);

   # Cache file umask
   define('CACHE_FILE_UMASK', 0600);   
?>
