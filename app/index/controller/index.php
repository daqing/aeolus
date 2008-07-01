<?php

  /* index controller in index group */
  function index()
  {
    Aeo::ld('AeoCache');
	if (!$data = AeoCache::fetch('date')) {
	  $m = Aeo::m('WelcomeModel');
	  $data = $m->get_msg();

	  AeoCache::store($data, 'date');
	}

	$v = Aeo::v('IndexView');
	$v->title = 'Home';
	$v->data = $data;

	$v->show();
  }
?>
