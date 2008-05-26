<?php if(! defined('APP_STARTED')){die('<h3>BAD REQUEST.</h3>');}
  /**
   * IndexTestModel model class in 'index' module
   *
   */

  class IndexModel extends AeolusModel
  {
    public function getDatabases()
    {
	  $query = "SELECT * FROM aeolus_admin";

	  $re = $this->select($query);

	  if( $re['result'] ){
	    return $re['set'];
	  }
    }
  } 
?>
