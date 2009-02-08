<?php

  /* index controller in index group */
  function index()
  {
    Aeolus::loadClass('AeoCache');
	if (!$data = AeoCache::fetch('date')) {
	  $m = Aeolus::m('WelcomeModel');
	  $data = $m->get_msg();

	  AeoCache::store($data, 'date');
	}

	$v = Aeolus::v('IndexView');
	$v->title = 'Home';
	$v->data = $data;

	$v->show();
  }
?>
