<?PHP

namespace applications\sso\application\actions;

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
use applications\iam\user\database\edges\UserToUser;
use applications\iam\user\database\edges\UserToApplication;
use applications\iam\policy\database\Vertex as Policy;
use applications\sso\application\database\Vertex as Application;
use applications\sso\application\forms\Matrioska;
use applications\sso\application\database\edges\ApplicationToUser;
use applications\sso\application\database\edges\ApplicationToCluster;

use extensions\Navigator;

Policy::mandatories('sso/application/action/create');

$user = User::login();
$user_query = ArangoDB::start($user);
$user_query_select = $user_query->select();
$user_query_select->getLimit()->set(1);
$user_query_select_return = 'RETURN 1';
$user_query_select->getReturn()->setPlain($user_query_select_return);
$user_query_select_statement = $user_query_select->getStatement();
$user_query_select_statement->setExpect(1)->setHideResponse(true);

$application = $user->useEdge(UserToApplication::getName())->vertex();
$application_uploads = array_column($_FILES, 'tmp_name');
$application_uploads_keys = array_keys($_FILES);
$application_uploads = array_combine($application_uploads_keys, $application_uploads);
$application->setFromAssociative((array)Request::post(), $application_uploads);

foreach (Vertex::MANAGEMENT as $field_name) $application->getField($field_name)->setProtected(false)->setRequired(true)->setValue($user->getField(Arango::KEY)->getValue());

$skip = [];
$preliminary = [];

Language::dictionary(__file__);
$exception_message = __namespace__ . '\\' . 'exception' . '\\';

$matrioska = new Matrioska();
$matrioska->setFromAssociative((array)Request::post());

if (false === $matrioska->getField('share')->isDefault()) {
    $assign = $matrioska->getField('share')->getValue();
    foreach ($assign as $destination) if (empty($destination->checkRequired()->getAllFieldsWarning())) {
        $share = $application->useEdge(ApplicationToUser::getName())->vertex($destination);

        $check_user = User::login();
        $check_user_query = ArangoDB::start($check_user);
        $check_user_to_user = $check_user->useEdge(UserToUser::getName());
        $check_user_to_user->vertex($share);
        $check_user_to_user->branch()->vertex()->useEdge(UserToUser::getName(), $check_user_to_user);

        $check_select = $check_user_query->select();
        $check_select->getLimit()->set(1);
        $check_select_return = 'RETURN 1';
        $check_select->getReturn()->setPlain($check_select_return);
        $check_select_statement = $check_select->getStatement();
        $check_select_statement_exception_message = Language::translate($exception_message . 'share', $destination->getField(Arango::KEY)->getValue());
        $check_select_statement->setExceptionMessage($check_select_statement_exception_message);
        $check_select_statement->setExpect(1)->setHideResponse(true);

        array_push($preliminary, $check_select_statement);
        array_push($skip, $share);
    }
}

$matrioska->getField('cluster')->setRequired(true);
$matrioska_warnings = $matrioska->checkRequired()->getAllFieldsWarning();

$cluster = $application->useEdge(ApplicationToCluster::getName())->vertex($matrioska->getField('cluster')->getValue());
$cluster_query = ArangoDB::start($cluster);
$cluster_select = $cluster_query->select();
$cluster_select->getLimit()->set(1);
$cluster_select_return = 'RETURN 1';
$cluster_select->getReturn()->setPlain($cluster_select_return);
$cluster_select_statement = $cluster_select->getStatement();
$cluster_select_statement->setExpect(1)->setHideResponse(true);

$application_field_hash = $application->addField('hash');
$application_field_hash_pattern = Validation::factory('ShowString');
$application_field_hash->setPatterns($application_field_hash_pattern);
$application_field_hash->addUniqueness();
$application_field_hash->setProtected(false)->setRequired(true);
$application_field_hash_value = $application->getAllFieldsValues();
$application_field_hash_value = serialize($application_field_hash_value) . microtime(true) . Navigator::getFingerprint();
$application_field_hash_value = hash('sha512', $application_field_hash_value);
$application_field_hash->setValue($application_field_hash_value);

$application_unique = new Application();
$application_unique->addFieldClone($application_field_hash);
$application_unique_field_hash = $application_unique->getField($application_field_hash->getName());
$application_unique_field_hash->setProtected(false)->setValue($application_field_hash_value);

