<?PHP

namespace applications\iam\group\actions;

use Knight\armor\Output;
use Knight\armor\Language;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\Statement;
use ArangoDB\operations\common\Choose;
use ArangoDB\entity\Edge;
use ArangoDB\entity\Vertex;
use ArangoDB\entity\common\Arango;

use applications\iam\user\database\Vertex as User;
use applications\iam\user\database\edges\UserToGroup;
use applications\iam\user\database\edges\UserToUser;
use applications\iam\group\database\Vertex as Group;
use applications\iam\group\database\edges\GroupToUser;
use applications\iam\group\database\edges\GroupToGroup;
use applications\iam\policy\database\Vertex as Policy;
use applications\iam\group\forms\Matrioska;

use extensions\Navigator;

Policy::mandatories('iam/group/action/detail');

$user = User::login();
$user_query = ArangoDB::start($user);

$user_to_group = $user->useEdge(UserToGroup::getName());
$user_to_group->getField('admin')->setProtected(false)->setRequired(true)->setValue(true);

$group = $user_to_group->vertex();
$group_fields = $group->getFields();
foreach ($group_fields as $field) $field->setProtected(true);

$group_key_value = parse_url($_SERVER[Navigator::REQUEST_URI], PHP_URL_PATH);
$group_key_value = basename($group_key_value);

$group->getField(Arango::KEY)->setProtected(false)->setRequired(true)->setValue($group_key_value);

if (!!$errors = $group->checkRequired()->getAllFieldsWarning()) {
    Language::dictionary(__file__);
    $notice = __namespace__ . '\\' . 'notice';
    $notice = Language::translate($notice);
    Output::concatenate('notice', $notice);
    Output::concatenate('errors', $errors);
    Output::print(false);
}

$user_query_select = $user_query->select();
$user_query_select_vertex = $user_query_select->getPointer(Choose::VERTEX);

$user_follow = new Group();
$user_follow->getField(Arango::KEY)->setProtected(false)->setValue($group_key_value);

$user_follow_query = ArangoDB::start($user_follow);

$user_follow_user = $user_follow->useEdge(GroupToUser::getName())->vertex();
$user_follow_user_branch = $user_follow_user->useEdge(UserToUser::getName())->setForceDirection(Edge::INBOUND);
$user_follow_user_branch->vertex($user);
$user_follow_user_branch->branch()->vertex()->useEdge(UserToUser::getName())->setForceDirection(Edge::INBOUND)->vertex($user);

$user_follow_query_select = $user_follow_query->select();
$user_follow_query_select->useWith(false);
$user_follow_query_select_traversal_edge = $user_follow_query_select->getPointer(Choose::TRAVERSAL_EDGE);
$user_follow_query_select_traversal_vertex = $user_follow_query_select->getPointer(Choose::TRAVERSAL_VERTEX);
$user_follow_query_select_return = new Statement();
$user_follow_query_select_return->append('LET users = SLICE(' . $user_follow_query_select_traversal_vertex . ', 1, 1)');
$user_follow_query_select_return->append('LET edges_first = FIRST(' . $user_follow_query_select_traversal_edge . ')');
$user_follow_query_select_return->append('LET admin = edges_first.' . $user_to_group->getField('admin')->getName() . ' == true ? "share" : "users"');
$user_follow_query_select_return->append('LET users_first = FIRST(users)');
$user_follow_query_select_return->append('FILTER users_first._key != $0');
$user_follow_query_select_return->append('COLLECT is = admin INTO users_grouping = users');
$user_follow_query_select_return->append('RETURN {[is]: FLATTEN(users_grouping)}');
$user_follow_query_select->getReturn()->setFromStatement($user_follow_query_select_return, $user->getField(Arango::KEY)->getValue());
$user_follow_query_select_statement = $user_follow_query_select->getStatement();
$user_follow_query_select_statement_query = $user_follow_query_select_statement->getQuery();

$group = new Group();
$group->getField(Arango::KEY)->setProtected(false)->setValue($group_key_value);

$group_to_group_query = ArangoDB::start($group);
$group_to_group = $group->useEdge(GroupToGroup::getName())->vertex();
$group_to_group_to_user = $group_to_group->useEdge(GroupToUser::getName());
$group_to_group_to_user->getField('admin')->setProtected(false)->setValue(true);

$group_to_group_to_user->vertex($user);
$group_to_group_query_select = $group_to_group_query->select();
$group_to_group_query_select->useWith(false);
$group_to_group_query_select_traversal_vertex = $group_to_group_query_select->getPointer(Choose::TRAVERSAL_VERTEX);
$group_to_group_query_select_return = new Statement();
$group_to_group_query_select_return->append('LET vertices = SLICE(' . $group_to_group_query_select_traversal_vertex . ', 1, 1)');
$group_to_group_query_select_return->append('LET user = FIRST(vertices)');
$group_to_group_query_select_return->append('FILTER user._key != $0');
$group_to_group_query_select_return->append('RETURN DISTINCT vertices');
$group_to_group_query_select->getReturn()->setFromStatement($group_to_group_query_select_return, $user->getField(Arango::KEY)->getValue());
$group_to_group_query_select_statement = $group_to_group_query_select->getStatement();
$group_to_group_query_select_statement_query = $group_to_group_query_select_statement->getQuery();

$owner = $group->getField(Vertex::OWNER)->getName();

$user_query_select_statement_return = new Statement();
$user_query_select_statement_return->addBindFromStatements($group_to_group_query_select_statement, $user_follow_query_select_statement);
$user_query_select_statement_return->append('LET users = MERGE(' . $user_follow_query_select_statement_query . ')');
$user_query_select_statement_return->append('LET under = FLATTEN(' . $group_to_group_query_select_statement_query . ')');
$user_query_select_statement_return->append('LET owner = DOCUMENT(' . User::COLLECTION . ',' . chr(32) . $user_query_select_vertex . '.' . $owner . ')');
$user_query_select_statement_return->append('LET group = MERGE(' . $user_query_select_vertex . ', {' . $owner . ': owner})');
$user_query_select_statement_return->append('RETURN MERGE(group, {share: ' . $user_query_select_vertex . '.' . $owner . ' == $0 ? users.share : [], group: under, users: users.users})', false);

$user_query_select->getReturn()->setFromStatement($user_query_select_statement_return, $user->getField(Arango::KEY)->getValue());
$user_query_select->getLimit()->set(1);

$user_query_select_response = $user_query_select->run();
if (null === $user_query_select_response
    || empty($user_query_select_response)) Output::print(false);

$matrioska = new Matrioska();
$matrioska->setSafeMode(false)->setReadMode(true);
$matrioska_value = reset($user_query_select_response);
$matrioska->setFromAssociative($matrioska_value, $matrioska_value);
$matrioska_value = $matrioska->getAllFieldsValues(false, false);

Output::concatenate(Output::APIDATA, $matrioska_value);
Output::print(true);
