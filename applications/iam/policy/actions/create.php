<?PHP

namespace applications\iam\policy\actions;

use Knight\armor\Output;
use Knight\armor\Request;
use Knight\armor\Language;

use Entity\Validation;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\operations\Update;
use ArangoDB\operations\common\Choose;
use ArangoDB\entity\Vertex;
use ArangoDB\entity\common\Arango;
use ArangoDB\operations\common\Handling;

use applications\iam\user\database\Vertex as User;
use applications\iam\user\database\edges\UserToApplication;
use applications\iam\policy\database\Vertex as Policy;
use applications\iam\policy\forms\Matrioska;
use applications\iam\policy\database\edges\PolicyToApplication;
use applications\sso\application\database\edges\ApplicationToPolicy;

use extensions\Navigator;

Policy::mandatories('iam/policy/action/create');

$user = User::login();
$user_query = ArangoDB::start($user);

$user_to_application = $user->useEdge(UserToApplication::getName());

$matrioska = new Matrioska();
$matrioska->setFromAssociative((array)Request::post());

if (false === $matrioska->getField('application')->isDefault()) $user_to_application->vertex($matrioska->getField('application')->getValue());

$matrioska_warnings = $matrioska->checkRequired()->getAllFieldsWarning();

$check_user_select = $user_query->select();
$check_user_select->getLimit()->set(1);
$check_user_select_return = 'RETURN 1';
$check_user_select->getReturn()->setPlain($check_user_select_return);
$check_user_select_statement = $check_user_select->getStatement();
$check_user_select_statement->setExpect(1)->setHideResponse(true);

$application = $user_to_application->vertex();
$application_basename = ArangoDB::start($application);
$application_basename_select = $application_basename->select();
$application_basename_select->useWith(false);
$application_basename_select_return = $application_basename_select->getPointer(Choose::VERTEX);
$application_basename_select_return = 'RETURN' . chr(32) . $application_basename_select_return . chr(46) . $application->getField('basename')->getName();
$application_basename_select->getReturn()->setPlain($application_basename_select_return);
$application_basename_select->getLimit()->set(1);
$application_basename_select_statement = $application_basename_select->getStatement();

$policy = $application->useEdge(ApplicationToPolicy::getName())->vertex();
$policy->setFromAssociative((array)Request::post());

foreach (Vertex::MANAGEMENT as $field_name) $policy->getField($field_name)->setProtected(false)->setRequired(true)->setValue($user->getField(Arango::KEY)->getValue());

$policy_fields_values = $policy->getAllFieldsValues();
$policy_fields_values = serialize($policy_fields_values) . microtime(true) . Navigator::getFingerprint();
$policy_fields_values = hash('sha512', $policy_fields_values);

$policy_field_hash = $policy->addField('hash');
$policy_field_hash_pattern = Validation::factory('ShowString');
$policy_field_hash->setPatterns($policy_field_hash_pattern);
$policy_field_hash->addUniqueness();
$policy_field_hash->setProtected(false)->setRequired(true);
$policy_field_hash->setValue($policy_fields_values);

Language::dictionary(__file__);
$exception_message = __namespace__ . '\\' . 'exception' . '\\';

$policy_unique = new Policy();
$policy_unique->addFieldClone($policy_field_hash);
$policy_unique_field_hash = $policy_unique->getField($policy_field_hash->getName());
$policy_unique_field_hash->setProtected(false);
$policy_unique_field_hash->setValue($policy_fields_values);

$policy_unique_query = ArangoDB::start($policy_unique);

$policy_unique_query_select = $policy_unique_query->select();
$policy_unique_query_select->getLimit()->set(1);
$policy_unique_query_select_return = 'RETURN 1';
$policy_unique_query_select->getReturn()->setPlain($policy_unique_query_select_return);
$policy_unique_query_select_statement = $policy_unique_query_select->getStatement();
$policy_unique_query_select_statement_exception_message = $exception_message . 'hash';
$policy_unique_query_select_statement_exception_message = Language::translate($policy_unique_query_select_statement_exception_message);
$policy_unique_query_select_statement->setExceptionMessage($policy_unique_query_select_statement_exception_message);
$policy_unique_query_select_statement->setExpect(0)->setHideResponse(true);

$policy_warnings = $policy->checkRequired()->getAllFieldsWarning();

