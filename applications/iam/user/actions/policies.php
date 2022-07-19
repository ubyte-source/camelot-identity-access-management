<?PHP

namespace applications\iam\user\actions;

use Knight\armor\Output;
use Knight\armor\Language;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\Statement;
use ArangoDB\operations\common\Choose;
use ArangoDB\entity\Edge;
use ArangoDB\entity\Vertex;
use ArangoDB\entity\common\Arango;

use applications\iam\user\database\Vertex as User;
use applications\iam\user\database\edges\UserToUser;
use applications\iam\user\database\edges\UserToGroup;
use applications\iam\user\database\edges\UserToPolicy;
use applications\iam\user\database\edges\UserToApplication;
use applications\iam\group\database\Vertex as Group;
use applications\iam\group\database\edges\GroupToUser;
use applications\iam\group\database\edges\GroupToGroup;
use applications\iam\group\database\edges\GroupToPolicy;
use applications\iam\policy\database\Vertex as Policy;
use applications\iam\policy\database\edges\PolicyToUser;
use applications\iam\policy\database\edges\PolicyToGroup;
use applications\iam\policy\database\edges\PolicyToApplication;
use applications\iam\user\map\Assignment;
use applications\iam\user\map\assignment\Group as OMAGroup;
use applications\iam\user\map\assignment\Policy as OMAPolicy;
use applications\sso\application\database\edges\ApplicationToUser;
use applications\sso\application\database\edges\ApplicationToPolicy;

use extensions\Navigator;

Policy::mandatories('iam/user/action/policies');

$user_child_key = parse_url($_SERVER[Navigator::REQUEST_URI], PHP_URL_PATH);
$user_child_key = basename($user_child_key);

$user = User::login();
$user_query = ArangoDB::start($user);

$user_first = $user->useEdge(UserToUser::getName());
$user_first_vertex = $user_first->vertex();
$user_first_vertex_fields = $user_first_vertex->getFields();
foreach ($user_first_vertex_fields as $field) $field->setProtected(true);

$user_first_vertex->getField(Arango::KEY)->setProtected(false)->setRequired(true)->setValue($user_child_key);

if (!!$errors = $user_first_vertex->checkRequired()->getAllFieldsWarning()) {
    Language::dictionary(__file__);
    $notice = __namespace__ . '\\' . 'notice';
    $notice = Language::translate($notice);
    Output::concatenate('notice', $notice);
    Output::concatenate('errors', $errors);
    Output::print(false);
}

$user_first->branch()->vertex()->useEdge(UserToUser::getName())->vertex($user_first_vertex);

$user_query_select = $user_query->select();
$user_query_select->useWith(false);
$user_query_select_return = 'RETURN 1';
$user_query_select->getReturn()->setPlain($user_query_select_return);
$user_query_select->getLimit()->set(1);
$user_query_select_statement = $user_query_select->getStatement();
$user_query_select_statement_query = $user_query_select_statement->getQuery();
$user_query_select_statement_query_with = $user_query_select->getWithCollectionsParsed();

$user_child = clone $user_first_vertex;
$user_child_query = ArangoDB::start($user_child);
$user_child->useEdge(UserToPolicy::getName());
$user_child_to_group = $user_child->useEdge(UserToGroup::getName());
$user_child_to_group_vertex = $user_child_to_group->vertex();
$user_child_to_group_vertex->useEdge(GroupToGroup::getName())->setForceDirection(Edge::INBOUND)->vertex()->useEdge(GroupToPolicy::getName());
$user_child_to_group_vertex->useEdge(GroupToPolicy::getName());
$user_child->useEdge(UserToApplication::getName())->vertex()->useEdge(ApplicationToPolicy::getName())->vertex();

$user_child_query_select = $user_child_query->select();
$user_child_query_select_edge = $user_child_query_select->getPointer(Choose::EDGE);
$user_child_query_select_vertex = $user_child_query_select->getPointer(Choose::VERTEX);

