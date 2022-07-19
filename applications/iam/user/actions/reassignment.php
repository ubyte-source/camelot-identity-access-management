<?PHP

namespace applications\iam\user\actions;

use Knight\armor\Output;
use Knight\armor\Language;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\Statement;
use ArangoDB\operations\common\Choose;
use ArangoDB\entity\Edge;
use ArangoDB\entity\common\Arango;

use applications\iam\user\database\Vertex as User;
use applications\iam\user\database\edges\UserToGroup;
use applications\iam\user\database\edges\UserToPolicy;
use applications\iam\user\database\edges\UserToApplication;
use applications\iam\group\database\Vertex as Group;
use applications\iam\group\database\edges\GroupToUser;
use applications\iam\group\database\edges\GroupToGroup;
use applications\iam\group\database\edges\GroupToPolicy;
use applications\iam\policy\database\Vertex as Policy;
use applications\iam\policy\database\edges\PolicyToApplication;
use applications\iam\user\map\Assignment;
use applications\iam\user\map\assignment\Group as OMAGroup;
use applications\iam\user\map\assignment\Policy as OMAPolicy;
use applications\sso\application\database\edges\ApplicationToPolicy;

Policy::mandatories('iam/user/action/policies');

$user = User::login();
$user_query = ArangoDB::start($user);

$user_to_policy = $user->useEdge(UserToPolicy::getName());
$user_to_policy_clone = clone $user_to_policy;
$user_to_policy->getField('reassignment')->setProtected(false)->setRequired(true)->setValue(true);

$user_to_group = $user->useEdge(UserToGroup::getName());

$user_query_select = $user_query->select();
$user_query_select_edge = $user_query_select->getPointer(Choose::EDGE);
$user_query_select_vertex = $user_query_select->getPointer(Choose::VERTEX);

$policy = $user_to_policy->vertex();

$user_to_application_policy = $user->useEdge(UserToApplication::getName())->vertex()->useEdge(ApplicationToPolicy::getName())->vertex($policy);
$user_to_group_vertex = $user_to_group->vertex();
$user_to_group_vertex_collection_name = $user_to_group_vertex->getCollectionName();
$user_to_group_vertex_policy = $user_to_group_vertex->useEdge(GroupToPolicy::getName(), $user_to_policy_clone)->vertex($policy);
$user_to_group_vertex_to_group_policy = $user_to_group_vertex->useEdge(GroupToGroup::getName())->setForceDirection(Edge::INBOUND)->vertex()->useEdge(GroupToPolicy::getName(), $user_to_policy_clone)->vertex($policy);
$user_query_select->pushEntitiesUsingOr($user_to_group_vertex_policy, $user_to_group_vertex_to_group_policy, $user_to_application_policy);

$policy = new Policy();
$policy_query = ArangoDB::start($policy);
$policy_to_application = $policy->useEdge(PolicyToApplication::getName());
$policy_to_application_collection = $policy_to_application->getCollectionName();

$policy_query_select = $policy_query->select();
$policy_query_select->useWith(false);
$policy->getField(Arango::ID)->setProtected(false)->setValue($user_query_select_vertex);

$policy_query_select->pushStatementSkipValues($user_query_select_vertex);
$policy_query_select_return = 'RETURN' . chr(32) . $policy_query_select->getPointer(Choose::VERTEX);
$policy_query_select->getReturn()->setPlain($policy_query_select_return);
$policy_query_select->getLimit()->set(1);
$policy_query_select_statement_query = $policy_query_select->getStatement()->getQuery();
$policy_query_select_statement_query_with = $policy_query_select->getWithCollectionsParsed();

$user_query_select_return = new Statement();
$user_query_select_return->append('LET application = FIRST(' . $policy_query_select_statement_query . ')');
$user_query_select_return->append('LET application_manager = IS_SAME_COLLECTION(' . $policy_to_application_collection . chr(44) . chr(32) . $user_query_select_edge . ')');
$user_query_select_return->append('LET groups = (', false);
$user_query_select_return->append('FOR o IN');
$user_query_select_return->append($user_query_select->getPointer(Choose::TRAVERSAL_VERTEX));
$user_query_select_return->append('FILTER IS_SAME_COLLECTION(' . $user_to_group_vertex_collection_name . chr(44) . chr(32) . 'o)');

$last = array_keys(OMAGroup::RESPONSE_FIELDS_NAME);
$last = end($last);
$user_query_select_return->append('RETURN {', false);
foreach (OMAGroup::RESPONSE_FIELDS_NAME as $i => $field_name) {
    $user_query_select_return->append($field_name . ': o' . chr(46) . $field_name, false);
    if ($last !== $i) $user_query_select_return->append(chr(44));
}
$user_query_select_return->append('})');
$user_query_select_return->append('LET grouping = (', false);
$user_query_select_return->append('FOR o IN groups');

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
$group_management_query_select_statement_query = $group_management_query_select_statement->getQuery();

$user_query_select_return->append('LET admin = FIRST(', false);
$user_query_select_return->append($group_management_query_select_statement_query, false);
$user_query_select_return->append(')');
$user_query_select_return->append('FILTER admin != null');
$user_query_select_return->append('RETURN o', false);
$user_query_select_return->append(')');
$user_query_select_return->append('LET results = MERGE(' . $user_query_select_edge . chr(44) . chr(32) . $user_query_select_vertex . ')');
$user_query_select_return->append('RETURN DISTINCT {', false);
$user_query_select_return->append('policy: {', false);

$allow = $user_to_policy->getField('allow')->getName();
$reassignment = $user_to_policy->getField('reassignment')->getName();

foreach (OMAPolicy::RESPONSE_FIELDS_NAME as $field_name) {
    if ($field_name == $allow
        || $field_name == $reassignment) continue;

    $user_query_select_return->append($field_name . ': results.' . $field_name, false);
    $user_query_select_return->append(chr(44));
}

$user_query_select_return->append($allow . ':');
$user_query_select_return->append($user_query_select_edge . '.' . $allow . ' == null');
$user_query_select_return->append('? application_manager : ' . $user_query_select_edge . '.' . $allow, false);
$user_query_select_return->append(chr(44));
$user_query_select_return->append($reassignment . ':');
$user_query_select_return->append($user_query_select_edge . '.' . $reassignment . ' == null');
$user_query_select_return->append('? application_manager : ' . $user_query_select_edge . '.' . $reassignment, false);
$user_query_select_return->append(chr(44));
$user_query_select_return->append('application: application', false);
$user_query_select_return->append('}', false);
$user_query_select_return->append(chr(44));
$user_query_select_return->append('path: grouping', false);
$user_query_select_return->append('}', false);
$user_query_select_return->addBindFromStatements($group_management_query_select_statement);

$user_query_select->getReturn()->setFromStatement($user_query_select_return);
$user_query_select->pushWithCollection(...$policy_query_select_statement_query_with);

$user_query_select_response = new Assignment($user_query_select);
Output::concatenate(Output::APIDATA, $user_query_select_response->getResponse());
Output::print(true);
