<?php

  # 'index' controller in 'index' group
  function index()
  {
    # Get data
    $model = A::m('IndexModel');
	$data = $model->get_data();
	$model = null;

    # Get view
    $view = A::v('IndexView', $data);

	$view->title = 'Index';
	$view->show();
  }
?>
