<?PHP

namespace applications\sso\server\actions;

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
use applications\iam\user\database\edges\UserToServer;
use applications\iam\policy\database\Vertex as Policy;
use applications\sso\server\database\Vertex as Server;
use applications\sso\server\database\edges\ServerToApplication;
use applications\sso\application\database\edges\ApplicationToUser;
use applications\sso\server\forms\Matrioska;

use extensions\Navigator;

Policy::mandatories('sso/server/action/create');

$user = User::login();
$user_query = ArangoDB::start($user);

$user_query_select = $user_query->select();
$user_query_select->getLimit()->set(1);
$user_query_select_return = 'RETURN 1';
$user_query_select->getReturn()->setPlain($user_query_select_return);
$user_query_select_statement = $user_query_select->getStatement();
$user_query_select_statement->setExpect(1)->setHideResponse(true);

$server = $user->useEdge(UserToServer::getName())->vertex();
$server->setFromAssociative((array)Request::post());

foreach (Vertex::MANAGEMENT as $field_name) $server->getField($field_name)->setProtected(false)->setRequired(true)->setValue($user->getField(Arango::KEY)->getValue());

$server_fields_values = $server->getAllFieldsValues();
$server_fields_values = serialize($server_fields_values) . microtime(true) . Navigator::getFingerprint();
$server_fields_values = hash('sha512', $server_fields_values);

$server_field_hash = $server->addField('hash');
$server_field_hash_pattern = Validation::factory('ShowString');
$server_field_hash->setPatterns($server_field_hash_pattern);
$server_field_hash->addUniqueness();

$server_field_hash->setProtected(false)->setRequired(true);
$server_field_hash->setValue($server_fields_values);

Language::dictionary(__file__);
$exception_message = __namespace__ . '\\' . 'exception' . '\\';

$server_unique = new Server();
$server_unique->addFieldClone($server_field_hash);
$server_unique_field_hash = $server_unique->getField($server_field_hash->getName());
$server_unique_field_hash->setProtected(false);
$server_unique_field_hash->setValue($server_fields_values);

$server_unique_query = ArangoDB::start($server_unique);
$server_unique_query_select = $server_unique_query->select();
$server_unique_query_select->getLimit()->set(1);
$server_unique_query_select_return = 'RETURN 1';
$server_unique_query_select->getReturn()->setPlain($server_unique_query_select_return);
$server_unique_query_select_statement = $server_unique_query_select->getStatement();
$server_unique_query_select_statement_exception_message = $exception_message . 'hash';
$server_unique_query_select_statement_exception_message = Language::translate($server_unique_query_select_statement_exception_message);
$server_unique_query_select_statement->setExceptionMessage($server_unique_query_select_statement_exception_message);
$server_unique_query_select_statement->setExpect(0)->setHideResponse(true);

$server_warnings = $server->checkRequired()->getAllFieldsWarning();

$skip = [];
$preliminary = [];

$matrioska = new Matrioska();
$matrioska->setFromAssociative((array)Request::post());

if (false === $matrioska->getField('application')->isDefault()) {
    $assign = $matrioska->getField('application')->getValue();
    foreach ($assign as $destination) if (empty($destination->checkRequired()->getAllFieldsWarning())) {
        $application = $server->useEdge(ServerToApplication::getName())->vertex($destination);

        $application_query = ArangoDB::start($application);
        $application->useEdge(ApplicationToUser::getName())->vertex($user);

        $application_query_select = $application_query->select();
        $application_query_select->getLimit()->set(1);
        $application_query_select_return = 'RETURN 1';
        $application_query_select->getReturn()->setPlain($application_query_select_return);
        $application_query_select_statement = $application_query_select->getStatement();
        $application_query_select_statement_exception_message = $exception_message . 'constrain';
        $application_query_select_exception_message = Language::translate($application_query_select_statement_exception_message);
        $application_query_select_statement->setExceptionMessage($application_query_select_exception_message);
        $application_query_select_statement->setExpect(1)->setHideResponse(true);
        $application->getContainer()->removeEdgesByName(ApplicationToUser::getName());

        array_push($preliminary, $application_query_select_statement);
        array_push($skip, $application);
    }
}

$matrioska_warnings = $matrioska->checkRequired()->getAllFieldsWarning();
if (!!$errors = array_merge($server_warnings, $matrioska_warnings)) {
    Language::dictionary(__file__);
    $notice = __namespace__ . '\\' . 'notice';
    $notice = Language::translate($notice);
    Output::concatenate('notice', $notice);
    Output::concatenate('errors', $errors);
    Output::print(false);
}

$management = [];

$server_remove_hash = clone $server;
$server_remove_hash_fields = $server_remove_hash->getFields();
foreach ($server_remove_hash_fields as $field) $field->setRequired(true);
$server_remove_hash->getField('hash')->setRequired(false);
$server_remove_hash_query = ArangoDB::start($server_remove_hash);
$server_remove_hash_query->setUseAdapter(false);
$server_remove_hash_query_update = $server_remove_hash_query->update();
$server_remove_hash_query_update->setReplace(true);

foreach (Vertex::MANAGEMENT as $field_name) {
    $server_remove_hash_field_name = Update::SEARCH . chr(46) . $field_name;
    $server_remove_hash->getField($field_name)->setSafeModeDetached(false)->setValue($server_remove_hash_field_name);
    array_push($management, $server_remove_hash_field_name);
}

$server_remove_hash_query_update->pushStatementSkipValues(...$management);
$server_remove_hash_query_update_transaction = $server_remove_hash_query_update->getTransaction();

$query_insert = $user_query->insert();
$query_insert->pushEntitySkips($user, ...$skip);
$query_insert->pushStatementsPreliminary($user_query_select_statement, $server_unique_query_select_statement, ...$preliminary);
$query_insert->pushTransactionsFinal($server_remove_hash_query_update_transaction);
$query_insert_return = 'RETURN' . chr(32) . Handling::RNEW;
$query_insert->getReturn()->setPlain($query_insert_return);
$query_insert->setEntityEnableReturns($server);
$query_insert_response = $query_insert->run();
if (null === $query_insert_response
    || empty($query_insert_response)) Output::print(false);

$server = new Server();
$server->setSafeMode(false)->setReadMode(true);
$server_value = reset($query_insert_response);
$server->setFromAssociative($server_value, $server_value);
$server_value = $server->getAllFieldsValues(false, false);
Output::concatenate(Output::APIDATA, $server_value);
Output::print(true);
