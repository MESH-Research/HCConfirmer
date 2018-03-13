<?php
/**
 * COmanage Registry HC Confirmer Plugin Language File
 *
 * Copyright (C) 2017 Modern Language Association
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software distributed under
 * the License is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied. See the License for the specific language governing
 * permissions and limitations under the License.
 *
 * @copyright     Copyright (C) 2017 Modern Language Association
 * @link          http://www.internet2.edu/comanage COmanage Project
 * @package       registry-plugin
 * @since         COmanage Registry v1.1.0
 * @license       Apache License, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 * @version       $Id$
 */
  
global $cm_lang, $cm_texts;

// When localizing, the number in format specifications (eg: %1$s) indicates the argument
// position as passed to _txt.  This can be used to process the arguments in
// a different order than they were passed.

$cm_h_c_confirmer_texts['en_US'] = array(
  //HCConfirmer
  'op.hcc.ret.hc'     => 'Return to Humanities Commons',
  'op.hcc.decline.ret.remind' => 'Remind Me',
  'op.hcc.decline.ret.society' => 'Register for %s Commons',
  'op.hcc.register.hc' => 'Register for Humanities Commons',
  'op.hcc.email.duplicate' => 'This e-mail is already associated with a <em>Humanities Commons</em> account. Forgotten how you log in? Click the Remind Me button below.',
  'op.hcc.email.na' => '<p>This e-mail is not associated with a current %1$s member account.
        <ul>
                <li> Register for %1$s Commons with another email</li>
                <li> Register for Humanities Commons (you can always contact us if you believe you should have access to %1$s Commons)</li>
                <li>Explore Humanities Commons</li>
        </ul>
</p>',
'op.hcc.email.exp' => '<p>The account associated with this e-mail is not a currently active %1$s member account.
        <ul>
                <li> Register for %1$s Commons with another email</li>
                <li> Register for Humanities Commons (you can always contact us if you believe you should have access to %1$s Commons)</li>
        </ul>
</p>'
);
