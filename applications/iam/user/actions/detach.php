<?PHP

namespace applications\iam\user\actions;

use Knight\armor\Output;
use Knight\armor\Request;
use Knight\armor\Language;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\Statement;
use ArangoDB\operations\common\Choose;
use ArangoDB\entity\Vertex;
use ArangoDB\entity\common\Arango;

use applications\iam\user\database\Vertex as User;
use applications\iam\user\database\edges\UserToUser;
use applications\iam\group\database\Vertex as Group;
use applications\iam\group\database\edges\GroupToGroup;
use applications\iam\group\database\edges\GroupToUser;
use applications\iam\policy\database\Vertex as Policy;
use applications\iam\policy\database\edges\PolicyToGroup;
use applications\iam\policy\database\edges\PolicyToApplication;
use applications\sso\application\database\edges\ApplicationToUser;

use extensions\Navigator;

const TARGET_COLLECTION_ASSIGNMENT_ADMITTED = array(
    Policy::COLLECTION,
    Group::COLLECTION
);

Policy::mandatories('iam/user/action/detach');

$user = User::login();
$user_query = ArangoDB::start($user);

$target_id = Request::post($user->getField(Arango::ID)->getName());
$target_id = null !== $target_id ? explode(Arango::SEPARATOR, $target_id) : [];
$target_id_count = count($target_id);

if (2 !== $target_id_count) Output::print(false);

list($target_collection_name, $target_key) = $target_id;
if (!in_array($target_collection_name, TARGET_COLLECTION_ASSIGNMENT_ADMITTED)) Output::print(false);

$user_child_key = parse_url($_SERVER[Navigator::REQUEST_URI], PHP_URL_PATH);
$user_child_key = basename($user_child_key);

$user_child_edge = $user->useEdge(UserToUser::getName());
$user_child = $user_child_edge->vertex();
$user_child_fields = $user_child->getFields();
foreach ($user_child_fields as $field) $field->setProtected(true);

$user_child->getField(Arango::KEY)->setProtected(false)->setRequired(true)->setValue($user_child_key);
$user_child_warnings = $user_child->checkRequired()->getAllFieldsWarning();
$user_child = $user_child_edge->branch()->vertex()->useEdge(UserToUser::getName())->vertex($user_child);

$user_query_select = $user_query->select();
$user_query_select->getLimit()->set(1);
$user_query_select_return = 'RETURN 1';
$user_query_select->getReturn()->setPlain($user_query_select_return);
$user_query_select_statement = $user_query_select->getStatement();
$user_query_select_statement->setExpect(1)->setHideResponse(true);

$check_edge = $user_child->useEdge('UserTo' . $target_collection_name);
$check_edge_vertex = $check_edge->vertex();
$check_edge_vertex_fields = $check_edge_vertex->getFields();
foreach ($check_edge_vertex_fields as $field) $field->setProtected(true);

$check_edge_vertex->getField(Arango::KEY)->setProtected(false)->setRequired(true)->setValue($target_key);
$check_edge_vertex_warnings = $check_edge_vertex->checkRequired()->getAllFieldsWarning();

if (!!$errors = array_merge($user_child_warnings, $check_edge_vertex_warnings)) {
    Language::dictionary(__file__);
    $notice = __namespace__ . '\\' . 'notice';
    $notice = Language::translate($notice);
    Output::concatenate('notice', $notice);
    Output::concatenate('errors', $errors);
    Output::print(false);
}

$user_child_query = ArangoDB::start($user_child);
$user_child_query_select = $user_child_query->select();
$user_child_query_select->getLimit()->set(1);

$clone_detach = clone $check_edge_vertex;
$clone_detach_query = ArangoDB::start($clone_detach);
$clone_detach_query_select = $clone_detach_query->select();
$clone_detach_query_select->useWith(false);
$clone_detach_query_select->getLimit()->set(1);

$clone_detach_edge = $clone_detach->useEdge($target_collection_name . 'ToUser');
$clone_detach_edge->vertex($user);

$user_child_query_remove = $user_child_query->remove();
$user_child_query_remove->setActionOnlyEdges(true);

