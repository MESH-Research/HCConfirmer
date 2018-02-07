<h1>test</h1>
<p>User found:</p>
<p><?php print $existing_user[0]['Name']['given'] . ' ' . $existing_user[0]['Name']['family']; ?></p>
<pre>
<?php //var_dump( $existing_user ); ?>
</pre>
<?php
print $this->Html->link(
         'Is this you? Merge emails',
          array('plugin' => 'h_c_confirmer',
              'controller' => 'h_c_confirmers',
              'action' => 'trigger_merge_enrollment',
               
           array('class' => 'cancelbutton mergeEnrollment')
         ) );
