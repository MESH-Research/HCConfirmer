<?php
/*** COmanage Registry HC Confirmer Controller
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

 App::uses("StandardController", "Controller");

require_once('/var/www/env.php');

class HCConfirmersController extends StandardController {
  // Class name, used by Cake
  public $name = "HCConfirmers";
 
  public $uses = array('HCConfirmer.HCConfirmer', 'OrgIdentity', 'OrgIdentitySource', 'OrgIdentitySourceRecord','CoPetition', 'CoPerson', 'EmailAddress', 'CoEnrollmentFlow', 'CoInvite', 'CoPetitionHistoryRecord' );

  public $societies = [
    '162' => 'AJS',
    '164' => 'ASEEES',
    '166' => 'CAA',
    '158' => 'HC',
    '160' => 'MLA',
    '389' => 'UP'
  ];

  public $duplicate_email = false;

  public $cur_enrollment_flow;

  public $expired_data = array();

  /**
   * Callback before other controller methods are invoked or views are rendered.
   * - postcondition: Auth component is configured 
   *
   * @since  COmanage Registry v3.1.0
   * @throws UnauthorizedException (REST)
   */
  
  function beforeFilter() {
    // Since we're overriding, we need to call the parent to run the authz check
    parent::beforeFilter();
    
    // Allow invite handling to process without a login page
    $this->Auth->allow('decline_petition', 'reply');
  }

  /**
   * Authorization for this Controller, called by Auth component
   * - precondition: Session.Auth holds data used for authz decisions
   * - postcondition: $permissions set with calculated permissions
   *
   * @since  COmanage Registry v3.1.0
   * @return Array Permissions
   */
  
  function isAuthorized() {
    $roles = $this->Role->calculateCMRoles();
    
    // Construct the permission set for this user, which will also be passed to the view.
    $p = array();
    
    // Determine what operations this user can perform
    
    // Reply to an invitation?
    $p['reply'] = true;
    
    $this->set('permissions', $p);
    return($p[$this->action]);
  }
  
 public function willHandle($coId, $coInvite, $coPetition) {
   // We always want to handle the request!
   return true;
 }

  /**
   * Handle an invitation reply request. Note: This is intended to render a confirmation
   * page, NOT to actually process the reply.
   *
   * @since  COmanage Registry v3.1.0
   * @param  Integer $inviteid CO Invitation ID
   */
  
  function reply($inviteid) {

    // Pull the data here that your view will need. eg:
    // (If you want to use the default confirmation buttons, you need to set $invite
    // and $co_enrollment_flow.) 

    $logPrefix = "HCConfirmersController reply ";
    $args = array();
    $args['conditions']['CoInvite.invitation'] = $inviteid;
    $args['contain'] = array('CoPetition', 'EmailAddress');
    
    $invite = $this->CoInvite->find('first', $args);

    $this->set('invite', $invite);

    $args = array();
    $args['conditions']['CoPerson.id'] = $invite['CoInvite']['co_person_id'];
    $args['contain'] = array('CoInvite', 'PrimaryName', 'CoPersonRole');    

    $invitee = $this->CoInvite->CoPerson->find('first', $args);

    //Enrollment flows that use EnvSource Org Identity Source will have verified emails
    //TODO might have to search for active Org Identities
    $emailVerify = $this->checkEmailAvailability( $invitee['CoInvite']['mail'], $invite['CoPetition']['enrollee_org_identity_id'] );

    if( $this->duplicate_email == true ) {
      $this->set('duplicate_email', '1');
    } else {
      $this->set('duplicate_email', '0');
    }

    $user_societies = $this->searchByEmail( $invitee['CoInvite']['mail'], $invite['CoPetition']['co_enrollment_flow_id'] );

    //if the current society is not in the $user_societies array, then the user is expired
    if( ! empty( $this->expired_data ) && $this->expired_data['status'] == true ) {
	$this->set('user_expired', true);
    } else {
	$this->set('user_expired', false);
    }

    $this->set('user_societies', $user_societies);
    $this->set('current_enrollment_flow_cou', $this->societies[$invite['CoPetition']['co_enrollment_flow_id']]);
    $this->set('current_enrollment_flow_id', $invite['CoPetition']['co_enrollment_flow_id'] );
    $this->set('societies_list', $this->societies );   
    $this->set('email_verify', $emailVerify );
    $this->set('invitee', $invitee);
    $this->set('title_for_layout', 'Invitation to Humanities Commons');
    $this->set('hc_domain', constant('HC_DOMAIN') );

    $petition = $this->CoPetition->find('first', [ 'conditions' => [ 'CoPetition.enrollee_co_person_id' => $invite['CoInvite']['co_person_id'] ], 'recursive' => -1 ] );

    if(!empty($invite['CoPetition']['co_enrollment_flow_id'])) {
      $args = array();
      $args['conditions']['CoEnrollmentFlow.id'] = $invite['CoPetition']['co_enrollment_flow_id'];
      $args['contain'][] = 'CoEnrollmentAttribute';
      
      $enrollmentFlow = $this->CoEnrollmentFlow->find('first', $args);

      if(!$emailVerify['exists'] && ( false === $user_societies ||
        in_array($this->societies[$invite['CoPetition']['co_enrollment_flow_id']], array_merge($user_societies, array('HC')) ) ) ) {
	      $this->redirect( array( 'plugin' => null,
				      'controller' => 'co_invites',
                                'action' => (isset($enrollmentFlow['CoEnrollmentFlow']['require_authn']) &&
                                                   $enrollmentFlow['CoEnrollmentFlow']['require_authn'])
                                                   ? 'authconfirm' : 'confirm',
                                $invite['CoInvite']['invitation'] ) );
      }

      $this->cur_enrollment_flow = $this->societies[$enrollmentFlow['CoEnrollmentFlow']['id']]; 
      $this->set('co_enrollment_flow', $enrollmentFlow);

    }

    $errorInfo = array (
        'Petition ID:' . $invite['CoPetition']['id'],
        'Email:' . $invitee['CoInvite']['mail'],
        $emailVerify['exists'] ? 'Email Exists:true' : 'Email Exists:false',
        'Enrollment Flow:' . $enrollmentFlow['CoEnrollmentFlow']['name'],
        empty($user_societies) ? 'User Societies:NONE' : 'User Societies:' . implode(',', $user_societies),
    );

    $this->CoPetitionHistoryRecord->record( $invite['CoPetition']['id'], $invite['CoPetition']['enrollee_co_person_id'], $invite['CoPetition']['status'], implode(' - ', $errorInfo) );

    $this->log($logPrefix . implode(' - ', $errorInfo));

  }

  public function searchByEmail( $email, $current_ef_id ) {

    $ret = array();

    // First we need to figure out what plugins we have available.

    $args = array();
    $args['conditions']['OrgIdentitySource.status'] = SuspendableStatusEnum::Active;
    $args['conditions']['OrgIdentitySource.co_id'] = 2;
    $args['contain'] = false;

    $sources = $this->OrgIdentitySource->find('all', $args);

    if(empty($sources)) {
      return false;
    }

    $society_list = array();
    $is_expired = array();

    foreach($sources as $s) {

     if($s['OrgIdentitySource']['sync_mode'] == SyncModeEnum::Query) {
       //$candidates = $this->OrgIdentitySource->find($s['OrgIdentitySource']['id'], array('mail' => $email));

      try {
      $candidates = $this->OrgIdentitySource->search($s['OrgIdentitySource']['id'], array('mail' => $email));
      foreach($candidates as $key => $c) {

	//lets set the user's email that expired into this array
        $is_expired['email'] = $c['EmailAddress'][0]['mail'];

        // Key results by source ID in case different sources return the same keys

        // See if there is an associated org identity for the candidate

        $args = array();
        $args['conditions']['OrgIdentitySourceRecord.org_identity_source_id'] = $s['OrgIdentitySource']['id'];
        $args['conditions']['OrgIdentitySourceRecord.sorid'] = $key;
        $args['contain'] = array('OrgIdentity');

        $ret[ $s['OrgIdentitySource']['id'] ][$key] = $this->OrgIdentitySourceRecord->find('first', $args);

        // Append the source record retrieved from the backend
        $ret[ $s['OrgIdentitySource']['id'] ][$key]['OrgIdentitySourceData'] = $c;

        // And the source info itself
        $ret[ $s['OrgIdentitySource']['id'] ][$key]['OrgIdentitySource'] = $s['OrgIdentitySource'];

//var_dump( $s['OrgIdentitySource']['co_pipeline_id'] );
//var_dump( $this->CoPipeline->find('first', array('conditions' => array('CoPipeline.co_pipeline_id' => $s['OrgIdentitySource']['co_pipeline_id'])) ) );

        if( count( explode( '_', $ret[$s['OrgIdentitySource']['id']][$key]["OrgIdentitySource"]["description"] ) ) > 1 ) {
          $society = explode( '_', $ret[$s['OrgIdentitySource']['id']][$key]["OrgIdentitySource"]["description"] );
        } else {
          $society = explode( ' ', $ret[$s['OrgIdentitySource']['id']][$key]["OrgIdentitySource"]["description"] );
        }

	//lets check if the current user is expired and belongs to the MLA enrollment flow
	if( $this->societies[$current_ef_id] == 'MLA' ) {

		$detail_record = $this->OrgIdentitySource->retrieve($s['OrgIdentitySource']['id'], $key);

		$is_expired = $this->calculate_expiration( $detail_record, $society, $this->societies[$current_ef_id] );

	} elseif ( $this->societies[$current_ef_id] != 'HC' ) {

		$is_expired = $this->calculate_expiration( $c, $society, $this->societies[$current_ef_id] );	

	}

	//lets only add the societies that are not expired into the list
        if( count( $society ) > 1 && array_key_exists($society[0], $is_expired) == false || $this->expired_data['status'] == false ) { 
          $society_list[] = $society[0];
        }

       }
     } catch( RuntimeException $e ) {
     }
    }
   }
	return $society_list;

  }

