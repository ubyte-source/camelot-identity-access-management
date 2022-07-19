<?PHP

namespace applications\iam\user\actions;

use IAM\Gateway;
use IAM\Request as IAMRequest;
use IAM\Configuration as IAMConfiguration;

use SendGrid;
use SendGrid\Mail\Mail;

use Knight\armor\Output;
use Knight\armor\Request;
use Knight\armor\Language;

use Entity\Validation;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\operations\Update;
use ArangoDB\entity\Vertex;
use ArangoDB\entity\common\Arango;
use ArangoDB\operations\common\Handling;

use applications\iam\user\database\Vertex as User;
use applications\iam\user\database\edges\Session;
use applications\iam\user\database\edges\UserToUser;
use applications\iam\policy\database\Vertex as Policy;
use applications\iam\group\database\Vertex as Group;

use extensions\Navigator;

use configurations\mail\Sendgrid as Configuration;

Policy::mandatories('iam/user/action/create');

function password()
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charlength = strlen($characters) - 1;
    $randstring = '';
    for ($i = 0; $i < 8; $i++) {
        $character_random_position = rand(0, $charlength);
        $randstring .= $characters[$character_random_position];
    }
    return $randstring;
}

const AUTOASSIGN = [
    Group::COLLECTION => [
        '40124046'
    ]
];

$user = User::login();
$user_query = ArangoDB::start($user);
$user_query_select = $user_query->select();
$user_query_select->getLimit()->set(1);
$user_query_select_return = 'RETURN 1';
$user_query_select->getReturn()->setPlain($user_query_select_return);
$user_query_select_statement = $user_query_select->getStatement();
$user_query_select_statement->setExpect(1)->setHideResponse(true);

$user_child = $user->useEdge(UserToUser::getName())->vertex();
$user_child_uploads = array_column($_FILES, 'tmp_name');
$user_child_uploads_keys = array_keys($_FILES);
$user_child_uploads = array_combine($user_child_uploads_keys, $user_child_uploads);
$user_child->setFromAssociative((array)Request::post(), $user_child_uploads);

if (false === Policy::check('iam/privilege/user/type')) $user_child->getField('type')->setValue(User::HUMAN);

foreach (Vertex::MANAGEMENT as $field_name)
    $user_child->getField($field_name)->setProtected(false)->setRequired(true)->setValue($user->getField(Arango::KEY)->getValue());

$user_child_fields_values = $user_child->getAllFieldsValues();
$user_child_fields_values = serialize($user_child_fields_values) . microtime(true) . Navigator::getFingerprint();
$user_child_fields_values = hash('sha512', $user_child_fields_values);

$user_child_field_hash = $user_child->addField('hash');
$user_child_field_hash_pattern = Validation::factory('ShowString');
$user_child_field_hash->setPatterns($user_child_field_hash_pattern);
$user_child_field_hash->addUniqueness();
$user_child_field_hash->setProtected(false)->setRequired(true);
$user_child_field_hash->setValue($user_child_fields_values);

$user_child_warnings = $user_child->checkRequired()->getAllFieldsWarning();

Language::dictionary(__file__);
$exception_message = __namespace__ . '\\' . 'exception' . '\\';

$user_unique = new User();
$user_unique->addFieldClone($user_child_field_hash);
$user_unique_field_hash = $user_unique->getField($user_child_field_hash->getName());
$user_unique_field_hash->setProtected(false);
$user_unique_field_hash->setValue($user_child_fields_values);

$user_unique_query = ArangoDB::start($user_unique);
$user_unique_query_select = $user_unique_query->select();
$user_unique_query_select->getLimit()->set(1);
$user_unique_query_select_return = 'RETURN 1';
$user_unique_query_select->getReturn()->setPlain($user_unique_query_select_return);
$user_unique_query_select_statement = $user_unique_query_select->getStatement();
$user_unique_query_select_statement_exception_message = $exception_message . 'hash';
$user_unique_query_select_statement_exception_message = Language::translate($user_unique_query_select_statement_exception_message);
$user_unique_query_select_statement->setExceptionMessage($user_unique_query_select_statement_exception_message);
$user_unique_query_select_statement->setExpect(0)->setHideResponse(true);

$check_user_constrain = new User();
$check_user_constrain_fields = $check_user_constrain->getFields();
foreach ($check_user_constrain_fields as $field) $field->setProtected(true);

$check_user_constrain->getField('email')->setRequired(true)->setProtected(false);
$check_user_constrain->setFromAssociative((array)Request::post());
$check_user_constrain_warnings = $check_user_constrain->checkRequired()->getAllFieldsWarning();

if (!!$errors = array_merge($user_child_warnings, $check_user_constrain_warnings)) {
    Language::dictionary(__file__);
    $notice = __namespace__ . '\\' .'create'.'\\'. 'notice';
    $notice = Language::translate($notice);
    Output::concatenate('notice', $notice);
    Output::concatenate('errors', $errors);
    Output::print(false);
}