if (!!$errors = array_merge($matrioska_warnings, $policy_warnings)) {
	Language::dictionary(__file__);
    $notice = __namespace__ . '\\' . 'notice';
    $notice = Language::translate($notice);
    Output::concatenate('notice', $notice);
    Output::concatenate('errors', $errors);
    Output::print(false);
}

$check_policy_constrain = new Policy();
$check_policy_constrain_fields = $check_policy_constrain->getFields();
foreach ($check_policy_constrain_fields as $field) $field->setProtected(true);

$check_policy_constrain->getField('route')->setProtected(false);
$check_policy_constrain->setFromAssociative((array)Request::post());

$check_policy_constrain_query = ArangoDB::start($check_policy_constrain);
$check_policy_constrain->useEdge(PolicyToApplication::getName())->vertex($matrioska->getField('application')->getValue());
$check_policy_constrain_query_select = $check_policy_constrain_query->select();
$check_policy_constrain_query_select->getLimit()->set(1);
$check_policy_constrain_query_select_return = 'RETURN 1';
$check_policy_constrain_query_select->getReturn()->setPlain($check_policy_constrain_query_select_return);
$check_policy_constrain_query_select_statement = $check_policy_constrain_query_select->getStatement();
$check_policy_constrain_query_select_statement_exception_message = $exception_message . 'constrain';
$check_policy_constrain_query_select_statement_exception_message = Language::translate($check_policy_constrain_query_select_statement_exception_message);
$check_policy_constrain_query_select_statement->setExceptionMessage($check_policy_constrain_query_select_statement_exception_message);
$check_policy_constrain_query_select_statement->setExpect(0)->setHideResponse(true);

$management = [];

$policy_remove_hash = clone $policy;
$policy_remove_hash_fields = $policy_remove_hash->getFields();
foreach ($policy_remove_hash_fields as $field) $field->setRequired(true);
$policy_remove_hash->getField('hash')->setRequired(false);
$policy_remove_hash_query = ArangoDB::start($policy_remove_hash);
$policy_remove_hash_query->setUseAdapter(false);
$policy_remove_hash_query_update = $policy_remove_hash_query->update();
$policy_remove_hash_query_update->setReplace(true);

$policy_remove_hash_cache = 'FIRST' . chr(40) . $application_basename_select_statement->getQuery() . chr(41);
$policy_remove_hash->getField(Policy::CACHE)->setProtected(false)->setValue($policy_remove_hash_cache);
array_push($management, $policy_remove_hash_cache);

foreach (Vertex::MANAGEMENT as $field_name) {
    $policy_remove_hash_name = Update::SEARCH . chr(46) . $field_name;
    $policy_remove_hash->getField($field_name)->setSafeModeDetached(false)->setValue($policy_remove_hash_name);
    array_push($management, $policy_remove_hash_name);
}

$policy_remove_hash_query_update->pushStatementSkipValues(...$management);
$policy_remove_hash_query_update_transaction = $policy_remove_hash_query_update->getTransaction();
$policy_remove_hash_query_update_transaction_statement = $policy_remove_hash_query_update_transaction->getStatements();
$policy_remove_hash_query_update_transaction_statement = reset($policy_remove_hash_query_update_transaction_statement);
$policy_remove_hash_query_update_transaction_statement->addBindFromStatements($application_basename_select_statement);

$application_query = ArangoDB::start($application);
$application_query_insert = $application_query->insert();
$application_query_insert->pushEntitySkips($user, $application);
$application_query_insert->pushStatementsPreliminary($check_user_select_statement, $check_policy_constrain_query_select_statement, $policy_unique_query_select_statement);
$application_query_insert->pushTransactionsFinal($policy_remove_hash_query_update_transaction);
$application_query_insert_return = 'RETURN' . chr(32) . Handling::RNEW;
$application_query_insert->getReturn()->setPlain($application_query_insert_return);
$application_query_insert->setEntityEnableReturns($policy);
$application_query_insert_response = $application_query_insert->run();
if (null === $application_query_insert_response
    || empty($application_query_insert_response)) Output::print(false);

$policy = new Policy();
$policy->setSafeMode(false)->setReadMode(true);
$policy_value = reset($application_query_insert_response);
$policy->setFromAssociative($policy_value, $policy_value);
$policy_value = $policy->getAllFieldsValues(false, false);
Output::concatenate(Output::APIDATA, $policy_value);
Output::print(true);
