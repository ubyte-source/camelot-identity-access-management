<?PHP

namespace applications\sso\server\actions;

use Knight\armor\Output;
use Knight\armor\Language;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\entity\Vertex;
use ArangoDB\entity\common\Arango;

use applications\iam\user\database\Vertex as User;
use applications\iam\policy\database\Vertex as Policy;
use applications\sso\server\database\Vertex as Server;
use applications\sso\server\database\edges\ServerToUser;

use extensions\Navigator;

Policy::mandatories('sso/server/action/delete');

$follow_server_edges = new Server();
ArangoDB::start($follow_server_edges);
$follow_server_edges = $follow_server_edges->getAllUsableEdgesName(true);

Language::dictionary(__file__);
$exception_message = __namespace__ . '\\' . 'exception';

$user = User::Login();

$delete = parse_url($_SERVER[Navigator::REQUEST_URI], PHP_URL_PATH);
$delete = basename($delete);

$server = new Server();
$server_fields = $server->getFields();
foreach ($server_fields as $field) $field->setProtected(true);

$server->getField(Vertex::OWNER)->setProtected(false)->setRequired(true)->setValue($user->getField(Arango::KEY)->getValue());
$server->getField(Arango::KEY)->setProtected(false)->setRequired(true)->setValue($delete);

if (!!$errors = $server->checkRequired()->getAllFieldsWarning()) {
    Language::dictionary(__file__);
    $notice = __namespace__ . '\\' . 'notice';
    $notice = Language::translate($notice);
    Output::concatenate('notice', $notice);
    Output::concatenate('errors', $errors);
    Output::print(false);
}

$server_query = ArangoDB::start($server);
$server->useEdge(ServerToUser::getName())->vertex($user);

$server_query_select = $server_query->select();
$server_query_select->getLimit()->set(1);
$server_query_select_return = 'RETURN 1';
$server_query_select->getReturn()->setPlain($server_query_select_return);
$server_query_select_statement = $server_query_select->getStatement();
$server_query_select_statement_exception_message = Language::translate($exception_message, $delete);
$server_query_select_statement->setExceptionMessage($server_query_select_statement_exception_message);
$server_query_select_statement->setExpect(1)->setHideResponse(true);
$server->getContainer()->removeEdgesByName(ServerToUser::getName());

$server_to_check_edge_select_query_clone = clone $server;
$server_to_check_edge_select_query = ArangoDB::start($server_to_check_edge_select_query_clone);

$server_query_delete = $server_query->remove();
$server_query_delete->pushStatementsPreliminary($server_query_select_statement);
foreach ($follow_server_edges as $edge_name) $server_query_delete->pushEntitySkips($server->useEdge($edge_name)->vertex());

$server_query_delete_response = $server_query_delete->run();
if (null !== $server_query_delete_response) Output::print(true);
Output::print(false);
