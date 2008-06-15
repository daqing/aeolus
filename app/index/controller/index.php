<?php

  # 'index' controller in 'index' group
  function index()
  {
    A::ld('ACache');
	if(! $data = ACache::fetch('index') ){
	  # Cache miss
	  $model = A::m('IndexModel');
	  $data = $model->get_data();

	  ACache::store($data, 'index', 60);
	  $model = null;
	}

    $view = A::v('IndexView', $data);

	$view->title = 'Index';
	$view->show();
  }
?>
