<?PHP

namespace applications\iam\user\actions;

use Knight\armor\Output;
use Knight\armor\Request;
use Knight\armor\Language;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\entity\Edge;
use ArangoDB\entity\common\Arango;

use applications\iam\user\database\Vertex as User;
use applications\iam\user\database\edges\UserToUser;
use applications\iam\user\database\edges\UserToGroup;
use applications\iam\user\database\edges\UserToApplication;
use applications\iam\group\database\Vertex as Group;
use applications\iam\group\database\edges\GroupToGroup;
use applications\iam\group\database\edges\GroupToPolicy;
use applications\iam\policy\database\Vertex as Policy;
use applications\sso\application\database\edges\ApplicationToPolicy;

use extensions\Navigator;

const TARGET_COLLECTION_ASSIGNMENT_ADMITTED = array(
    Policy::COLLECTION,
    Group::COLLECTION
);

Policy::mandatories('iam/user/action/assignment');

$user = User::login();
$user_query = ArangoDB::start($user);

$target_id = Request::post($user->getField(Arango::ID)->getName());
$target_id = null !== $target_id ? explode(Arango::SEPARATOR, $target_id) : [];
$target_id_count = count($target_id);

if (2 !== $target_id_count) Output::print(false);

list($target_collection_name, $target_key) = $target_id;
if (!in_array($target_collection_name, TARGET_COLLECTION_ASSIGNMENT_ADMITTED)) Output::print(false);

$user_child_key_value = parse_url($_SERVER[Navigator::REQUEST_URI], PHP_URL_PATH);
$user_child_key_value = basename($user_child_key_value);

$user_child_edge = $user->useEdge(UserToUser::getName());
$user_child = $user_child_edge->vertex();
$user_child_fields = $user_child->getFields();
foreach ($user_child_fields as $field) $field->setProtected(true);

$user_child->getField(Arango::KEY)->setProtected(false)->setRequired(true)->setValue($user_child_key_value);
$user_child_warnings = $user_child->checkRequired()->getAllFieldsWarning();
$user_child = $user_child_edge->branch()->vertex()->useEdge(UserToUser::getName())->vertex($user_child);

$check_user_child_select = $user_query->select();
$check_user_child_select->getLimit()->set(1);
$check_user_child_select_return = 'RETURN 1';
$check_user_child_select->getReturn()->setPlain($check_user_child_select_return);
$check_user_child_select_statement = $check_user_child_select->getStatement();
$check_user_child_select_statement->setExpect(1)->setHideResponse(true);

$check_target = User::login();
$check_target_query = ArangoDB::start($check_target);
$check_target_query_select = $check_target_query->select();
$check_target_query_select->getLimit()->set(1);
$check_target_query_select_return = 'RETURN 1';
$check_target_query_select->getReturn()->setPlain($check_target_query_select_return);

$check_target_edge = $check_target->useEdge('UserTo' . $target_collection_name);
$check_target_edge->vertex()->getField(Arango::KEY)->setProtected(false)->setValue($target_key);

$preliminary = [];

switch ($target_collection_name) {
    case Policy::COLLECTION:
        $check_target_edge->getField('reassignment')->setProtected(false)->setValue(true);
        $check_target->useEdge(UserToApplication::getName())->vertex()->useEdge(ApplicationToPolicy::getName(), $check_target_edge);
        $check_target_user_group = $check_target->useEdge(UserToGroup::getName())->vertex();
        $check_target_user_group->useEdge(GroupToGroup::getName())->setForceDirection(Edge::INBOUND)->vertex()->useEdge(GroupToPolicy::getName(), $check_target_edge);
        $check_target_user_group->useEdge(GroupToPolicy::getName(), $check_target_edge);

        $user_child_administrator = new User();
        $user_child_administrator->getField(Arango::KEY)->setProtected(false)->setValue($user_child_key_value);
        $user_child_administrator_query = ArangoDB::start($user_child_administrator);
        $user_child_administrator->useEdge(UserToApplication::getName())->vertex()->useEdge(ApplicationToPolicy::getName(), $check_target_edge);
        $user_child_administrator_query_select = $user_child_administrator_query->select();
        $user_child_administrator_query_select->getLimit()->set(1);
        $user_child_administrator_query_select_return = 'RETURN 1';
        $user_child_administrator_query_select->getReturn()->setPlain($user_child_administrator_query_select_return);
        $user_child_administrator_query_select_statement = $user_child_administrator_query_select->getStatement();
        $user_child_administrator_query_select_statement->setExpect(0)->setHideResponse(true);
        array_push($preliminary, $user_child_administrator_query_select_statement);
        break;
    case Group::COLLECTION:
        $check_target_edge->getField('admin')->setProtected(false)->setValue(true);
        break;
}

$user_child_query = ArangoDB::start($user_child);
$user_child_query_edge = $user_child->useEdge('UserTo' . $target_collection_name);
$user_child_query_edge_fields = $user_child_query_edge->getFields();
foreach ($user_child_query_edge_fields as $field) $field->setRequired(true);

$user_child_query_vertex = $user_child_query_edge->vertex();
$user_child_query_vertex_fields = $user_child_query_vertex->getFields();
foreach ($user_child_query_vertex_fields as $field) $field->setProtected(true);

$user_child_query_vertex->getField(Arango::KEY)->setProtected(false)->setRequired(true)->setValue($target_key);
$user_child_query_vertex_warnings = $user_child_query_vertex->checkRequired()->getAllFieldsWarning();

if (!!$errors = array_merge($user_child_warnings, $user_child_query_vertex_warnings)) {
    Language::dictionary(__file__);
    $notice = __namespace__ . '\\' . 'notice';
    $notice = Language::translate($notice);
    Output::concatenate('notice', $notice);
    Output::concatenate('errors', $errors);
    Output::print(false);
}

$check_target_query_select_statement = $check_target_query_select->getStatement();
$check_target_query_select_statement->setExpect(1)->setHideResponse(true);

$user_child_query_upsert = $user_child_query->upsert();
$user_child_query_upsert->setActionOnlyEdges(true);
$user_child_query_upsert->pushStatementsPreliminary($check_user_child_select_statement, $check_target_query_select_statement, ...$preliminary);
$user_child_query_upsert_response = $user_child_query_upsert->run();
if (null !== $user_child_query_upsert_response) Output::print(true);
Output::print(false);