$user_child_query_select_return = new Statement();
$user_child_query_select_return->append('LET hierarchy = FIRST(' . $user_query_select_statement_query . ')');
$user_child_query_select_return->append('FILTER null != hierarchy');
$user_child_query_select_return->append('LET groups = (', false);
$user_child_query_select_return->append('FOR o IN');
$user_child_query_select_return->append($user_child_query_select->getPointer(Choose::TRAVERSAL_VERTEX));
$user_child_query_select_return->append('FILTER IS_SAME_COLLECTION(' . Group::COLLECTION . chr(44) . chr(32) . 'o)');
$user_child_query_select_return->append('RETURN o', false);
$user_child_query_select_return->append(')');
$user_child_query_select_return->append('LET grouping = (', false);
$user_child_query_select_return->append('FOR o IN groups');

$group_management = new Group();
$group_management_query = ArangoDB::start($group_management);
$group_management_field_id_name = 'o' . chr(46) . $group_management->getField(Arango::ID)->getName();
$group_management->getField(Arango::ID)->setSafeModeDetached(false)->setValue($group_management_field_id_name);

$group_management_to_user = $group_management->useEdge(GroupToUser::getName());
$group_management_to_user->getField('admin')->setProtected(false)->setRequired(true)->setValue(true);
$group_management_to_user->vertex($user);

$group_management_query_select = $group_management_query->select();
$group_management_query_select->useWith(false);
$group_management_query_select->pushStatementSkipValues($group_management_field_id_name);
$group_management_query_select->getLimit()->set(1);
$group_management_query_select_return = 'RETURN 1';
$group_management_query_select->getReturn()->setPlain($group_management_query_select_return);
$group_management_query_select_statement = $group_management_query_select->getStatement();
$group_management_query_select_statement_aql = $group_management_query_select_statement->getQuery();

$owner = $group_management->getField(Vertex::OWNER)->getName();

$user_child_query_select_return->append('FILTER o' . chr(46) . $owner . chr(32) . '!= $0');
$user_child_query_select_return->append('LET y = FIRST(', false);
$user_child_query_select_return->append($group_management_query_select_statement_aql, false);
$user_child_query_select_return->append(')');
$user_child_query_select_return->append('LET child = IS_SAME_COLLECTION(' . $group_management_to_user->getCollectionName() . chr(44) . chr(32) . $user_child_query_select_edge . ')');
$user_child_query_select_return->append('LET admin = child AND true == ' . $user_child_query_select_edge . chr(46) . $group_management_to_user->getField('admin')->getName());
$user_child_query_select_return->append('LET owner = child AND $1 == ' . $user_child_query_select_vertex . chr(46) . $owner);
$user_child_query_select_return->append('LET manager = owner OR !admin && y != null ? false : true');
$user_child_query_select_return->append('RETURN DISTINCT {', false);

foreach (OMAGroup::RESPONSE_FIELDS_NAME as $field_name) {
    $user_child_query_select_return->append($field_name . ': o' . chr(46) . $field_name, false);
    $user_child_query_select_return->append(chr(44));
}

$user_child_query_select_return->append('manager: manager', false);
$user_child_query_select_return->append('})');
$user_child_query_select_return->append('LET e = COUNT(grouping)');
$user_child_query_select_return->append('FILTER 0 < e OR COUNT(groups) == e AND e == 0');

$policy = new Policy();
$policy_query = ArangoDB::start($policy);
$policy_field_id_name = $user_child_query_select_vertex . chr(46) . $policy->getField(Arango::ID)->getName();
$policy->getField(Arango::ID)->setSafeModeDetached(false)->setValue($policy_field_id_name);

$policy_to_application = $policy->useEdge(PolicyToApplication::getName());
$policy_to_application_collection = $policy_to_application->getCollectionName();

$policy_query_select = $policy_query->select();
$policy_query_select->useWith(false);
$policy_query_select->pushStatementSkipValues($policy_field_id_name);
$policy_query_select_vertex = $policy_query_select->getPointer(Choose::VERTEX);
$policy_query_select_return = 'RETURN' . chr(32) . $policy_query_select_vertex;
$policy_query_select->getReturn()->setPlain($policy_query_select_return);
$policy_query_select->getLimit()->set(1);
$policy_query_select_statement_query = $policy_query_select->getStatement()->getQuery();
$policy_query_select_statement_query_with = $policy_query_select->getWithCollectionsParsed();

$user_child_query_select_return->append('LET application = FIRST(' . $policy_query_select_statement_query . ')');

