<?PHP

namespace applications\iam\group\actions;

use Knight\armor\Output;
use Knight\armor\Request;
use Knight\armor\Language;

use Entity\Map;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\Statement;
use ArangoDB\operations\common\Choose;
use ArangoDB\operations\Update;
use ArangoDB\entity\Vertex;
use ArangoDB\entity\Edge;
use ArangoDB\entity\common\Arango;
use ArangoDB\operations\common\Handling;

use applications\iam\user\database\Vertex as User;
use applications\iam\user\database\edges\UserToGroup;
use applications\iam\user\database\edges\UserToUser;
use applications\iam\group\database\Vertex as Group;
use applications\iam\group\database\edges\GroupToUser;
use applications\iam\group\database\edges\GroupToGroup;
use applications\iam\policy\database\Vertex as Policy;
use applications\iam\group\forms\Matrioska;

use extensions\Navigator;

Policy::mandatories('iam/group/action/create');

$group_key_value = parse_url($_SERVER[Navigator::REQUEST_URI], PHP_URL_PATH);
$group_key_value = basename($group_key_value);

$user = User::login();
ArangoDB::start($user);

$user_to_group = $user->useEdge(UserToGroup::getName());
$user_to_group->getField('admin')->setProtected(false)->setRequired(true)->setValue(true);

$group = $user_to_group->vertex();
$group_check = clone $group;
$group_check->getField(Arango::KEY)->setProtected(false)->setValue($group_key_value);

$matrioska = new Matrioska();
$matrioska->setFromAssociative((array)Request::post());

if (null !== Request::post('share')) {
    $group_field_owner = $group->getField(Vertex::OWNER);
    $group_field_owner->setProtected(false)->setRequired(true);
    $group_field_owner->setValue($user->getField(Arango::KEY)->getValue());
    $group_check->addFieldClone($group_field_owner);
}

$group_check_query = ArangoDB::start($group_check);
$group_check->useEdge(GroupToUser::getName())->vertex($user);
$group_check_query_select = $group_check_query->select();
$group_check_query_select->getLimit()->set(1);
$group_check_query_select_return = 'RETURN 1';
$group_check_query_select->getReturn()->setPlain($group_check_query_select_return);
$group_check_query_select_statement = $group_check_query_select->getStatement();
$group_check_query_select_statement->setExpect(1)->setHideResponse(true);

$group->setFromAssociative((array)Request::post());
$group->getField(Arango::KEY)->setProtected(false)->setRequired(true)->setValue($group_key_value);

$management = [];

$group_clone = clone $group;
$group_clone_fields = $group_clone->getFields();
foreach ($group_clone_fields as $field) $field->setRequired(true);

$group_clone_query = ArangoDB::start($group_clone);
$group_clone_query_update = $group_clone_query->update();
$group_clone_query_update->setReplace(true);
$group_clone_query_update_return = 'RETURN' . chr(32) . Handling::RNEW;
$group_clone_query_update->getReturn()->setPlain($group_clone_query_update_return);
$group_clone_query_update->setEntityEnableReturns($group_clone);

foreach (Vertex::MANAGEMENT as $field_name) {
    $group_clone_field_value = Update::SEARCH . chr(46) . $field_name;
    $group_clone->getField($field_name)->setSafeModeDetached(false)->setRequired(true)->setValue($group_clone_field_value);
    array_push($management, $group_clone_field_value);
}

$group_clone_query_update->pushStatementsPreliminary($group_check_query_select_statement);
$group_clone_query_update->pushStatementSkipValues(...$management);
$group_clone_query_update_transaction = $group_clone_query_update->getTransaction();

$group_anchor = [
    $matrioska->getField('share')->getName() => GroupToUser::getName(),
    $matrioska->getField('group')->getName() => GroupToGroup::getName(),
    $matrioska->getField('users')->getName() => GroupToUser::getName()
];

Language::dictionary(__file__);
$exception_message = __namespace__ . '\\' . 'exception' . '\\';

$writer = [];
$remove = [];
$preliminary = [];

