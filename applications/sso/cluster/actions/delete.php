<?PHP

namespace applications\sso\cluster\actions;

use Knight\armor\Output;
use Knight\armor\Language;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\entity\Vertex;
use ArangoDB\entity\common\Arango;

use applications\iam\user\database\Vertex as User;
use applications\iam\policy\database\Vertex as Policy;
use applications\sso\cluster\database\Vertex as Cluster;
use applications\sso\cluster\database\edges\ClusterToUser;
use applications\sso\cluster\database\edges\ClusterToApplication;

use extensions\Navigator;

Policy::mandatories('sso/cluster/action/delete');

$follow_cluster_edges = new Cluster();
ArangoDB::start($follow_cluster_edges);
$follow_cluster_edges = $follow_cluster_edges->getAllUsableEdgesName(true);

const FOLLOW_EDGES_CHECK_AND_SKIP = array(
    ClusterToApplication::COLLECTION => 0
);

Language::dictionary(__file__);
$exception_message = __namespace__ . '\\' . 'exception';
$exception_message_check_and_skip = $exception_message . '\\' . 'check';

$user = User::Login();

$delete = parse_url($_SERVER[Navigator::REQUEST_URI], PHP_URL_PATH);
$delete = basename($delete);

$cluster = new Cluster();
$cluster_fields = $cluster->getFields();
foreach ($cluster_fields as $field) $field->setProtected(true);

$cluster->getField(Vertex::OWNER)->setProtected(false)->setRequired(true)->setValue($user->getField(Arango::KEY)->getValue());
$cluster->getField(Arango::KEY)->setProtected(false)->setRequired(true)->setValue($delete);

if (!!$errors = $cluster->checkRequired()->getAllFieldsWarning()) {
    Language::dictionary(__file__);
    $notice = __namespace__ . '\\' . 'notice';
    $notice = Language::translate($notice);
    Output::concatenate('notice', $notice);
    Output::concatenate('errors', $errors);
    Output::print(false);
}

$cluster_query = ArangoDB::start($cluster);

$cluster_to_user_vertex = $cluster->useEdge(ClusterToUser::getName())->vertex($user);
$cluster_to_user_vertex_query = $cluster_query->select();
$cluster_to_user_vertex_query->getLimit()->set(1);
$cluster_to_user_vertex_query_return = 'RETURN 1';
$cluster_to_user_vertex_query->getReturn()->setPlain($cluster_to_user_vertex_query_return);
$cluster_to_user_vertex_query_statement = $cluster_to_user_vertex_query->getStatement();
$cluster_to_user_vertex_query_statement_exception_message = Language::translate($exception_message, $delete);
$cluster_to_user_vertex_query_statement->setExceptionMessage($cluster_to_user_vertex_query_statement_exception_message);
$cluster_to_user_vertex_query_statement->setExpect(1)->setHideResponse(true);
$cluster->getContainer()->removeEdgesByName(ClusterToUser::getName());

$cluster_to_check_edge_select_query_clone = clone $cluster;
$cluster_to_check_edge_select_query = ArangoDB::start($cluster_to_check_edge_select_query_clone);

$cluster_query_delete = $cluster_query->remove();
$cluster_query_delete->pushStatementsPreliminary($cluster_to_user_vertex_query_statement);
foreach ($follow_cluster_edges as $edge_name) if (array_key_exists($edge_name, FOLLOW_EDGES_CHECK_AND_SKIP)) {
    $cluster_to_check_edge_select_query_clone->useEdge($edge_name);
    $cluster_to_check_edge_select = $cluster_to_check_edge_select_query->select();
    $cluster_to_check_edge_select_limit = FOLLOW_EDGES_CHECK_AND_SKIP[$edge_name] + 1;
    $cluster_to_check_edge_select->getLimit()->set($cluster_to_check_edge_select_limit);
    $cluster_to_check_edge_select_statement = $cluster_to_check_edge_select->getStatement();
    $cluster_to_check_edge_select_statement_exception_message = Language::translate($exception_message_check_and_skip, $edge_name, $delete);
    $cluster_to_check_edge_select_statement->setExceptionMessage($cluster_to_check_edge_select_statement_exception_message);
    $cluster_to_check_edge_select_statement->setExpect(FOLLOW_EDGES_CHECK_AND_SKIP[$edge_name])->setHideResponse(true);

    $cluster_query_delete->pushStatementsPreliminary($cluster_to_check_edge_select_statement);

    $cluster_to_check_edge_select_query_clone->getContainer()->removeEdgesByName($edge_name);
} else {
    $cluster_query_delete->pushEntitySkips($cluster->useEdge($edge_name)->vertex());
}

$cluster_query_delete_response = $cluster_query_delete->run();
if (null !== $cluster_query_delete_response) Output::print(true);
Output::print(false);
