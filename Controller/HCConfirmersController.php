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
    '160' => 'MLA',
    '389' => 'UP'
  ];

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
    $this->Auth->allow('reply');
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
echo "<pre>";


//var_dump( App::objects('Model') );

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
    $args['contain'] = array('CoInvite', 'PrimaryName', 'CoPersonRole');    

    $invitee = $this->CoInvite->CoPerson->find('first', $args);

    $emailVerify = $this->checkEmailAvailability( $invitee['CoInvite']['mail'] );

//var_dump( $emailVerify );
$user_societies = $this->searchByEmail( $invitee['CoInvite']['mail'] );

//var_dump( $user_societies );

$this->set('user_societies', $user_societies);
$this->set('current_enrollment_flow_cou', $this->societies[$invite['CoPetition']['co_enrollment_flow_id']]);
$this->set('current_enrollment_flow_id', $invite['CoPetition']['co_enrollment_flow_id'] );
$this->set('societies_list', $this->societies );
//echo "<br />currently in " . $this->societies[$invite['CoPetition']['co_enrollment_flow_id']] . ' enrollment flow';    
 
    $this->set('email_verify', $emailVerify );

    $petition = $this->CoPetition->find('first', [ 'conditions' => [ 'CoPetition.enrollee_co_person_id' => $invite['CoInvite']['co_person_id'] ], 'recursive' => -1 ] );

//var_dump( $petition );
//debug( $this->CoOrgIdentityLink );    

    if( $emailVerify['exists'] == true && $petition['CoPetition']['status'] == 'PC' ) {


      //set as duplicate petition
      //$this->CoPetition->updateAll([ 'CoPetition.status' => $this->CoPetition->getDataSource()->value(StatusEnum::Duplicate) ], [ 'CoPetition.enrollee_co_person_id' => $invite['CoInvite']['co_person_id'] ])->save();
    // $this->CoPetition->updateStatus( $petition['CoPetition']['id'], $this->CoPetition->getDataSource()->value(StatusEnum::Duplicate), $invitee['CoPerson']['id'] );
      
      //then we decline the duplicate petition
      //$this->CoInvite->decline( $invite['CoInvite']['invitation'] )->save();
    }

//var_dump( $petition );

/*
use orgidentitylinks to get co_person data
use orgIdentity model to join data from orgidentitylinks
*/
/*echo "<pre>";
var_dump( $emailVerify );
//var_dump( $this->OrgIdentity->find('first') );
var_dump( $petition['CoPetition'] );
//var_dump( $this->CoPetition->find('first', [ 'conditions' => [ 'CoPetition.enrollee_co_person_id' => $invite['CoInvite']['co_person_id'] ], 'recursive' => -1 ] ) );
//var_dump( $this->CoPetitionAttribute->find('first', [ 'conditions' => [ 'CoPetitionAttribute.co_petition_id' => $petition['CoPetition']['id'] ] ] ) );
echo "</pre>";*/

    $this->set('invitee', $invitee);
    $this->set('title_for_layout', 'Invitation to Humanities Commons');
    
    if(!empty($invite['CoPetition']['co_enrollment_flow_id'])) {
      $args = array();
      $args['conditions']['CoEnrollmentFlow.id'] = $invite['CoPetition']['co_enrollment_flow_id'];
      $args['contain'][] = 'CoEnrollmentAttribute';
      
      $enrollmentFlow = $this->CoEnrollmentFlow->find('first', $args);
      
      $this->set('co_enrollment_flow', $enrollmentFlow);
    }

echo "</pre>";
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
//var_dump( explode( ',', $ret[$s['OrgIdentitySource']['id']][$key]['OrgIdentitySourceRecord']['source_record'] ) );

$society = explode( '_', $ret[$s['OrgIdentitySource']['id']][$key]["OrgIdentitySource"]["description"] );
if( count( $society ) > 1 ) {
   $society_list[] = $society[0];
}

//var_dump( $society_list );

      }
     }
    }
//echo "RESULTS";

    return $society_list;

  }

 /**
 * Checks if email is available (verified) or not and outputs message
 * 
 * @param  string $email      current email being used for CoInvite
 * @return array  $emailData  email data to be output with message if email exists and is verified
 */
  public function checkEmailAvailability( $email ) {

    //lets query the EmailAdddress model and just return the data for that model, including orgidentity data
 //   $e_org_id = $this->EmailAddress->find('first', [ 'conditions' => [ 'EmailAddress.mail' => $email ] ] );
   // $e = $this->EmailAddress->find('all', [ 'conditions' => [ 'EmailAddress.mail' => $email ] ], [ 'joins' => [ [ 'table' => 'OrgIdentity', 'type' => 'LEFT', 'conditions' => [ 'OrgIdentity.id' => $e_org_id['OrgIdentity']['id']  ] ] ] ] );

//$e = $this->EmailAddress->find('first', [ 'conditions' => [ 'EmailAddress.mail' => $email ] ], [ 'joins' => [ [ 'table' => 'co_petitions', 'alias' => 'CoPetition', 'type' => 'INNER', 'conditions' => [ 'CoPetition.petitioner_co_person_id' => 'EmailAddress.co_person_id' ] ] ] ] );

  $e = $this->EmailAddress->find('first', [ 'conditions' => [ 'EmailAddress.mail' => $email ] ]  );
   // $e = $this->EmailAddress->find('first', [ 'conditions' => [ 'EmailAddress.mail' => $email ], 'contain' => ['OrgIdentity'] ] );
    //$orgidentity = $this->OrgIdentity->find('all', [ 'conditions' => [ 'OrgIdentity.id' => $e['OrgIdentity']['id'] ] ] );


    if( array_key_exists('EmailAddress', $e) && $e['EmailAddress']['verified'] == true ) {

      $emailData = [
	'exists' => true, 
	'message' => 'This email has already been enrolled in Humanities Commons.',
	'hc_domain' => constant( 'HC_DOMAIN' )
      ];

    } else {
      $emailData = ['exists' => false];
    }

    return $emailData;

  }

  public function decline_petition( $inviteid, $data_array ) {
    var_dump( $inviteid );

  }

}
