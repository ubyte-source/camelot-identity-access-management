<?PHP

namespace applications\iam\group\actions;

use Knight\armor\Output;
use Knight\armor\Language;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\entity\Vertex;
use ArangoDB\entity\Edge;
use ArangoDB\entity\common\Arango;

use applications\iam\user\database\Vertex as User;
use applications\iam\group\database\Vertex as Group;
use applications\iam\group\database\edges\GroupToUser;
use applications\iam\group\database\edges\GroupToGroup;
use applications\iam\policy\database\Vertex as Policy;

use extensions\Navigator;

Policy::mandatories('iam/group/action/delete');

$follow_group_edges = new Group();
ArangoDB::start($follow_group_edges);
$follow_group_edges = $follow_group_edges->getAllUsableEdgesName(true);

Language::dictionary(__file__);
$exception_message = __namespace__ . '\\' . 'exception';

$user = User::Login();

$delete = parse_url($_SERVER[Navigator::REQUEST_URI], PHP_URL_PATH);
$delete = basename($delete);

$group = new Group();
$group_fields = $group->getFields();
foreach ($group_fields as $field) $field->setProtected(true);

$group->getField(Vertex::OWNER)->setProtected(false)->setRequired(true)->setValue($user->getField(Arango::KEY)->getValue());
$group->getField(Arango::KEY)->setProtected(false)->setRequired(true)->setValue($delete);

if (!!$errors = $group->checkRequired()->getAllFieldsWarning()) {
    Language::dictionary(__file__);
    $notice = __namespace__ . '\\' . 'notice';
    $notice = Language::translate($notice);
    Output::concatenate('notice', $notice);
    Output::concatenate('errors', $errors);
    Output::print(false);
}

$group_query = ArangoDB::start($group);

$group_to_user_vertex = $group->useEdge(GroupToUser::getName())->vertex($user);
$group_to_user_vertex_query = $group_query->select();
$group_to_user_vertex_query->getLimit()->set(1);
$group_to_user_vertex_query_return = 'RETURN 1';
$group_to_user_vertex_query->getReturn()->setPlain($group_to_user_vertex_query_return);
$group_to_user_vertex_query_statement = $group_to_user_vertex_query->getStatement();
$group_to_user_vertex_query_statement_exception_message = Language::translate($exception_message, $delete);
$group_to_user_vertex_query_statement->setExceptionMessage($group_to_user_vertex_query_statement_exception_message);
$group_to_user_vertex_query_statement->setExpect(1)->setHideResponse(true);
$group->getContainer()->removeEdgesByName(GroupToUser::getName());

$group_to_check_edge_query_clone = clone $group;
$group_to_check_edge_query = ArangoDB::start($group_to_check_edge_query_clone);

$group_query_delete = $group_query->remove();
$group_query_delete->pushStatementsPreliminary($group_to_user_vertex_query_statement);
foreach ($follow_group_edges as $edge_name) {
    $group_query_delete->pushEntitySkips($group->useEdge($edge_name)->vertex());
    if ($edge_name === GroupToGroup::getName()) $group_query_delete->pushEntitySkips($group->useEdge($edge_name)->setForceDirection(Edge::INBOUND)->vertex());
}

$group_query_delete_response = $group_query_delete->run();
if (null !== $group_query_delete_response) Output::print(true);
Output::print(false);