$policy = new Policy();
$policy_query = ArangoDB::start($policy);
$policy_field_id_name = $user_child_query_select_vertex . chr(46) . $policy->getField(Arango::ID)->getName();
$policy->getField(Arango::ID)->setSafeModeDetached(false)->setValue($policy_field_id_name);

$policy_to_user = $policy->useEdge(PolicyToUser::getName());
$policy_to_user_vertex = $policy_to_user->vertex($user);

$policy_to_group = $policy->useEdge(PolicyToGroup::getName());
$policy_to_group_vertex = $policy_to_group->vertex();
$policy_to_group_vertex->useEdge(GroupToUser::getName())->vertex($user);
$policy_to_group->branch()->vertex()->useEdge(GroupToGroup::getName())->vertex($policy_to_group_vertex, true);
$policy->useEdge(PolicyToApplication::getName())->vertex()->useEdge(ApplicationToUser::getName())->vertex($user);

$policy_query_select = $policy_query->select();
$policy_query_select->useWith(false);
$policy_query_select->pushStatementSkipValues($policy_field_id_name);
$policy_query_select->getLimit()->set(1);
$policy_query_select_vertex = $policy_query_select->getPointer(Choose::VERTEX);
$policy_query_select_return = 'RETURN ' . $policy_query_select_vertex;
$policy_query_select->getReturn()->setPlain($policy_query_select_return);
$policy_query_select_statement = $policy_query_select->getStatement();
$policy_query_select_statement_query = $policy_query_select_statement->getQuery();

$user_child_query_select_return->append('LET hierarchies = FIRST(' . $policy_query_select_statement_query . ')');
$user_child_query_select_return->append('LET application_manager = IS_SAME_COLLECTION(' . $policy_to_application_collection . chr(44) . chr(32) . $user_child_query_select_edge . ')');
$user_child_query_select_return->append('LET manager = application_manager OR hierarchies == null');
$user_child_query_select_return->append('LET result = MERGE(' . $user_child_query_select_edge . ', ' . $user_child_query_select_vertex . ')');
$user_child_query_select_return->append('SORT e ASC');
$user_child_query_select_return->append('LET identifier = PARSE_IDENTIFIER(' . $user_child_query_select_edge . ')');
$user_child_query_select_return->append('LET policy = {', false);

$allow = $policy_to_user->getField('allow')->getName();
$reassignment = $policy_to_user->getField('reassignment')->getName();

foreach (OMAPolicy::RESPONSE_FIELDS_NAME as $field_name) {
    if ($field_name == $allow
        || $field_name == $reassignment) continue;

    $user_child_query_select_return->append($field_name . ': result' . chr(46) . $field_name, false);
    $user_child_query_select_return->append(chr(44));
}

$user_child_query_select_return->append($allow . chr(58));
$user_child_query_select_return->append('manager ? null : ' . $user_child_query_select_edge . chr(46) . $allow, false);
$user_child_query_select_return->append(chr(44));
$user_child_query_select_return->append($reassignment . chr(58));
$user_child_query_select_return->append('manager ? null : ' . $user_child_query_select_edge . chr(46) . $reassignment, false);
$user_child_query_select_return->append(chr(44));
$user_child_query_select_return->append('manager: manager');
$user_child_query_select_return->append(chr(44));
$user_child_query_select_return->append('type: identifier.collection');
$user_child_query_select_return->append(chr(44));
$user_child_query_select_return->append('application: application', false);
$user_child_query_select_return->append('}', false);
$user_child_query_select_return->append('LET path = REVERSE(grouping)');
$user_child_query_select_return->append('COLLECT collector = policy INTO paths = path');
$user_child_query_select_return->append('LET path = LAST(paths)');
$user_child_query_select_return->append('SORT COUNT(path) ASC');
$user_child_query_select_return->append('RETURN DISTINCT {policy: collector, path: path}');
$user_child_query_select_return->addBindFromStatements($user_query_select_statement, $group_management_query_select_statement, $policy_query_select_statement);

$user_child_query_select->getReturn()->setFromStatement($user_child_query_select_return, $user_child_key, $user->getField(Arango::KEY)->getValue());
$user_child_query_select->pushWithCollection(...$user_query_select_statement_query_with, ...$policy_query_select_statement_query_with);

$user_child_query_select_response = new Assignment($user_child_query_select);
Output::concatenate(Output::APIDATA, $user_child_query_select_response->getResponse());
Output::print(true);
