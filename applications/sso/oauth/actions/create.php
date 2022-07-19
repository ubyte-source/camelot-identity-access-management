<?PHP

namespace applications\sso\oauth\actions;

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
use applications\iam\user\database\edges\UserToOauth;
use applications\iam\policy\database\Vertex as Policy;
use applications\sso\oauth\database\Vertex as Oauth;

use extensions\Navigator;

Policy::mandatories('sso/oauth/action/create');

$user = User::login();
$user_query = ArangoDB::start($user);

$user_query_select = $user_query->select();
$user_query_select->getLimit()->set(1);
$user_query_select_return = 'RETURN 1';
$user_query_select->getReturn()->setPlain($user_query_select_return);
$user_query_select_statement = $user_query_select->getStatement();
$user_query_select_statement->setExpect(1)->setHideResponse(true);

$oauth = $user->useEdge(UserToOauth::getName())->vertex();
$oauth->setFromAssociative((array)Request::post());

foreach (Vertex::MANAGEMENT as $field_name) $oauth->getField($field_name)->setProtected(false)->setRequired(true)->setValue($user->getField(Arango::KEY)->getValue());

$oauth_fields_values = $oauth->getAllFieldsValues();
$oauth_fields_values = serialize($oauth_fields_values) . microtime(true) . Navigator::getFingerprint();
$oauth_fields_values = hash('sha512', $oauth_fields_values);

$oauth_field_hash = $oauth->addField('hash');
$oauth_field_hash_pattern = Validation::factory('ShowString');
$oauth_field_hash->setPatterns($oauth_field_hash_pattern);
$oauth_field_hash->addUniqueness();

$oauth_field_hash->setProtected(false)->setRequired(true);
$oauth_field_hash->setValue($oauth_fields_values);

Language::dictionary(__file__);
$exception_message = __namespace__ . '\\' . 'exception' . '\\';

$oauth_unique = new Oauth();
$oauth_unique->addFieldClone($oauth_field_hash);
$oauth_unique_field_hash = $oauth_unique->getField($oauth_field_hash->getName());
$oauth_unique_field_hash->setProtected(false);
$oauth_unique_field_hash->setValue($oauth_fields_values);

$oauth_unique_query = ArangoDB::start($oauth_unique);
$oauth_unique_query_select = $oauth_unique_query->select();
$oauth_unique_query_select->getLimit()->set(1);
$oauth_unique_query_select_return = 'RETURN 1';
$oauth_unique_query_select->getReturn()->setPlain($oauth_unique_query_select_return);
$oauth_unique_query_select_statement = $oauth_unique_query_select->getStatement();
$oauth_unique_query_select_statement_exception_message = $exception_message . 'hash';
$oauth_unique_query_select_statement_exception_message = Language::translate($oauth_unique_query_select_statement_exception_message);
$oauth_unique_query_select_statement->setExceptionMessage($oauth_unique_query_select_statement_exception_message);
$oauth_unique_query_select_statement->setExpect(0)->setHideResponse(true);

if (!!$errors = $oauth->checkRequired()->getAllFieldsWarning()) {
    Language::dictionary(__file__);
    $notice = __namespace__ . '\\' . 'notice';
    $notice = Language::translate($notice);
    Output::concatenate('notice', $notice);
    Output::concatenate('errors', $errors);
    Output::print(false);
}

$management = [];

$oauth_remove_hash = clone $oauth;
$oauth_remove_hash_fields = $oauth_remove_hash->getFields();
foreach ($oauth_remove_hash_fields as $field) $field->setRequired(true);
$oauth_remove_hash->getField('hash')->setRequired(false);
$oauth_remove_hash_query = ArangoDB::start($oauth_remove_hash);
$oauth_remove_hash_query->setUseAdapter(false);
$oauth_remove_hash_query_update = $oauth_remove_hash_query->update();
$oauth_remove_hash_query_update->setReplace(true);

foreach (Vertex::MANAGEMENT as $field_name) {
    $oauth_remove_hash_field_name = Update::SEARCH . chr(46) . $field_name;
    $oauth_remove_hash->getField($field_name)->setSafeModeDetached(false)->setValue($oauth_remove_hash_field_name);
    array_push($management, $oauth_remove_hash_field_name);
}

$oauth_remove_hash_query_update->pushStatementSkipValues(...$management);
$oauth_remove_hash_query_update_transaction = $oauth_remove_hash_query_update->getTransaction();

$query_insert = $user_query->insert();
$query_insert->pushEntitySkips($user);
$query_insert->pushStatementsPreliminary($user_query_select_statement, $oauth_unique_query_select_statement);
$query_insert->pushTransactionsFinal($oauth_remove_hash_query_update_transaction);
$query_insert_return = 'RETURN' . chr(32) . Handling::RNEW;
$query_insert->getReturn()->setPlain($query_insert_return);
$query_insert->setEntityEnableReturns($oauth);
$query_insert_response = $query_insert->run();
if (null === $query_insert_response
    || empty($query_insert_response)) Output::print(false);

$oauth = new Oauth();
$oauth->setSafeMode(false)->setReadMode(true);
$oauth_value = reset($query_insert_response);
$oauth->setFromAssociative($oauth_value, $oauth_value);
$oauth_value = $oauth->getAllFieldsValues(false, false);
Output::concatenate(Output::APIDATA, $oauth_value);
Output::print(true);
