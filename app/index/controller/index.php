<?php

  /* index controller in index group */
  function index()
  {
    $m = A::m('WelcomeModel');
	$data = $m->get_msg();

	$v = A::v('IndexView', $data);

	$v->title = 'Home';
	$v->show();
  }
?>
