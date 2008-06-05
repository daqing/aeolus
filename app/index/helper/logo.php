<?php

  # 'logo' helper in 'index' group
  function logo()
  {
    echo '<a href="'.APP_PREFIX.'/"><img width="165" height="34" ';
	echo 'src="'.APP_SUB.'/pub/image/logo.gif" ';
	echo 'alt="Logo" /></a>';
  }
?>
