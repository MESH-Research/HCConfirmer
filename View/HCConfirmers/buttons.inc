<?php
/**
 * COmanage Registry CO Invite Reply Buttons
 *
 * Portions licensed to the University Corporation for Advanced Internet
 * Development, Inc. ("UCAID") under one or more contributor license agreements.
 * See the NOTICE file distributed with this work for additional information
 * regarding copyright ownership.
 *
 * UCAID licenses this file to you under the Apache License, Version 2.0
 * (the "License"); you may not use this file except in compliance with the
 * License. You may obtain a copy of the License at:
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * 
 * @link          http://www.internet2.edu/comanage COmanage Project
 * @package       registry
 * @since         COmanage Registry v3.1.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 */

  if(!empty($invite['CoInvite']['email_address_id'])) {

    // We are verifying email
    
    print $this->Html->link(
      _txt('op.confirm'),
      array('plugin' => null,
            'controller' => 'co_invites',
            'action' => 'authverify',
            $invite['CoInvite']['invitation']),
      array('class' => 'checkbutton')
    );
    
    print $this->Html->link(
      _txt('op.cancel'),
      array('plugin' => null,
            'controller' => 'co_invites',
            'action' => 'decline',
            $invite['CoInvite']['invitation']),
      array('class' => 'cancelbutton')
    );
  } else {


  if( ! is_null( $user_societies ) &&
         in_array( $societies_list[$current_enrollment_flow_id], array_merge( $user_societies, array( 'HC' ) ) ) &&
         $email_verify['exists'] == false ) {

    print $this->Html->link(
      _txt('op.accept'),
      array('plugin' => null,
            'controller' => 'co_invites',
            'action' => (isset($co_enrollment_flow['CoEnrollmentFlow']['require_authn'])
                         && $co_enrollment_flow['CoEnrollmentFlow']['require_authn']) ? 'authconfirm' : 'confirm',
            $invite['CoInvite']['invitation']),
      array('class' => 'checkbutton')
    );

    print $this->Html->link(
      _txt('op.decline'),
      array('plugin' => 'h_c_confirmer',
            'controller' => 'h_c_confirmers',
            'action' => 'decline_petition',
            $invite['CoInvite']['invitation'], 0),
      array('class' => 'cancelbutton')
    ); 

  }
  //default text
  $btn_text = 'Decline';

    if( $current_enrollment_flow_cou != 'HC' ) {
       $btn_text = 'Return to Commons';

       print $this->Html->link(
        $btn_text,
        array('plugin' => 'h_c_confirmer',
            'controller' => 'h_c_confirmers',
            'action' => 'decline_petition',
           $invite['CoInvite']['invitation'], 0),
         array('class' => 'cancelbutton')
       );

     //'https://' . $societies_list[$current_enrollment_flow_id] . '.' . constant('HC_DOMAIN'),
     } else if( $current_enrollment_flow_cou == 'HC' && $email_verify['exists'] == true ) {
       $btn_text = 'Remind Me';
	
       print $this->Html->link(
        $btn_text,
        array('plugin' => 'h_c_confirmer',
            'controller' => 'h_c_confirmers',
            'action' => 'decline_petition',
            $invite['CoInvite']['invitation'], $duplicate_email),
         array('class' => 'cancelbutton')
       );
     }

  }