$application_field_hash_query = ArangoDB::start($application_unique);
$application_field_hash_query_select = $application_field_hash_query->select();
$application_field_hash_query_select->getLimit()->set(1);
$application_field_hash_query_select_return = 'RETURN 1';
$application_field_hash_query_select->getReturn()->setPlain($application_field_hash_query_select_return);
$application_field_hash_query_select_statement = $application_field_hash_query_select->getStatement();
$application_field_hash_query_select_statement_exception_message = $exception_message . 'hash';
$application_field_hash_query_select_statement_exception_message = Language::translate($application_field_hash_query_select_statement_exception_message);
$application_field_hash_query_select_statement->setExceptionMessage($application_field_hash_query_select_statement_exception_message);
$application_field_hash_query_select_statement->setExpect(0)->setHideResponse(true);

$application_field_basename_value = $application->getField('basename')->getValue();
if (Application::checkLocalExists($application_field_basename_value)) $application->getField('link')->setProtected(true);

$application_warnings = $application->checkRequired(true)->getAllFieldsWarning();

$check_application = new Application();
$check_application_fields = $application_unique->getFields();
foreach ($check_application_fields as $field) $field->setProtected(true); 

$check_application->getField('basename')->setProtected(false)->setRequired(true);
$check_application->setFromAssociative((array)Request::post());
$check_application_warnings = $check_application->checkRequired()->getAllFieldsWarning();

if (!!$errors = array_merge($application_warnings, $matrioska_warnings, $check_application_warnings)) {
    Language::dictionary(__file__);
    $notice = __namespace__ . '\\' . 'notice';
    $notice = Language::translate($notice);
    Output::concatenate('notice', $notice);
    Output::concatenate('errors', $errors);
    Output::print(false);
}

$check_application_query = ArangoDB::start($check_application);
$check_application_query_select = $check_application_query->select();
$check_application_query_select->getLimit()->set(1);
$check_application_query_select_return = 'RETURN 1';
$check_application_query_select->getReturn()->setPlain($check_application_query_select_return);
$check_application_query_select_statement_constrain = $check_application_query_select->getStatement();
$check_application_query_select_statement_constrain_exception_message = $exception_message . 'constrain';
$check_application_query_select_statement_constrain_exception_message = Language::translate($check_application_query_select_statement_constrain_exception_message);
$check_application_query_select_statement_constrain->setExceptionMessage($check_application_query_select_statement_constrain_exception_message);
$check_application_query_select_statement_constrain->setExpect(0)->setHideResponse(true);

$management = [];

$application_remove_hash = clone $application;
$application_remove_hash_fields = $application_remove_hash->getFields();
foreach ($application_remove_hash_fields as $field) $field->setRequired(true);
$application_remove_hash->getField('hash')->setRequired(false);
$application_remove_hash_query = ArangoDB::start($application_remove_hash);
$application_remove_hash_query->setUseAdapter(false);
$application_remove_hash_query_update = $application_remove_hash_query->update();
$application_remove_hash_query_update->setReplace(true);

foreach (Vertex::MANAGEMENT as $field_name) {
    $application_remove_hash_field_name = Update::SEARCH . chr(46) . $field_name;
    $application_remove_hash->getField($field_name)->setSafeModeDetached(false)->setValue($application_remove_hash_field_name);
    array_push($management, $application_remove_hash_field_name);
}

$application_remove_hash_query_update->pushStatementSkipValues(...$management);
$application_remove_hash_query_update_transaction = $application_remove_hash_query_update->getTransaction();

$user_query_insert = $user_query->insert();
$user_query_insert->pushEntitySkips($user, $cluster, ...$skip);
$user_query_insert->pushStatementsPreliminary($user_query_select_statement, $check_application_query_select_statement_constrain, $application_field_hash_query_select_statement, $cluster_select_statement, ...$preliminary);
$user_query_insert->pushTransactionsFinal($application_remove_hash_query_update_transaction);
$user_query_insert_return = 'RETURN' . chr(32) . Handling::RNEW;
$user_query_insert->getReturn()->setPlain($user_query_insert_return);
$user_query_insert->setEntityEnableReturns($application);
$user_query_insert_response = $user_query_insert->run();
if (null === $user_query_insert_response
    || empty($user_query_insert_response)) Output::print(false);

$application = new Application();
$application->setSafeMode(false)->setReadMode(true);
$application_value = reset($user_query_insert_response);
$application->setFromAssociative($application_value, $application_value);
$application_value = $application->getAllFieldsValues(false, false);
Output::concatenate(Output::APIDATA, $application_value);
Output::print(true);
