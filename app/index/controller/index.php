<?php

  # 'index' controller in 'index' group
  function index()
  {
    A::ld('ACache');
	if(! $data = ACache::fetch('index') ){
	  # Cache miss
	  $model = A::m('IndexModel');
	  $data = $model->getData();

	  ACache::store($data, 'index', 60);
	  $model = null;
	}

    $view = A::v('IndexView', $data);

	$view->title = 'Index';
	$view->show();
  }
?>
