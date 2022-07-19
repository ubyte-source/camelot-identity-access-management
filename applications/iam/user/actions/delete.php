<?PHP

namespace applications\iam\user\actions;

use Knight\armor\Output;
use Knight\armor\Language;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\operations\common\Choose;
use ArangoDB\entity\Edge;
use ArangoDB\entity\Vertex;
use ArangoDB\entity\common\Arango;

use applications\iam\user\database\Vertex as User;
use applications\iam\user\database\edges\UserToUser;
use applications\iam\policy\database\Vertex as Policy;

use extensions\Navigator;

Policy::mandatories('iam/user/action/delete');

const NOMOVE_EDGES = [
    'UserToSetting',
    'UserToPolicy'
];

$follow_user_edges = new User();
ArangoDB::start($follow_user_edges);
$follow_user_edges = $follow_user_edges->getAllUsableEdgesName(true);

Language::dictionary(__file__);
$exception_message = __namespace__ . '\\' . 'exception' . '\\' . 'hierarchy';

$user = User::Login();

$delete_user_key = parse_url($_SERVER[Navigator::REQUEST_URI], PHP_URL_PATH);
$delete_user_key = basename($delete_user_key);

$user_child = new User();
$user_child_fields = $user_child->getFields();
foreach ($user_child_fields as $field) $field->setProtected(true);

$user_child->getField(Arango::KEY)->setProtected(false)->setRequired(true)->setValue($delete_user_key);
$user_child_query = ArangoDB::start($user_child);

$user_child_branch = $user_child->useEdge(UserToUser::getName())->setForceDirection(Edge::INBOUND);
$user_child_branch->vertex($user);
$user_child_branch->branch()->vertex()->useEdge(UserToUser::getName())->setForceDirection(Edge::INBOUND)->vertex($user);

$user_child_query_select = $user_child_query->select();
$user_child_query_select->getLimit()->set(1);
$user_child_query_select_return = 'RETURN 1';
$user_child_query_select->getReturn()->setPlain($user_child_query_select_return);
$user_child_query_select_statement = $user_child_query_select->getStatement();
$user_child_query_select_statement_exception_message = Language::translate($exception_message, $delete_user_key);
$user_child_query_select_statement->setExceptionMessage($user_child_query_select_statement_exception_message);
$user_child_query_select_statement->setExpect(1)->setHideResponse(true);
$user_child->getContainer()->removeEdgesByName(UserToUser::getName());

$user_child_clone = clone $user_child;
$user_child_clone_query = ArangoDB::start($user_child_clone);

$user_child_query_remove = $user_child_query->remove();
$user_child_query_remove->pushStatementsPreliminary($user_child_query_select_statement);
$user_child_query_remove_skip_owner = $user_child->useEdge(UserToUser::getName())->setForceDirection(Edge::INBOUND)->vertex();
$user_child_query_remove->pushEntitySkips($user_child_query_remove_skip_owner);

foreach ($follow_user_edges as $edge_name) {
    $follow_edge_vertex = $user_child->useEdge($edge_name)->vertex();
    $user_child_query_remove->pushEntitySkips($follow_edge_vertex);

    if (true === in_array($edge_name, NOMOVE_EDGES)) continue;

    $follow_edge_vertex_clone = clone $follow_edge_vertex;
    $follow_edge_vertex_clone->unsetAdapter();
    $follow_edge_vertex_clone_query = ArangoDB::start($follow_edge_vertex_clone);
    $follow_edge_vertex_clone->getField(Vertex::OWNER)->setProtected(false)->setValue($delete_user_key);

    $follow_edge_name_reverse = explode('To', $edge_name);
    $follow_edge_name_reverse = array_filter($follow_edge_name_reverse);
    if (2 === count($follow_edge_name_reverse)) {
        $follow_edge_name_reverse = array_reverse($follow_edge_name_reverse);
        $follow_edge_name_reverse = implode('To', $follow_edge_name_reverse);
        $follow_edge_name_reverse_entity = $follow_edge_vertex_clone->useEdge($follow_edge_name_reverse);
        $follow_edge_name_reverse_entity_fields = $follow_edge_name_reverse_entity->getFields();
        foreach ($follow_edge_name_reverse_entity_fields as $field) $field->setRequired(true);

        if ($follow_edge_name_reverse === UserToUser::getName()) $follow_edge_name_reverse_entity->setForceDirection(Edge::INBOUND);
        if ($follow_edge_name_reverse_entity->checkFieldExists('admin')) $follow_edge_name_reverse_entity->getField('admin')->setProtected(false)->setValue(true);

        $follow_edge_name_reverse_entity->vertex($user);
    }

    $follow_edge_vertex_clone_query_upsert = $follow_edge_vertex_clone_query->upsert();
    $follow_edge_vertex_clone_query_upsert->setActionOnlyEdges(true);
    $follow_edge_vertex_clone_query_upsert_transaction = $follow_edge_vertex_clone_query_upsert->getTransaction();

    $user_child_query_remove->pushTransactionsFinal($follow_edge_vertex_clone_query_upsert_transaction);
}

$writer = [];

foreach ($follow_user_edges as $edge_name) {
    if (in_array($edge_name, NOMOVE_EDGES)) continue;

    $user_vertex = User::Login();
    $user_vertex_query = ArangoDB::start($user_vertex);

    $destination = $user_vertex->useEdge($edge_name)->vertex();
    $destination_collection_name = $destination->getCollectionName();
    array_push($writer, $destination_collection_name);

    $destination_clone = clone $destination;
    $destination_clone->getField(Vertex::OWNER)->setProtected(false)->setValue($delete_user_key);

    $destination_clone_query = ArangoDB::start($destination_clone);
    $destination_clone_query_update = $destination_clone_query->select();
    $destination_clone_query_update_vertex = $destination_clone_query_update->getPointer(Choose::VERTEX);
    $destination_clone_query_update_vertex = $destination_clone_query_update_vertex . chr(46) . $destination_clone->getField(Arango::ID)->getName();
    $destination_clone_query_update_return = 'UPDATE' . chr(32) . $destination_clone_query_update_vertex . chr(32) . 'WITH {owner: $0} IN' . chr(32) . $destination_collection_name;
    $destination_clone_query_update->getReturn()->setPlain($destination_clone_query_update_return, $user->getField(Arango::KEY)->getValue());
    $destination_clone_query_update_statement = $destination_clone_query_update->getStatement();

    $user_child_query_remove->pushStatementsFinal($destination_clone_query_update_statement);
}

if (!!$errors = $user_child->checkRequired()->getAllFieldsWarning()) {
    Language::dictionary(__file__);
    $notice = __namespace__ . '\\' . 'notice';
    $notice = Language::translate($notice);
    Output::concatenate('notice', $notice);
    Output::concatenate('errors', $errors);
    Output::print(false);
}

$user_child_query_remove_transaction = $user_child_query_remove->getTransaction();
$user_child_query_remove_transaction->openCollectionsWriteMode(...$writer);
$user_child_query_remove_transaction_response = $user_child_query_remove_transaction->commit();
if (null !== $user_child_query_remove_transaction_response) Output::print(true);
Output::print(false);
