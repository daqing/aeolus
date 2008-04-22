<?php
  
  # Redirect to AEOLUS_ROOT.'/index.php/install/' for actural installation
  require '../app/etc/rule.php';
  
  $url = rtrim($rule['base'],'/\\');
  $url .= '/index.php/install/';

  header("Location: $url");

  die();
?>