foreach ($group_anchor as $field_name => $edge_name) {

    if (null === Request::post($field_name)) continue;

    $group_follow = new Group();
    $group_follow->getField(Arango::KEY)->setProtected(false)->setValue($group_key_value);
	$group_follow_query = ArangoDB::start($group_follow);
    $group_follow_edges = $group_follow->useEdge($edge_name);
    $group_follow_edges_vertex = $group_follow_edges->vertex();

    switch ($edge_name) {
        case GroupToUser::getName():
            $group_follow_edges_vertex_branch = $group_follow_edges_vertex->useEdge(UserToUser::getName())->setForceDirection(Edge::INBOUND);
            $group_follow_edges_vertex_branch->vertex($user);
            $group_follow_edges_vertex_branch->branch()->vertex()->useEdge(UserToUser::getName())->setForceDirection(Edge::INBOUND)->vertex($user);
            break;
        case GroupToGroup::getName():
            $group_follow_edges_vertex_group_to_user = $group_follow_edges_vertex->useEdge(GroupToUser::getName());
            $group_follow_edges_vertex_group_to_user->getField('admin')->setProtected(false)->setValue(true);
            $group_follow_edges_vertex_group_to_user->vertex($user);
            break;
    }

    $binder = [];

    $group_follow_query_select = $group_follow_query->select();
    $group_follow_query_select_return = new Statement();
    $group_follow_query_select_return->append('LET edge_remove = FIRST(' . $group_follow_query_select->getPointer(Choose::TRAVERSAL_EDGE) . ')');

    switch ($field_name) {
        case 'users':
            $group_follow_query_select_return->append('FILTER edge_remove.admin == false OR edge_remove.admin == null');
            break;
        case 'share':
            $group_follow_query_select_return->append('LET user = SLICE(' . $group_follow_query_select->getPointer(Choose::TRAVERSAL_VERTEX) . ', 1, 1)');
            $group_follow_query_select_return->append('LET user_first = FIRST(user)');
            $group_follow_query_select_return->append('FILTER $0 != user_first._key AND edge_remove.admin == true');
            array_push($binder, $user->getField(Arango::KEY)->getValue());

            $group_association = new Group();
            $group_association_owner = 'user_first' . chr(46) . Arango::KEY;
            $group_association->getField(Vertex::OWNER)->setSafeModeDetached(false)->setValue($group_association_owner);

            $group_association_query = ArangoDB::start($group_association);
            $group_association_query_select = $group_association_query->select();
            $group_association_query_select->useWith(false);
            $group_association_query_select_return = 'RETURN' . chr(32) . $group_association_query_select->getPointer(Choose::EDGE);
            $group_association_query_select->getReturn()->setPlain($group_association_query_select_return);

            $group_association_edge = $group_association->useEdge(GroupToGroup::getName());
            $group_association_edge->vertex()->getField(Arango::KEY)->setProtected(false)->setValue($group_key_value);

            $group_association_query_select_statement = $group_association_query_select->getStatement();
            $group_association_query_select_statement_query = $group_association_query_select_statement->getQuery();
            $group_association_query_select_statement_query_with = $group_association_query_select->getWithCollectionsParsed();

            $group_association_query_select_return = clone $group_follow_query_select_return;

            $assign = $matrioska->getField('share')->getValue();
            $assign = array_map(function (Map $item) {
                return $item->getField(Arango::KEY)->getValue();
            }, $assign);
            $assign = array_filter($assign);
            $assign_count = count($assign);
            if (0 !== $assign_count) {
                $group_association_query_select_return_detach = range($range = count($binder), $range + $assign_count - 1);
                $group_association_query_select_return_detach = preg_filter('/\d+/', 'user_first._key != $$0', $group_association_query_select_return_detach);
                $group_association_query_select_return_detach = implode(chr(32) . 'AND' . chr(32), $group_association_query_select_return_detach);
                $group_association_query_select_return->append('FILTER');
                $group_association_query_select_return->append($group_association_query_select_return_detach);
            }

            $group_association_query_select_return_remove = $group_association_edge->getCollectionName();
            $group_association_query_select_return->append('LET clean = FIRST(' . $group_association_query_select_statement_query . ')');
            $group_association_query_select_return->append('REMOVE clean IN');
            $group_association_query_select_return->append($group_association_query_select_return_remove);
            $group_association_query_select_return->append('OPTIONS {waitForSync: true, ignoreErrors: true} RETURN 1');
            $group_association_query_select_return->addBindFromStatements($group_association_query_select_statement);

            $group_follow_query_select_clone = clone $group_follow_query_select;
            $group_follow_query_select_clone->pushStatementSkipValues($group_association_owner);
            $group_follow_query_select_clone->getReturn()->setFromStatement($group_association_query_select_return, $user->getField(Arango::KEY)->getValue(), ...$assign);
            $group_follow_query_select_clone->pushWithCollection(...$group_association_query_select_statement_query_with);
            $group_follow_query_select_clone_statement = $group_follow_query_select_clone->getStatement();
            $group_follow_query_select_clone_statement->setHideResponse(true);

            array_push($remove, $group_follow_query_select_clone_statement);
            array_push($writer, $group_association_query_select_return_remove);
            break;
    }

    $collection = $group_follow_edges->getCollectionName();
    $group_follow_query_select_return->append('REMOVE edge_remove IN');
    $group_follow_query_select_return->append($collection);
    $group_follow_query_select_return->append('OPTIONS {waitForSync: true, ignoreErrors: true} RETURN 1');
	$group_follow_query_select->getReturn()->setFromStatement($group_follow_query_select_return, ...$binder);
    $group_follow_query_select_statement = $group_follow_query_select->getStatement();
    $group_follow_query_select_statement->setHideResponse(true);

    array_push($remove, $group_follow_query_select_statement);
    array_push($writer, $collection);

    if ($matrioska->getField($field_name)->isDefault()) continue;

    $assign = $matrioska->getField($field_name)->getValue();
    foreach ($assign as $destination) if (empty($destination->checkRequired()->getAllFieldsWarning())) {
        $edge = $group->useEdge($edge_name);
        if ($field_name === 'share') $edge->getField('admin')->setProtected(false)->setRequired(true)->setValue(true);

        $target = $edge->vertex($destination);

        $check_user = User::login();
        $check_user_query =  ArangoDB::start($check_user);

        $used = explode('To', $edge_name);
        $used = end($used);
        $edge_reverse = $check_user->useEdge('UserTo' . $used);
        $edge_reverse->vertex($target);
        $check_user->useEdge(UserToUser::getName())->vertex()->useEdge('UserTo' . $used, $edge_reverse);

        $check_user_query_select = $check_user_query->select();
        $check_user_query_select->getLimit()->set(1);
        $check_user_query_select_return = 'RETURN 1';
        $check_user_query_select->getReturn()->setPlain($check_user_query_select_return);
        $check_user_query_select_statement = $check_user_query_select->getStatement();
        $check_user_query_select_statement_exception_message = Language::translate($exception_message . $field_name, $destination->getField(Arango::KEY)->getValue());
        $check_user_query_select_statement->setExceptionMessage($check_user_query_select_statement_exception_message);
        $check_user_query_select_statement->setExpect(1)->setHideResponse(true);
        array_push($preliminary, $check_user_query_select_statement);

        if ($field_name !== 'group') continue;

        $clone = clone $target;
        $clone_query = ArangoDB::start($clone);
        
        $group_loop = new Group();
        $group_loop->getField(Arango::KEY)->setProtected(false)->setValue($group_key_value);

        $clone_edge = $clone->useEdge(GroupToGroup::getName());
        $clone_edge->vertex($group_loop);
        $clone_edge->branch()->vertex()->useEdge(GroupToGroup::getName(), $clone_edge);

        $clone_query_select = $clone_query->select();
        $clone_query_select->getLimit()->set(1);
        $clone_query_select_return = 'RETURN 1';
        $clone_query_select->getReturn()->setPlain($clone_query_select_return);
        $clone_query_select_statement = $clone_query_select->getStatement();
        $clone_query_select_statement_exception_message = $exception_message . 'loop';
        $clone_query_select_statement_exception_message = Language::translate($clone_query_select_statement_exception_message, $destination->getField(Arango::KEY)->getValue());
        $clone_query_select_statement->setExceptionMessage($clone_query_select_statement_exception_message);
        $clone_query_select_statement->setExpect(0)->setHideResponse(true);
        array_push($preliminary, $clone_query_select_statement);
    }
}

