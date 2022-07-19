<?PHP

namespace applications\iam\policy\actions;

use Knight\armor\Output;
use Knight\armor\Request;
use Knight\armor\Language;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\operations\Update;
use ArangoDB\operations\common\Choose;
use ArangoDB\entity\Vertex;
use ArangoDB\entity\common\Arango;
use ArangoDB\operations\common\Handling;

use applications\iam\user\database\Vertex as User;
use applications\iam\user\database\edges\UserToApplication;
use applications\iam\policy\database\Vertex as Policy;
use applications\iam\policy\database\edges\PolicyToApplication;
use applications\iam\policy\forms\Matrioska;
use applications\sso\application\database\edges\ApplicationToPolicy;

use extensions\Navigator;

Policy::mandatories('iam/policy/action/update');

$policy_key_value = parse_url($_SERVER[Navigator::REQUEST_URI], PHP_URL_PATH);
$policy_key_value = basename($policy_key_value);

$user = User::login();
$user_query = ArangoDB::start($user);

$user_to_application = $user->useEdge(UserToApplication::getName());

$matrioska = new Matrioska();
$matrioska->setFromAssociative((array)Request::post());

if (false === $matrioska->getField('application')->isDefault()) $user_to_application->vertex($matrioska->getField('application')->getValue());

$matrioska_warnings = $matrioska->checkRequired()->getAllFieldsWarning();

$user_query_select = $user_query->select();
$user_query_select->getLimit()->set(1);
$user_query_select_return = 'RETURN 1';
$user_query_select->getReturn()->setPlain($user_query_select_return);
$user_query_select_statement = $user_query_select->getStatement();
$user_query_select_statement->setExpect(1)->setHideResponse(true);

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
$remove_policy = clone $policy;
$remove_policy->getField(Arango::KEY)->setProtected(false)->setValue($policy_key_value);

$remove_policy_query = ArangoDB::start($remove_policy);
$remove_policy->useEdge(PolicyToApplication::getName())->vertex();
$remove_policy_query = $remove_policy_query->remove();
$remove_policy_query->setActionOnlyEdges(true);
$remove_policy_query_transaction = $remove_policy_query->getTransaction();

$policy->setFromAssociative((array)Request::post());
$policy->getField(Arango::KEY)->setProtected(false)->setRequired(true)->setValue($policy_key_value);
$policy_warnings = $policy->checkRequired()->getAllFieldsWarning();

if (!!$errors = array_merge($matrioska_warnings, $policy_warnings)) {
	Language::dictionary(__file__);
    $notice = __namespace__ . '\\' . 'notice';
    $notice = Language::translate($notice);
    Output::concatenate('notice', $notice);
    Output::concatenate('errors', $errors);
    Output::print(false);
}

$management = [];

$policy_clone = clone $policy;
$policy_clone_fields = $policy_clone->getFields();
foreach ($policy_clone_fields as $field) $field->setRequired(true);

$policy_clone_query = ArangoDB::start($policy_clone);
$policy_clone_query_update = $policy_clone_query->update();
$policy_clone_query_update->setReplace(true);
$policy_clone_query_update_return = 'RETURN' . chr(32) . Handling::RNEW;
$policy_clone_query_update->getReturn()->setPlain($policy_clone_query_update_return);
$policy_clone_query_update->setEntityEnableReturns($policy_clone);

$policy_clone_cache = 'FIRST' . chr(40) . $application_basename_select_statement->getQuery() . chr(41);
$policy_clone->getField(Policy::CACHE)->setProtected(false)->setValue($policy_clone_cache);
array_push($management, $policy_clone_cache);

foreach (Vertex::MANAGEMENT as $field_name) {
    $policy_clone_document_name = Update::SEARCH . chr(46) . $field_name;
    $policy_clone->getField($field_name)->setSafeModeDetached(false)->setRequired(true)->setValue($policy_clone_document_name);
    array_push($management, $policy_clone_document_name);
}

$policy_clone_query_update->pushStatementsPreliminary($user_query_select_statement);
$policy_clone_query_update->pushTransactionsPreliminary($remove_policy_query_transaction);
$policy_clone_query_update->pushStatementSkipValues(...$management);
$policy_clone_query_update_transaction = $policy_clone_query_update->getTransaction();
$policy_clone_query_update_transaction_statement = $policy_clone_query_update_transaction->getStatements();
$policy_clone_query_update_transaction_statement = reset($policy_clone_query_update_transaction_statement);
$policy_clone_query_update_transaction_statement->addBindFromStatements($application_basename_select_statement);

Language::dictionary(__file__);
$exception_message = __namespace__ . '\\' . 'exception' . '\\';

$check_constrain = new Policy();
$check_constrain_fields = $check_constrain->getFields();
foreach ($check_constrain_fields as $field) $field->setProtected(true);

$check_constrain->getField('route')->setProtected(false);
$check_constrain->setFromAssociative((array)Request::post());

$check_constrain_query = ArangoDB::start($check_constrain);
$check_constrain->useEdge(PolicyToApplication::getName())->vertex($matrioska->getField('application')->getValue());
$check_constrain_query_select = $check_constrain_query->select();
$check_constrain_query_select->getLimit()->set(2);
$check_constrain_query_select_statement = $check_constrain_query_select->getStatement();
$check_constrain_query_select_statement_exception_message = $exception_message . 'constrain';
$check_constrain_query_select_statement_exception_message = Language::translate($check_constrain_query_select_statement_exception_message);
$check_constrain_query_select_statement->setExceptionMessage($check_constrain_query_select_statement_exception_message);
$check_constrain_query_select_statement->setExpect(1)->setHideResponse(true);

$application_query = ArangoDB::start($application);
$application_query_insert = $application_query->insert();
$application_query_insert->setActionOnlyEdges(true);
$application_query_insert->pushTransactionsPreliminary($policy_clone_query_update_transaction);
$application_query_insert->pushStatementsFinal($check_constrain_query_select_statement);
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
