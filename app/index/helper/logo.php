<?php

  # 'logo' helper in 'index' group
  function logo()
  {
    echo '<a href="'.URL_BASE.'"><img width="165" height="34" ';
	echo 'src="'.SUB_DIR.'pub/image/logo.gif" ';
	echo 'alt="Logo" /></a>';
  }
?>