$user_child_type = $user_child->getField('type')->getValue();
$user_child_password = password();
if (User::OAUTH !== $user_child_type) $user_child->getField('password')->setSafeModeDetached(false)->setValue($user_child_password);

$check_user_constrain_query = ArangoDB::start($check_user_constrain);
$check_user_constrain_query_select = $check_user_constrain_query->select();
$check_user_constrain_query_select->getLimit()->set(1);
$check_user_constrain_query_select_return = 'RETURN 1';
$check_user_constrain_query_select->getReturn()->setPlain($check_user_constrain_query_select_return);
$check_user_constrain_query_select_statement = $check_user_constrain_query_select->getStatement();
$check_user_constrain_query_select_statement_exception_message = $exception_message . 'constrain';
$check_user_constrain_query_select_statement_exception_message = Language::translate($check_user_constrain_query_select_statement_exception_message);
$check_user_constrain_query_select_statement->setExceptionMessage($check_user_constrain_query_select_statement_exception_message);
$check_user_constrain_query_select_statement->setExpect(0)->setHideResponse(true);

$management = [];

$user_remove_hash = clone $user_child;
$user_remove_hash_fields = $user_remove_hash->getFields();
foreach ($user_remove_hash_fields as $field) $field->setRequired(true);

$user_remove_hash->getField('hash')->setRequired(false);
$user_remove_hash_query = ArangoDB::start($user_remove_hash);
$user_remove_hash_query->setUseAdapter(false);
$user_remove_hash_query_update = $user_remove_hash_query->update();
$user_remove_hash_query_update->setReplace(true);

foreach (Vertex::MANAGEMENT as $field_name) {
    $user_remove_hash_field_name = Update::SEARCH . chr(46) . $field_name;
    $user_remove_hash->getField($field_name)->setSafeModeDetached(false)->setValue($user_remove_hash_field_name);
    array_push($management, $user_remove_hash_field_name);
}

$user_remove_hash_query_update->pushStatementSkipValues(...$management);
$user_remove_hash_query_update_transaction = $user_remove_hash_query_update->getTransaction();

$user_query_insert = $user_query->insert();
$user_query_insert->pushEntitySkips($user);
$user_query_insert->pushStatementsPreliminary($user_query_select_statement, $check_user_constrain_query_select_statement, $user_unique_query_select_statement);
$user_query_insert->pushTransactionsFinal($user_remove_hash_query_update_transaction);
$user_query_insert_return = 'RETURN' . chr(32) . Handling::RNEW;
$user_query_insert->getReturn()->setPlain($user_query_insert_return);
$user_query_insert->setEntityEnableReturns($user_child);
$user_query_insert_response = $user_query_insert->run();
if (null === $user_query_insert_response
    || empty($user_query_insert_response)) Output::print(false);

if (User::SERVICE !== $user_child_type) {
    Language::setSpeech($user_child->getField('language')->getValue());

    $user_query_insert_values = $user_child->getAllFieldsValues(false, false);
    $user_query_insert_values['password'] = User::OAUTH === $user_child_type
        ? Language::translate(__namespace__ . '\\' . 'email' . '\\' . 'oauth')
        : $user_child_password;
    $user_query_insert_values_exception = __namespace__ . '\\' . 'email';
    $user_query_insert_values += Language::getTextsNamespaceName($user_query_insert_values_exception);

    $email = new Mail();
    $email->enableBypassListManagement();
    $email->setFrom('user@management.energia-europa.com', 'Identity and Access Management');
    $email->setTemplateId('d-4bd98771ed0c4adb9185fa1c756ceeaf');
    $email->addTo($user_query_insert_values['email'], $user_query_insert_values['firstname'] . chr(32) . $user_query_insert_values['lastname'], $user_query_insert_values, 0);

    try {
        $sendgrid = new SendGrid(Configuration::KEY);
        $response = $sendgrid->send($email);
        Output::concatenate('email', 202 === $response->statusCode());
    } catch (Exception $exception) {
        Output::concatenate('errors', $exception->getMessage());
        Output::print(false);
    }
}

$registered = new User();
$registered->setSafeMode(false)->setReadMode(true);
$registered_value = reset($registered_query_insert_response);
$registered->setFromAssociative($registered_value, $registered_value);
$registered_value = $registered->getAllFieldsValues(false, false);

$user = new User();
$user->getField('email')->setProtected(false)->setRequired(true)->setValue(User::ROOT);

Navigator::setImpersonate(Navigator::ENABLE);
Session::generate($user, 60);
IAMRequest::instance(Session::getAuthorization());

$assign_api = 'iam/user/assignment' . chr(47) . $registered->getField(User::KEY)->getValue();
foreach (AUTOASSIGN as $type => $value) {
    $invoke = preg_filter('/^.*$/', $type . chr(47) . '$0', $value);
    foreach ($invoke as $item)
        Gateway::callAPI(IAMConfiguration::getApplicationBasename(), $assign_api, array(
            Vertex::ID => $item
        ));
}

Output::concatenate(Output::APIDATA, $registered_value);
Output::print(true);
