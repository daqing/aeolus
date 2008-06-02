<?php

  # 'index' controller in 'index' group
  function index()
  {
    A::ld('ACache.php');
	if(! $data = ACache::fetch('index') ){
	  # Cache miss
	  $model = A::mkmodel('IndexModel');
	  $data = $model->getData();

	  ACache::store($data, 'index', 60);
	  $model = null;
	}

    $view = A::mkview('IndexView', $data);

	$view->title = 'Index';
	$view->show();
  }
?>
