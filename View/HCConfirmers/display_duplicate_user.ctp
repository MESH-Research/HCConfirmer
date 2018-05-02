<p><?php

     if( $current_enrollment_flow_cou == 'HC' ) {
     	echo sprintf( _txt('op.hcc.name.duplicate'), '<em>Humanities Commons</em>' ); 
     } else {
	echo sprintf( _txt('op.hcc.name.duplicate'), $current_enrollment_flow_cou ); 
     }?></p>

<ul>
<?php foreach( $unique_emails as $email ) : ?>
<li><?php echo trim( $email['name'] ); ?></li>
<?php endforeach; ?>
</ul>
<p>If you still want to proceed, click Create Account. If you forgot your account click Remind Me below.</p>
<?php
    print $this->Html->link(
      'Create Account',
      array('plugin' => null,
            'controller' => 'co_invites',
            'action' => (isset($co_enrollment_flow['CoEnrollmentFlow']['require_authn'])
                         && $co_enrollment_flow['CoEnrollmentFlow']['require_authn']) ? 'authconfirm' : 'confirm',
            $invite['CoInvite']['invitation']),
      array('class' => 'checkbutton')
    );

       print $this->Html->link(
	     	_txt('op.hcc.decline.ret.remind'),
		array('plugin' => 'h_c_confirmer',
			'controller' => 'h_c_confirmers',
			'action' => 'decline_petition',
			 $invite['CoInvite']['invitation'], 'remind-me' ),
		 array('class' => 'cancelbutton')
	);
