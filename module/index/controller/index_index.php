<?php

  /* index controller in index group */
  function index_index()
  {
    Aeolus::loadClass('AeoCache');
	if (!$data = AeoCache::fetch('date')) {
	  $m = Aeolus::newModel('WelcomeModel');
	  $data = $m->get_msg();

	  AeoCache::store($data, 'date');
	}

	$v = Aeolus::newView('IndexView');
	$v->title = 'Home';
	$v->data = $data;

	$v->show();
  }
?>
