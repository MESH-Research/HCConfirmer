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
 
//public $components = array('DebugKit.Toolbar');
 
  public $uses = array('HCConfirmer.HCConfirmer', 'CoPetitionAttribute', 'CoOrgIdentityLink','OrgIdentity', 'OrgIdentitySource', 'OrgIdentitySourceRecord', 'CoPetition', 'CoPerson', 'EmailAddress', 'CoEnrollmentFlow', 'CoInvite', 'CoPersonRole', 'CoEnrollmentAttributes');

  public $societies = [
    '162' => 'AJS',
    '164' => 'ASEEES',
    '166' => 'CAA',
    '158' => 'HC',
    '454' => 'HC',
    '160' => 'MLA',
    '389' => 'UP'
  ];

  public $dupe_email = false;

  public $cur_enrollment_flow;

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

    $args = array();
    $args['conditions']['CoInvite.invitation'] = $inviteid;
    $args['contain'] = array('CoPetition', 'EmailAddress');
    
    $invite = $this->CoInvite->find('first', $args);

    $this->set('invite', $invite);
   
    $args = array();
    $args['conditions']['CoPerson.id'] = $invite['CoInvite']['co_person_id'];
//original $args['contain'] = array('Co', 'PrimaryName');
    $args['contain'] = array('CoInvite', 'PrimaryName', 'CoPersonRole');    

    $invitee = $this->CoInvite->CoPerson->find('first', $args);

    //Enrollment flows that use EnvSource Org Identity Source will have verified emails
    //TODO might have to search for active Org Identities
    $emailVerify = $this->checkEmailAvailability( $invitee['CoInvite']['mail'], $invite['CoPetition']['enrollee_org_identity_id'] );

$user_societies = $this->searchByEmail( $invitee['CoInvite']['mail'] );

$this->set('user_societies', $user_societies);
$this->set('current_enrollment_flow_cou', $this->societies[$invite['CoPetition']['co_enrollment_flow_id']]);
$this->set('current_enrollment_flow_id', $invite['CoPetition']['co_enrollment_flow_id'] );
$this->set('societies_list', $this->societies );   
 
    $this->set('email_verify', $emailVerify );

    $petition = $this->CoPetition->find('first', [ 'conditions' => [ 'CoPetition.enrollee_co_person_id' => $invite['CoInvite']['co_person_id'] ], 'recursive' => -1 ] );

//grabs current step in petition
//$this->CoPetition->currentStep( $petition['CoPetition']['id'] );

    //TODO what is this doing?
    //checks for duplicate petition
    if( $emailVerify['exists'] == true && $petition['CoPetition']['status'] == 'PC' ) { }

    $this->set('invitee', $invitee);
    $this->set('title_for_layout', 'Invitation to Humanities Commons');
    
    if(!empty($invite['CoPetition']['co_enrollment_flow_id'])) {
      $args = array();
      $args['conditions']['CoEnrollmentFlow.id'] = $invite['CoPetition']['co_enrollment_flow_id'];
      $args['contain'][] = 'CoEnrollmentAttribute';
      
      $enrollmentFlow = $this->CoEnrollmentFlow->find('first', $args);

//var_dump( $enrollmentFlow );

     $this->cur_enrollment_flow = $this->societies[$enrollmentFlow['CoEnrollmentFlow']['id']]; 

      $this->set('co_enrollment_flow', $enrollmentFlow);
    }

  }

  public function searchByEmail( $email ) {

   $ret = array();

    // First we need to figure out what plugins we have available.

    $args = array();
    $args['conditions']['OrgIdentitySource.status'] = SuspendableStatusEnum::Active;
    $args['conditions']['OrgIdentitySource.co_id'] = 2;
    $args['contain'] = false;

    $sources = $this->OrgIdentitySource->find('all', $args);

    if(empty($sources)) {
      throw new InvalidArgumentException(_txt('er.ois.search.none'));
    }

    $society_list = array();

    foreach($sources as $s) {
     if($s['OrgIdentitySource']['sync_mode'] == SyncModeEnum::Query) {
      //$candidates = $this->OrgIdentitySource->find('first', );
       //$candidates = $this->OrgIdentitySource->find($s['OrgIdentitySource']['id'], array('mail' => $email));
       $candidates = $this->OrgIdentitySource->search($s['OrgIdentitySource']['id'], array('mail' => $email));

      foreach($candidates as $key => $c) {
        // Key results by source ID in case different sources return the same keys

        // See if there is an associated org identity for the candidate

        $args = array();
        $args['conditions']['OrgIdentitySourceRecord.org_identity_source_id'] = $s['OrgIdentitySource']['id'];
        $args['conditions']['OrgIdentitySourceRecord.sorid'] = $key;
        $args['contain'] = array('OrgIdentity');

        $ret[ $s['OrgIdentitySource']['id'] ][$key] =
          $this->OrgIdentitySourceRecord->find('first', $args);

        // Append the source record retrieved from the backend
        $ret[ $s['OrgIdentitySource']['id'] ][$key]['OrgIdentitySourceData'] = $c;

        // And the source info itself
        $ret[ $s['OrgIdentitySource']['id'] ][$key]['OrgIdentitySource'] = $s['OrgIdentitySource'];

$society = explode( '_', $ret[$s['OrgIdentitySource']['id']][$key]["OrgIdentitySource"]["description"] );
if( count( $society ) > 1 ) {
   $society_list[] = $society[0];
}

      }
     }
    }

    return $society_list;

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

      $this->dupe_email = true;

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
    
    $args = array();
    $args['conditions']['CoInvite.invitation'] = $inviteid;
    $args['contain'] = array('CoPetition', 'EmailAddress');

    $invite = $this->CoInvite->find('first', $args);

try {
    $this->CoInvite->processReply( $inviteid, false );
    $this->CoPetition->updateStatus( $invite['CoPetition']['id'], StatusEnum::Declined, $invite['CoInvite']['co_person_id'] );
    $this->CoPerson->recalculateStatus( $invite['CoInvite']['co_person_id'] );

if( $this->dupe_email == true ) {
    $this->redirect('https://' . constant('HC_DOMAIN') . '/remind-me' );
} else {
    $this->redirect('https://' . constant('HC_DOMAIN') );
}

 } catch(Exception $e) {
echo $e->getMessage();
}

  }

}
