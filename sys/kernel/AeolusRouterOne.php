<?php
  # Aeolus Router for command size = 1

  $cmd = $this->cmd;
  
  if( $cmd[0] != '' ){
    # First, check if it's a module
    if( $this->isModule($cmd[0]) ){
      if( $this->hasController($cmd[0],'index')){
        $this->result['module'] = $cmd[0];
        return;
      }else{
        $this->cmd[1] = 'index';
        $this->status = 402;
        return;
      }
    }
    
	# Then, chekc if it's a controller in 'index' module
    if( $this->hasController('index',$cmd[0])){
      $this->result['controller'] = $cmd[0];
      return;

    }else{
      # Neither a module, nor a controller in 'index' module
      $this->status= 401;
    }
  }
?>