switch ($target_collection_name) {
    case Group::COLLECTION:
        $clone_detach_edge->getField('admin')->setProtected(false)->setValue(true);

        $clone_detach_query_select_return = new Statement();
        $clone_detach_query_select_return->append('RETURN 1');
        $clone_detach_query_select->getReturn()->setFromStatement($clone_detach_query_select_return);

        $clone_detach_query_select_statement = $clone_detach_query_select->getStatement();
        $clone_detach_query_select_statement_query = $clone_detach_query_select_statement->getQuery();
        $clone_detach_query_select_statement_query_with = $clone_detach_query_select->getWithCollectionsParsed();

        $admin = $check_edge->getField('admin')->getName();
        $owner = $check_edge_vertex->getField(Vertex::OWNER)->getName();

        $user_child_query_select_return = new Statement();
        $user_child_query_select_return->append('LET y = FIRST(', false);
        $user_child_query_select_return->append($clone_detach_query_select_statement_query, false);
        $user_child_query_select_return->append(')');
        $user_child_query_select_return->append('LET admin = true ==' . chr(32) . $user_child_query_select->getPointer(Choose::EDGE) . chr(46) . $admin);
        $user_child_query_select_return->append('LET owner = $0 ==' . chr(32) . $user_child_query_select->getPointer(Choose::VERTEX) . chr(46) . $owner);
        $user_child_query_select_return->append('FILTER owner OR !admin && y != null');
        $user_child_query_select_return->append('RETURN 1', false);
        $user_child_query_select_return->addBindFromStatements($clone_detach_query_select_statement);
        $user_child_query_select->getReturn()->setFromStatement($user_child_query_select_return, $user->getField(Arango::KEY)->getValue());
        $user_child_query_select->pushWithCollection(...$clone_detach_query_select_statement_query_with);

        $group_association = clone $check_edge_vertex;
        $group_association_query = ArangoDB::start($group_association);
        $group_association_query_remove = $group_association_query->remove();
        $group_association_query_remove->setActionOnlyEdges(true);
        $group_association->useEdge(GroupToGroup::getName())->vertex()->getField(Vertex::OWNER)->setProtected(false)->setRequired(true)->setValue($user_child_key);

        $group_association_query_remove_transaction = $group_association_query_remove->getTransaction();
        $user_child_query_remove->pushTransactionsPreliminary($group_association_query_remove_transaction);
        break;
    case Policy::COLLECTION:
        $clone_detach_to_group = $clone_detach->useEdge(PolicyToGroup::getName());
        $clone_detach_to_group_vertex = $clone_detach_to_group->vertex();
        $clone_detach_to_group_vertex->useEdge(GroupToUser::getName())->vertex($user);
        $clone_detach_to_group->branch()->vertex()->useEdge(GroupToGroup::getName())->vertex($clone_detach_to_group_vertex, true);
        $clone_detach->useEdge(PolicyToApplication::getName())->vertex()->useEdge(ApplicationToUser::getName())->vertex($user);

        $clone_detach_query_select_statement = $clone_detach_query_select->getStatement();
        $clone_detach_query_select_statement_query = $clone_detach_query_select_statement->getQuery();
        $clone_detach_query_select_statement_query_with = $clone_detach_query_select->getWithCollectionsParsed();

        $user_child_query_select_return = new Statement();
        $user_child_query_select_return->append('LET y = FIRST(', false);
        $user_child_query_select_return->append($clone_detach_query_select_statement_query, false);
        $user_child_query_select_return->append(')');
        $user_child_query_select_return->append('FILTER y != null');
        $user_child_query_select_return->append('RETURN 1', false);
        $user_child_query_select_return->addBindFromStatements($clone_detach_query_select_statement);
        $user_child_query_select->getReturn()->setFromStatement($user_child_query_select_return);
        $user_child_query_select->pushWithCollection(...$clone_detach_query_select_statement_query_with);
        break;
}

$user_child_query_select_statement = $user_child_query_select->getStatement();
$user_child_query_select_statement->setExpect(1)->setHideResponse(true);

$user_child_query_remove->pushStatementsPreliminary($user_query_select_statement, $user_child_query_select_statement);
$user_child_query_remove_response = $user_child_query_remove->run();
if (null !== $user_child_query_remove_response) Output::print(true);
Output::print(false);
