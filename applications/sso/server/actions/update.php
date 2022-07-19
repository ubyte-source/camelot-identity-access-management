<?PHP

namespace applications\sso\server\actions;

use Knight\armor\Output;
use Knight\armor\Request;
use Knight\armor\Language;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\operations\Update;
use ArangoDB\entity\Vertex;
use ArangoDB\entity\common\Arango;
use ArangoDB\operations\common\Handling;

use applications\iam\user\database\Vertex as User;
use applications\iam\user\database\edges\UserToServer;
use applications\iam\policy\database\Vertex as Policy;
use applications\sso\server\forms\Matrioska;
use applications\sso\server\database\Vertex as Server;
use applications\sso\server\database\edges\ServerToUser;
use applications\sso\server\database\edges\ServerToApplication;
use applications\sso\application\database\edges\ApplicationToUser;

use extensions\Navigator;

Policy::mandatories('sso/server/action/update');

$server_key_value = parse_url($_SERVER[Navigator::REQUEST_URI], PHP_URL_PATH);
$server_key_value = basename($server_key_value);

$user = User::Login();
$user_query = ArangoDB::start($user);

$server = $user->useEdge(UserToServer::getName())->vertex();
$server_check = clone $server;
$server_check_fields = $server_check->getFields();
foreach ($server_check_fields as $field) $field->setProtected(true);

$server_check->getField(Arango::KEY)->setProtected(false)->setRequired(true)->setValue($server_key_value);

$server_check_query = ArangoDB::start($server_check);
$server_check->useEdge(ServerToUser::getName())->vertex($user);
$server_check_query_select = $server_check_query->select();
$server_check_query_select->getLimit()->set(1);
$server_check_query_select_return = 'RETURN 1';
$server_check_query_select->getReturn()->setPlain($server_check_query_select_return);
$server_check_query_select_statement = $server_check_query_select->getStatement();
$server_check_query_select_statement->setExpect(1)->setHideResponse(true);

$server->setFromAssociative((array)Request::post());
$server->getField(Arango::KEY)->setProtected(false)->setRequired(true)->setValue($server_key_value);
$server_warnings = $server->checkRequired()->getAllFieldsWarning();

$management = [];

$server_clone = clone $server;
$server_clone_fields = $server_clone->getFields();
foreach ($server_clone_fields as $field) $field->setRequired(true);

$server_clone_query = ArangoDB::start($server_clone);
$server_clone_query_update = $server_clone_query->update();
$server_clone_query_update->setReplace(true);
$server_clone_query_update_return = 'RETURN' . chr(32) . Handling::RNEW;
$server_clone_query_update->getReturn()->setPlain($server_clone_query_update_return);
$server_clone_query_update->setEntityEnableReturns($server_clone);

foreach (Vertex::MANAGEMENT as $field_name) {
    $server_clone_field_name = Update::SEARCH . chr(46) . $field_name;
    $server_clone->getField($field_name)->setSafeModeDetached(false)->setRequired(true)->setValue($server_clone_field_name);
    array_push($management, $server_clone_field_name);
}

$server_clone_query_update->pushStatementsPreliminary($server_check_query_select_statement);
$server_clone_query_update->pushStatementSkipValues(...$management);
$server_clone_query_update_transaction = $server_clone_query_update->getTransaction();

$server->useEdge(ServerToApplication::getName())->vertex();
$remove_server_edges_query = ArangoDB::start($server);
$remove_server_edges = $remove_server_edges_query->remove();
$remove_server_edges->setActionOnlyEdges(true);
$remove_server_edges_transaction = $remove_server_edges->getTransaction();
$server->getContainer()->removeEdgesByName(ServerToApplication::getName());

Language::dictionary(__file__);
$exception_message = __namespace__ . '\\' . 'exception' . '\\';

$matrioska = new Matrioska();
$matrioska->setFromAssociative((array)Request::post());

$preliminary = [];

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

$server_query = ArangoDB::start($server);
$server_query_insert = $server_query->insert();
$server_query_insert->pushStatementsPreliminary(...$preliminary);
$server_query_insert->pushTransactionsPreliminary($remove_server_edges_transaction);
$server_query_insert->pushTransactionsFinal($server_clone_query_update_transaction);
$server_query_insert->setActionOnlyEdges(true);
$server_query_insert_response = $server_query_insert->run();
if (null === $server_query_insert_response
    || empty($server_query_insert_response)) Output::print(false);

$server = new Server();
$server->setSafeMode(false)->setReadMode(true);
$server_value = reset($server_query_insert_response);
$server->setFromAssociative($server_value, $server_value);
$server_value = $server->getAllFieldsValues(false, false);
Output::concatenate(Output::APIDATA, $server_value);
Output::print(true);
