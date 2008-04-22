<?php
  /**
   * Utility functions
   */
  
  /**
   * Aeolus init 
   *
   */
  function aeolus_init()
  {
    # Do nothing
  }


   /**
    * Strip slashes if magic_quote_gpc is on
    * 
    */
   function aeolus_stripslashes($v)
   {
   	if(get_magic_quotes_gpc()){
   		return stripslashes($v);
   	}else{
   		return $v;
   	}
   }


  /**
   * Calculate how much time between two datetime
   *
   * @param string $begin
   * @param string $end
   * @return string $duration
   */
  function calculate_date($begin,$end)
  {
      $begin = date_parse($begin);
      $end = date_parse($end);
      
      if( 0 == $begin['warning_count'] && 0 == $end['warning_count']){
      	$year = abs($end['year'] - $begin['year']);
      	$month = abs($end['month'] - $begin['month']);
      	$day = abs($end['day'] - $begin['day']);
      	$hour = abs($end['hour'] - $begin['hour']);
      	$minute = abs($end['minute'] - $begin['minute']);
      	$second = abs($end['second'] - $begin['second']);
      	$duration = '';
      	
      	if($year) $duration .= $year.'年';
      	if($month) $duration .= $month.'月';
      	if($day) $duration .= $day.'天';
      	if($hour) $duration .= $hour.'小时';
      	if($minute) $duration .= $minute.'分';
      	if($second) $duration .= $second.'秒';
      	$duration .= '前';
      	
      	return $duration;
      }
      
      return null;
  }


?>
