<?php

  # 'doc' controller in 'index' group
  function doc()
  {
    $view = A::mkview('DocView');

	$view->title = 'Doc';
	$view->show();
  }
?>