/**
 * Gets data and determines wether the user's account expired
 * 
 * @param  array $org_arr       array from OrgIdentitySource query
 * @param  array $user_society  array from explode that contains societies that the user belongs to
 * @param  string $cur_ef	current enrollment flow the user is in 
 *
 * @return array $is_expired    final data array to use that contains expired user data and society data
 */
  public function calculate_expiration( $org_arr, $user_society, $cur_ef ) {

    $expired = array();

    if( array_key_exists( 'orgidentity', $org_arr ) ) {
	
	//checks if the current user is expired through retrieve method in OrgIdentitySource model
	if( array_key_exists('valid_through', $org_arr['orgidentity']['OrgIdentity']) && !is_null( $org_arr['orgidentity']['OrgIdentity']['valid_through'] ) ) {

		    if( ( date('Y-m-d', strtotime( $org_arr['orgidentity']['OrgIdentity']['valid_through']) ) < date('Y-m-d') ) ) {
			 if( $user_society[0] == $cur_ef ) {
			 	$expired[$user_society[0]]['status'] = true;
				$this->expired_data = array('society' => $cur_ef, 'status' => 'true');  
			 }
		     }

		} else {
		    $this->expired_data = array('society' => false, 'status' => false);
		    $expired[$user_society[0]]['status'] = false;
		}

     } else {
       
	//lets check if the current user is expired
        if( array_key_exists('valid_through', $org_arr['OrgIdentity']) && !is_null( $org_arr['OrgIdentity']['valid_through'] ) ) {

            if( ( date('Y-m-d', strtotime($org_arr['OrgIdentity']['valid_through']) ) < date('Y-m-d') ) ) {
		 if( $user_society[0] == $cur_ef ) {
		     $expired[$user_society[0]]['status'] = true;
		     $this->expired_data = array('society' => $cur_ef, 'status' => 'true');
		 }
             }

        } else {
	    $this->expired_data = array('society' => false, 'status' => false);
            $expired[$user_society[0]]['status'] = false;
        }



     }

     return $expired;
 
  }

 /**
 * Checks if email already exists (verified) or not and outputs message
 * 
 * @param  string $email        current email being used for CoInvite
 * @param  string $orgidentityid current org_identity_id being used for CoInvite
 * @return array  $emailData    email data to be output with message if email exists and is verified
 */
  public function checkEmailAvailability( $email, $orgidentityid ) {

   /*
    * Check if the email has been verified at any time in the past.
    */
   $args = array();
   $args['conditions']['EmailAddress.mail'] = $email;
   $args['conditions'][] = 'EmailAddress.verified IS true';
   $args['conditions'][] = 'EmailAddress.email_address_id IS NULL';
   $args['conditions'][] = 'OrgIdentity.id != ' . $orgidentityid;
   $args['fields'][] = 'DISTINCT EmailAddress.mail, EmailAddress.verified';
   $e = $this->EmailAddress->find('first', $args);

  if( array_key_exists('EmailAddress', $e) && $e['EmailAddress']['verified'] == true ) {
      
      $this->duplicate_email = true;

      $emailData = [
	'exists' => true, 
	'message' => 'We already have this email on file with <em>Humanities Commons</em>.',
	'hc_domain' => constant( 'HC_DOMAIN' )
      ];

    } else {
      $emailData = ['exists' => false];
    }

    return $emailData;

  }

  public function decline_petition( $inviteid ) {

    $logPrefix = "HCConfirmersController decline_petition ";
    $args = array();
    $args['conditions']['CoInvite.invitation'] = $inviteid;
    $args['contain'] = array('CoPetition', 'EmailAddress');

    $invite = $this->CoInvite->find('first', $args);

    $this->CoPetitionHistoryRecord->record( $invite['CoPetition']['id'], $invite['CoPetition']['enrollee_co_person_id'], $invite['CoPetition']['status'], $logPrefix . 'Petition ID:' . $invite['CoPetition']['id'] . ' - Redirect to:' . $this->params['pass'][1] );

    $this->log($logPrefix . 'Petition ID:' . $invite['CoPetition']['id'] . ' - Redirect to:' . $this->params['pass'][1]);

    try {
        $this->CoInvite->processReply( $inviteid, false );
        $this->CoPetition->updateStatus( $invite['CoPetition']['id'], StatusEnum::Declined, $invite['CoInvite']['co_person_id'] );
        $this->CoPerson->recalculateStatus( $invite['CoInvite']['co_person_id'] );

        $current_society_id = array_search( $this->params['pass'][1], $this->societies );

        if( $this->params['pass'][1] == 'remind-me' ) {
            $this->redirect('https://' . constant('HC_DOMAIN') . '/remind-me' );
        } else if( in_array( $this->params['pass'][1], $this->societies ) == true ) {
            $this->redirect( array( 'plugin' => null, 'controller' => 'co_petitions', 'action' => 'start', 'coef:' . $current_society_id ) );
        } else if ( $this->params['pass'][1] == 'commons' )  {
            $this->redirect('https://' . constant('HC_DOMAIN') );
        } else {
            $this->redirect('https://' . constant('HC_DOMAIN') ); //Have to go somewhere
        }
    } catch(Exception $e) {
        echo $e->getMessage();
    }

  }

}