if (!!$errors = $group->checkRequired()->getAllFieldsWarning()) {
    Language::dictionary(__file__);
    $notice = __namespace__ . '\\' . 'notice';
    $notice = Language::translate($notice);
    Output::concatenate('notice', $notice);
    Output::concatenate('errors', $errors);
    Output::print(false);
}

if (empty($preliminary)) {
    $group_clone_query_update_transaction->pushStatementsPreliminary(...$remove);
    $group_clone_query_update_transaction->openCollectionsWriteMode(...$writer);
    if (null !== $group_clone_query_update_transaction->commit()) Output::print(true);
    Output::print(false);
}

$group_query = ArangoDB::start($group);
$group_query_insert = $group_query->insert();
$group_query_insert->setActionOnlyEdges(true);
$group_query_insert->pushStatementsPreliminary(...$preliminary, ...$remove);
$group_query_insert->pushTransactionsFinal($group_clone_query_update_transaction);
$group_query_insert_transaction = $group_query_insert->getTransaction();
$group_query_insert_transaction->openCollectionsWriteMode(...$writer);
$group_query_insert_transaction_response = $group_query_insert_transaction->commit();
if (null === $group_query_insert_transaction_response
    || empty($group_query_insert_transaction_response)) Output::print(false);

$group = new Group();
$group->setSafeMode(false)->setReadMode(true);
$group_value = reset($group_query_insert_transaction_response);
$group->setFromAssociative($group_value, $group_value);
$group_value = $group->getAllFieldsValues(false, false);
Output::concatenate(Output::APIDATA, $group_value);
Output::print(true);
