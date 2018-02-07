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
 
  public $uses = array( 'CoPersonRole', 'Name', 'CoOrgIdentityLink', 'HCConfirmer.HCConfirmer', 'OrgIdentity', 'OrgIdentitySource', 'OrgIdentitySourceRecord','CoPetition', 'CoPerson', 'EmailAddress', 'CoEnrollmentFlow', 'CoInvite', 'CoPetitionHistoryRecord' );

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

    //$this->checkIfUserExists();

    // Allow invite handling to process without a login page
    $this->Auth->allow('decline_petition', 'reply');
    $this->Auth->allow('trigger_merge_enrollment', 'reply');
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

    $user_societies = $this->searchByEmail( $invitee['CoInvite']['mail'] );

var_dump( $user_societies );

if( $user_societies['is_expired']['status'] == true ) {
   $this->render('HCConfirmer.HCConfirmers/reply');
   return;
}

    $this->set('user_societies', $user_societies['society_list']);
    $this->set('current_enrollment_flow_cou', $this->societies[$invite['CoPetition']['co_enrollment_flow_id']]);
    $this->set('current_enrollment_flow_id', $invite['CoPetition']['co_enrollment_flow_id'] );
    $this->set('societies_list', $this->societies );   
    $this->set('email_verify', $emailVerify );
    $this->set('invitee', $invitee);
    $this->set('title_for_layout', 'Invitation to Humanities Commons');
    $this->set('hc_domain', constant('HC_DOMAIN') );

    $petition = $this->CoPetition->find('first', [ 'conditions' => [ 'CoPetition.enrollee_co_person_id' => $invite['CoInvite']['co_person_id'] ], 'recursive' => -1 ] );


//echo "<pre>";
//var_dump( $invitee );
//echo "co person: <br>"; 

//this is where all of the name results come from, some have a CoPerson with a status of null, others have SY and A
//var_dump( $this->Name->find( 'all', ['conditions' => [ 'Name.given' => $invitee['PrimaryName']['given'], 'Name.family' => $invitee['PrimaryName']['family'] ] ] ) );

$existingUser = null;
$names = $this->Name->find( 'all', ['conditions' => [ 'Name.given' => $invitee['PrimaryName']['given'], 'Name.family' => $invitee['PrimaryName']['family'] ] ] );

//if CoPerson status == A then the petition is active (as defined by PetitionStatusEnum)

foreach( $names as $name ) {

  if( ! is_null( $name['CoPerson']['status'] ) ) {

    $identityLink = $this->CoOrgIdentityLink->find('first', ['conditions' => [ 'CoOrgIdentityLink.co_person_id' => $name['CoPerson']['id'] ]]);
    //echo "<br/><br/>org_identity_link: <br/><br/>";
   // var_dump( $identityLink );
    //echo "<br/><br/>identity_link: <br/><br/>";
    $orgIdentity = $this->OrgIdentity->find('first', ['conditions' => ['OrgIdentity.id' => $identityLink['OrgIdentity']['id']] ] );
    //var_dump( $orgIdentity['Identifier'] );

   // echo "<br/><br/>CoPerson: <br/><br/>";
  // var_dump( $name['CoPerson']['status'] );

   if( $name['CoPerson']['status'] == 'A' ) {
      $existingUser[] = $name; 
      $existingUser[] = $orgIdentity['Identifier'];
      break;
   }

  }

}


if( ! is_null( $existingUser ) ) {

   $this->set('existing_user', $existingUser);
   $this->render('HCConfirmer.HCConfirmers/display_merge');

   return;

}

//var_dump( $this->CoPerson->find( 'first', [ 'conditions' => [ 'CoPerson'  ] ] ) );

//$orgid = $this->Name->find( 'first', ['conditions' => [ 'Name.given' => $invitee['PrimaryName']['given'], 'Name.family' => $invitee['PrimaryName']['family'] ] ] );

//var_dump( $this->OrgIdentityLink->find('first', ['conditions' => ['OrgIdentityLink.org_identity_id' => $orgid['OrgIdentity']['id'] ]] ) );

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

  public function searchByEmail( $email ) {

    $ret = array();

    // First we need to figure out what plugins we have available.

    $args = array();
    $args['conditions']['OrgIdentitySource.status'] = SuspendableStatusEnum::Active;
    $args['conditions']['OrgIdentitySource.co_id'] = 2;
    $args['contain'] = false;

    $sources = $this->OrgIdentitySource->find('all', $args);

/*echo "<pre>";
var_dump( $sources );
echo "</pre>";
*/
    if(empty($sources)) {
      return false;
    }

    $society_list = array();
    $is_expired = array();
echo "<pre>";

    foreach($sources as $s) {
     if($s['OrgIdentitySource']['sync_mode'] == SyncModeEnum::Query) {
       //$candidates = $this->OrgIdentitySource->find($s['OrgIdentitySource']['id'], array('mail' => $email));
       $candidates = $this->OrgIdentitySource->search($s['OrgIdentitySource']['id'], array('mail' => $email));

      foreach($candidates as $key => $c) {

var_dump( $c );
       
       $is_expired['email'] = $c['EmailAddress'][0]['mail'];

       //lets check if the current user is expired
       if( array_key_exists('valid_through', $c['OrgIdentity']) && array_key_exists('valid_from', $c['OrgIdentity']) ) {

	   if( ( date('Y-m-d', strtotime($c['OrgIdentity']['valid_through']) ) < date('Y-m-d') ) ) {
                $is_expired['status'] = true;
           }	   

       } else {
          $is_expired['status'] = false;
       }

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

        if( count( explode( '_', $ret[$s['OrgIdentitySource']['id']][$key]["OrgIdentitySource"]["description"] ) ) > 1 ) {
          $society = explode( '_', $ret[$s['OrgIdentitySource']['id']][$key]["OrgIdentitySource"]["description"] );
        } else {
          $society = explode( ' ', $ret[$s['OrgIdentitySource']['id']][$key]["OrgIdentitySource"]["description"] );
        }

        if( count( $society ) > 1 ) {
          $society_list[] = $society[0];
        }

      }
     }
    }
echo "</pre>";
    $data = array(
        'society_list' => $society_list,
        'is_expired' => $is_expired
    );

    return $data;

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

  function trigger_merge_enrollment( $peition_id  ) {

      //grab orgidentity of original user and link it to the current user petition after click for confirmation of merge
      $this->CoPeition->relinkOrgIdentity( $peition_id, $orig_org_id, $co_person_id_orig, 'merge');
      echo "test";
      //redirect to HC?

  }

  function checkIfUserExists() {

//$uses = array('HCConfirmer.HCConfirmer', 'OrgIdentity', 'OrgIdentitySource', 'OrgIdentitySourceRecord','CoPetition', 'CoPerson', 'EmailAddress', 'CoEnrollmentFlow', 'CoInvite', 'CoPetitionHistoryRecord' );



    echo "test";

echo "<pre>";



echo "<pre>";

//$this->render('HCConfirmer.HCConfirmers/display_merge');
    die();

  }

}
