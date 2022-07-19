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
use applications\iam\group\database\Vertex as Group;
use applications\iam\policy\database\Vertex as Policy;

use extensions\Navigator;

const TARGET_COLLECTION_ASSIGNMENT_ADMITTED = array(
    Policy::COLLECTION,
    Group::COLLECTION
);

Policy::mandatories('iam/group/action/detach');

$user = User::login();
$user_query = ArangoDB::start($user);

$target_id = Request::post($user->getField(Arango::ID)->getName());
$target_id = null !== $target_id ? explode(Arango::SEPARATOR, $target_id) : [];
$target_id_count = count($target_id);

if (2 !== $target_id_count) Output::print(false);

list($target_collection_name, $target_key) = $target_id;
if (!in_array($target_collection_name, TARGET_COLLECTION_ASSIGNMENT_ADMITTED)) Output::print(false);

$group_key = parse_url($_SERVER[Navigator::REQUEST_URI], PHP_URL_PATH);
$group_key = basename($group_key);

$user_to_group = $user->useEdge(UserToGroup::getName());
$user_to_group->getField('admin')->setProtected(false)->setValue(true);

$group = $user_to_group->vertex();
$group_fields = $group->getFields();
foreach ($group_fields as $field) $field->setProtected(true);

$group->getField(Arango::KEY)->setProtected(false)->setRequired(true)->setValue($group_key);

$user_to_group_select = $user_query->select();
$user_to_group_select->getLimit()->set(1);
$user_to_group_select_return = 'RETURN 1';
$user_to_group_select->getReturn()->setPlain($user_to_group_select_return);
$user_to_group_select_statement = $user_to_group_select->getStatement();
$user_to_group_select_statement->setExpect(1)->setHideResponse(true);

$check_edge = $group->useEdge('GroupTo' . $target_collection_name);
$check_vertex = $check_edge->vertex();
$check_vertex->getField(Arango::KEY)->setProtected(false)->setRequired(true)->setValue($target_key);

switch ($target_collection_name) {
    case Policy::COLLECTION:
        $check_vertex->getField(Vertex::OWNER)->setProtected(false)->setRequired(true)->setValue($user->getField(Arango::KEY)->getValue());
        break;
    case Group::COLLECTION:
        $check_edge->setForceDirection(Edge::INBOUND);
        break;
}

if (!!$errors = $group->checkRequired()->getAllFieldsWarning()) {
    Language::dictionary(__file__);
    $notice = __namespace__ . '\\' . 'notice';
    $notice = Language::translate($notice);
    Output::concatenate('notice', $notice);
    Output::concatenate('errors', $errors);
    Output::print(false);
}

$user_query = ArangoDB::start($group);
$user_query_remove = $user_query->remove();
$user_query_remove->pushStatementsPreliminary($user_to_group_select_statement);
$user_query_remove->setActionOnlyEdges(true);
$user_query_remove_response = $user_query_remove->run();
if (null !== $user_query_remove_response) Output::print(true);
Output::print(false);
