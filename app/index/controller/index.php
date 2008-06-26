<?php

  /* index controller in index group */
  function index()
  {
    A::ld('ACache');
	if (!$data = ACache::fetch('date')) {
	  $m = A::m('WelcomeModel');
	  $data = $m->get_msg();

	  ACache::store($data, 'date');
	}

	$v = A::v('IndexView', $data);

	$v->title = 'Home';
	$v->show();
  }
?>
