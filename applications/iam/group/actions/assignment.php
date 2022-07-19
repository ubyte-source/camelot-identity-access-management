<?PHP

namespace applications\iam\group\actions;

use Knight\armor\Output;
use Knight\armor\Request;
use Knight\armor\Language;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\entity\Vertex;
use ArangoDB\entity\Edge;
use ArangoDB\entity\common\Arango;

use applications\iam\user\database\Vertex as User;
use applications\iam\user\database\edges\UserToGroup;
use applications\iam\user\database\edges\UserToApplication;
use applications\sso\application\database\edges\ApplicationToPolicy;
use applications\iam\group\database\Vertex as Group;
use applications\iam\group\database\edges\GroupToGroup;
use applications\iam\group\database\edges\GroupToPolicy;
use applications\iam\policy\database\Vertex as Policy;

use extensions\Navigator;

const TARGET_COLLECTION_ASSIGNMENT_ADMITTED = array(
    Policy::COLLECTION,
    Group::COLLECTION
);

Policy::mandatories('iam/group/action/assignment');

$user = User::login();
$user_query = ArangoDB::start($user);;

$target_id = Request::post($user->getField(Arango::ID)->getName());
$target_id = null !== $target_id ? explode(Arango::SEPARATOR, $target_id) : [];
$target_id_count = count($target_id);

if (2 !== $target_id_count) Output::print(false);

list($target_collection_name, $target_key) = $target_id;
if (!in_array($target_collection_name, TARGET_COLLECTION_ASSIGNMENT_ADMITTED)) Output::print(false);

$group_key_value = parse_url($_SERVER[Navigator::REQUEST_URI], PHP_URL_PATH);
$group_key_value = basename($group_key_value);

$user_to_group = $user->useEdge(UserToGroup::getName());
$user_to_group->getField('admin')->setProtected(false)->setValue(true);

$group = $user_to_group->vertex();
$group_fields = $group->getFields();
foreach ($group_fields as $field) $field->setProtected(true);

$group->getField(Arango::KEY)->setProtected(false)->setRequired(true)->setValue($group_key_value);
if ($target_collection_name === Policy::COLLECTION) $group->getField(Vertex::OWNER)->setProtected(false)->setRequired(true)->setValue($user->getField(Arango::KEY)->getValue());

$group_warnings = $group->checkRequired()->getAllFieldsWarning();

$check_user_select = $user_query->select();
$check_user_select->getLimit()->set(1);
$check_user_select_return = 'RETURN 1';
$check_user_select->getReturn()->setPlain($check_user_select_return);
$check_user_select_statement = $check_user_select->getStatement();
$check_user_select_statement->setExpect(1)->setHideResponse(true);

$check_target = User::login();
$check_target_query = ArangoDB::start($check_target);
$check_target_query_select = $check_target_query->select();
$check_target_query_select->getLimit()->set(1);
$check_target_query_select_return = 'RETURN 1';
$check_target_query_select->getReturn()->setPlain($check_target_query_select_return);

$check_target_edge = $check_target->useEdge('UserTo' . $target_collection_name);
$check_target_vertex = $check_target_edge->vertex();
$check_target_vertex->getField(Arango::KEY)->setProtected(false)->setRequired(true)->setValue($target_key);

$preliminary = [];

switch ($target_collection_name) {
    case Policy::COLLECTION;
        $check_target_edge->getField('reassignment')->setProtected(false)->setRequired(true)->setValue(true);
        $check_target->useEdge(UserToApplication::getName())->vertex()->useEdge(ApplicationToPolicy::getName(), $check_target_edge);
        $check_target_user_group = $check_target->useEdge(UserToGroup::getName())->vertex();
        $check_target_user_group->useEdge(GroupToGroup::getName())->setForceDirection(Edge::INBOUND)->vertex()->useEdge(GroupToPolicy::getName(), $check_target_edge);
        $check_target_user_group->useEdge(GroupToPolicy::getName(), $check_target_edge);
        break;
    case Group::COLLECTION:
        $check_target_edge->getField('admin')->setProtected(false)->setRequired(true)->setValue(true);

        Language::dictionary(__file__);
        $exception_message = __namespace__ . '\\' . 'exception' . '\\';

        $check_target_vertex_clone = clone $check_target_vertex;
        $check_target_vertex_clone_query = ArangoDB::start($check_target_vertex_clone);

        $check_target_vertex_clone_edge = $check_target_vertex_clone->useEdge(GroupToGroup::getName());
        $check_target_vertex_clone_edge->vertex($group);
        $check_target_vertex_clone_edge->branch()->vertex()->useEdge(GroupToGroup::getName(), $check_target_vertex_clone_edge);

        $check_target_vertex_clone_query_select = $check_target_vertex_clone_query->select();
        $check_target_vertex_clone_query_select->getLimit()->set(1);
        $check_target_vertex_clone_query_select_return = 'RETURN 1';
        $check_target_vertex_clone_query_select->getReturn()->setPlain($check_target_vertex_clone_query_select_return);
        $check_target_vertex_clone_query_select_statement = $check_target_vertex_clone_query_select->getStatement();
        $check_target_vertex_clone_query_select_statement_exception_message = $exception_message . 'loop';
        $check_target_vertex_clone_query_select_statement_exception_message = Language::translate($check_target_vertex_clone_query_select_statement_exception_message);
        $check_target_vertex_clone_query_select_statement->setExceptionMessage($check_target_vertex_clone_query_select_statement_exception_message);
        $check_target_vertex_clone_query_select_statement->setExpect(0)->setHideResponse(true);
        array_push($preliminary, $check_target_vertex_clone_query_select_statement);
        break;
}

$group_query = ArangoDB::start($group);
$group_query_edge = $group->useEdge('GroupTo' . $target_collection_name);
$group_query_edge_fields = $group_query_edge->getFields();
foreach ($group_query_edge_fields as $field) $field->setRequired(true);
if ($target_collection_name === Group::COLLECTION) $group_query_edge->setForceDirection(Edge::INBOUND);

$group_query_vertex = $group_query_edge->vertex();
$group_query_vertex_fields = $group_query_vertex->getFields();
foreach ($group_query_vertex_fields as $field) $field->setProtected(true);

$group_query_vertex->getField(Arango::KEY)->setProtected(false)->setRequired(true)->setValue($target_key);
$group_query_vertex_warnings = $group_query_vertex->checkRequired()->getAllFieldsWarning();

if (!!$errors = array_merge($group_warnings, $group_query_vertex_warnings)) {
    Language::dictionary(__file__);
    $notice = __namespace__ . '\\' . 'notice';
    $notice = Language::translate($notice);
    Output::concatenate('notice', $notice);
    Output::concatenate('errors', $errors);
    Output::print(false);
}

$check_target_query_select_statement = $check_target_query_select->getStatement();
$check_target_query_select_statement->setExpect(1)->setHideResponse(true);

$group_query_upsert = $group_query->upsert();
$group_query_upsert->setActionOnlyEdges(true);
$group_query_upsert->pushStatementsPreliminary($check_user_select_statement, $check_target_query_select_statement, ...$preliminary);
$group_query_upsert_response = $group_query_upsert->run();
if (null !== $group_query_upsert_response) Output::print(true);
Output::print(false);
