<?PHP

namespace applications\sso\application\actions;

use Knight\armor\Output;
use Knight\armor\Language;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\entity\Vertex;
use ArangoDB\entity\common\Arango;

use applications\iam\user\database\Vertex as User;
use applications\iam\policy\database\Vertex as Policy;
use applications\sso\application\database\Vertex as Application;
use applications\sso\application\database\edges\ApplicationToUser;
use applications\sso\application\database\edges\ApplicationToPolicy;
use applications\sso\application\database\edges\ApplicationToServer;

use extensions\Navigator;

Policy::mandatories('sso/application/action/delete');

$follow_application_edges = new Application();
ArangoDB::start($follow_application_edges);
$follow_application_edges = $follow_application_edges->getAllUsableEdgesName(true);

const FOLLOW_EDGES_CHECK_AND_SKIP = array(
    ApplicationToPolicy::COLLECTION => 0,
    ApplicationToServer::COLLECTION => 0
);

Language::dictionary(__file__);
$exception_message = __namespace__ . '\\' . 'exception';

$user = User::Login();

$delete = parse_url($_SERVER[Navigator::REQUEST_URI], PHP_URL_PATH);
$delete = basename($delete);

$application = new Application();
$application_fields = $application->getFields();
foreach ($application_fields as $field) $field->setProtected(true);

$application->getField(Vertex::OWNER)->setProtected(false)->setRequired(true)->setValue($user->getField(Arango::KEY)->getValue());
$application->getField(Arango::KEY)->setProtected(false)->setRequired(true)->setValue($delete);

if (!!$errors = $application->checkRequired()->getAllFieldsWarning()) {
    Language::dictionary(__file__);
    $notice = __namespace__ . '\\' . 'notice';
    $notice = Language::translate($notice);
    Output::concatenate('notice', $notice);
    Output::concatenate('errors', $errors);
    Output::print(false);
}

$application_query = ArangoDB::start($application);

$application->useEdge(ApplicationToUser::getName())->vertex($user);
$application_to_user_vertex_query_select = $application_query->select();
$application_to_user_vertex_query_select->getLimit()->set(1);
$application_to_user_vertex_query_select_return = 'RETURN 1';
$application_to_user_vertex_query_select->getReturn()->setPlain($application_to_user_vertex_query_select_return);
$application_to_user_vertex_query_statement = $application_to_user_vertex_query_select->getStatement();
$application_to_user_vertex_query_statement_exception_message = Language::translate($exception_message, $delete);
$application_to_user_vertex_query_statement->setExceptionMessage($application_to_user_vertex_query_statement_exception_message);
$application_to_user_vertex_query_statement->setExpect(1)->setHideResponse(true);
$application->getContainer()->removeEdgesByName(ApplicationToUser::getName());

$application_to_check_edge_select_query_clone = clone $application;
$application_to_check_edge_select_query = ArangoDB::start($application_to_check_edge_select_query_clone);

$application_query_delete = $application_query->remove();
$application_query_delete->pushStatementsPreliminary($application_to_user_vertex_query_statement);
foreach ($follow_application_edges as $edge_name) if (array_key_exists($edge_name, FOLLOW_EDGES_CHECK_AND_SKIP)) {
    $application_to_check_edge_select_query_clone->useEdge($edge_name);
    $application_to_check_edge_select = $application_to_check_edge_select_query->select();
    $application_to_check_edge_select_limit = FOLLOW_EDGES_CHECK_AND_SKIP[$edge_name] + 1;
    $application_to_check_edge_select->getLimit()->set($application_to_check_edge_select_limit);
    $application_to_check_edge_select_statement = $application_to_check_edge_select->getStatement();
    $application_to_check_edge_select_statement_exception_message = Language::translate($exception_message . '\\' . 'check', $edge_name, $delete);
    $application_to_check_edge_select_statement->setExceptionMessage($application_to_check_edge_select_statement_exception_message);
    $application_to_check_edge_select_statement->setExpect(FOLLOW_EDGES_CHECK_AND_SKIP[$edge_name])->setHideResponse(true);

    $application_query_delete->pushStatementsPreliminary($application_to_check_edge_select_statement);

    $application_to_check_edge_select_query_clone->getContainer()->removeEdgesByName($edge_name);    
} else {
    $application_query_delete->pushEntitySkips($application->useEdge($edge_name)->vertex());
}

$application_query_delete_response = $application_query_delete->run();
if (null !== $application_query_delete_response) Output::print(true);
Output::print(false);
