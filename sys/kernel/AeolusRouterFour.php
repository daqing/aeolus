<?php
  # Aeolus Router for command size = 4

  $cmd = $this->cmd;
  
  # First, check if it's a module
  if( $this->isModule($cmd[0])){
   if( $this->hasController($cmd[0],$cmd[1])){
     $this->result['module'] = $cmd[0];
     $this->result['controller'] = $cmd[1];
     $this->result['action'] = $cmd[2];
     $this->result['argc'] = 1;
     $this->result['argv'][] = $cmd[3];
     return;;

   }else{
     # Controller not exists in this module
     $this->status = 402;
	 return;
   }
  }
 
  # Then, check if it's a controller in 'index' module
  if( $this->hasController('index',$cmd[0])){
   $this->result['controller'] = $cmd[0];
   $this->result['action'] = $cmd[1];
   $this->result['argc'] = 2;
   $this->result['argv'][] = $cmd[2];
   $this->result['argv'][] = $cmd[3];
   return;;

  }else{
   # Controller not exists in 'index' module
   $this->status = 401;
  }
?>
