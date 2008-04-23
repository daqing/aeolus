<?php
    /**
	 * Index_Index_View class in 'index' module
	 *
	 */

    class Index_Index_View extends View
	{
	  function render_control()
	  {
	     echo 'Control Panel';
	  }

	  function render_sections()
	  {
         ?>
         <div class="section"><h4>Welcome to Aeolus framework !</h4>
         <p>&nbsp;&nbsp;<a href="http://code.google.com/p/aeolus">Aeolus</a> is a productive PHP Web framework that's fast , lightweight and flexible.<br/><br/>&nbsp;&nbsp;See Aeolus's <a href="http://code.google.com/p/aeolus/w/list">Wiki</a> for more details.<span style="color:green;font-size:8pt;"><br/><br/>&nbsp;&nbsp;Notice:<br/><br/>&nbsp;&nbsp;&nbsp;&nbsp;For now Aeolus framework is at its very early stage, so don't use Aeolus for *production*.<br/>&nbsp;&nbsp;&nbsp;&nbsp;The first stable release of Aeolus will be available 6 months later. <br/>&nbsp;&nbsp;&nbsp;&nbsp;Thank you for your interests in Aeolus.</span></p>
		 </div>
         <?php
			  if( ! $_SESSION['aeolus']['can_rewrite']){
			    echo '<div class="section">';
                echo '<h4>URLs can not be rewritten</h4>';
				echo '<p style="color:green;font-size:8pt;">&nbsp;&nbsp;We\'ve detected that your apache configuration does not load mod_rewrite or allow .htaccess file to override the default settings.<br/><br/>&nbsp;&nbsp;It\'s not an issue most time, but you\'d enable it to get better URLs.</p>';
				echo '</div>';
			  }
	  }
	}
?>
