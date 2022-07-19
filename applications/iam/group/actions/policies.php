<?PHP

namespace applications\iam\group\actions;

use Knight\armor\Output;
use Knight\armor\Language;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\Statement;
use ArangoDB\operations\common\Choose;
use ArangoDB\entity\Vertex;
use ArangoDB\entity\Edge;
use ArangoDB\entity\common\Arango;

use applications\iam\user\database\Vertex as User;
use applications\iam\user\database\edges\UserToGroup;
use applications\iam\group\database\Vertex as Group;
use applications\iam\group\database\edges\GroupToPolicy;
use applications\iam\group\database\edges\GroupToGroup;
use applications\iam\group\database\edges\GroupToUser;
use applications\iam\policy\database\Vertex as Policy;
use applications\iam\policy\database\edges\PolicyToApplication;
use applications\iam\policy\database\edges\PolicyToUser;
use applications\iam\policy\database\edges\PolicyToGroup;
use applications\sso\application\database\edges\ApplicationToUser;
use applications\iam\user\map\Assignment;
use applications\iam\user\map\assignment\Group as OMAGroup;
use applications\iam\user\map\assignment\Policy as OMAPolicy;

use extensions\Navigator;

Policy::mandatories('iam/group/action/policies');

$group_key_value = parse_url($_SERVER[Navigator::REQUEST_URI], PHP_URL_PATH);
$group_key_value = basename($group_key_value);

$user = User::login();
$user_query = ArangoDB::start($user);

$group = $user->useEdge(UserToGroup::getName())->vertex();
$group_fields = $group->getFields();
foreach ($group_fields as $field) $field->setProtected(true);

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
$user_query_select->useWith(false);
$user_query_select_return = 'RETURN 1';
$user_query_select->getReturn()->setPlain($user_query_select_return);
$user_query_select->getLimit()->set(1);
$user_query_select_statement = $user_query_select->getStatement();
$user_query_select_statement_query = $user_query_select_statement->getQuery();
$user_query_select_statement_query_with = $user_query_select->getWithCollectionsParsed();

$group_to_policy = $group->useEdge(GroupToPolicy::getName());
$group->useEdge(GroupToGroup::getName())->setForceDirection(Edge::INBOUND)->vertex()->useEdge(GroupToPolicy::getName(), $group_to_policy);

$group_query = ArangoDB::start($group);
$group_query_select = $group_query->select();
$group_query_select_edge = $group_query_select->getPointer(Choose::EDGE);
$group_query_select_vertex = $group_query_select->getPointer(Choose::VERTEX);

$group_query_select_return = new Statement();
$group_query_select_return->append('LET hierarchy = FIRST(' . $user_query_select_statement_query . ')');
$group_query_select_return->append('FILTER null != hierarchy');
$group_query_select_return->append('LET groups = (', false);
$group_query_select_return->append('FOR o IN');
$group_query_select_return->append($group_query_select->getPointer(Choose::TRAVERSAL_VERTEX));
$group_query_select_return->append('FILTER IS_SAME_COLLECTION(' . Group::COLLECTION . chr(44) . chr(32) .  'o)');
$group_query_select_return->append('FILTER $0 != o' . chr(46) . $group->getField(Arango::KEY)->getName());
$group_query_select_return->append('RETURN o', false);
$group_query_select_return->append(')');
$group_query_select_return->append('LET grouping = (', false);
$group_query_select_return->append('FOR o IN groups');

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

$group_query_select_return->append('LET y = FIRST(' . $group_management_query_select_statement_query . ')', false);
$group_query_select_return->append('LET admin = true == ' . $group_query_select_edge . chr(46) . $group_management_to_user->getField('admin')->getName());
$group_query_select_return->append('LET owner = $1 == ' . $group_query_select_vertex . chr(46) . $group_management->getField(Vertex::OWNER)->getName());
$group_query_select_return->append('LET manager = owner OR !admin && y != null ? false : true');
$group_query_select_return->append('RETURN DISTINCT {', false);

foreach (OMAGroup::RESPONSE_FIELDS_NAME as $field_name) {
    $group_query_select_return->append($field_name . ': o.' . $field_name, false);
    $group_query_select_return->append(chr(44));
}

$group_query_select_return->append('manager: manager', false);
$group_query_select_return->append('})');
$group_query_select_return->append('LET e = COUNT(grouping)');
$group_query_select_return->append('FILTER 0 < e OR COUNT(groups) == e AND e == 0');

$policy = new Policy();
$policy_query = ArangoDB::start($policy);
$policy_field_id_name = $group_query_select_vertex . chr(46) . $policy->getField(Arango::ID)->getName();
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

$group_query_select_return->append('LET application = FIRST(' . $policy_query_select_statement_query . ')');

$policy = new Policy();
$policy_query = ArangoDB::start($policy);
$policy_field_id_name = $group_query_select_vertex . chr(46) . $policy->getField(Arango::ID)->getName();
$policy->getField(Arango::ID)->setSafeModeDetached(false)->setValue($policy_field_id_name);

$policy_to_user = $policy->useEdge(PolicyToUser::getName());
$policy_to_user->vertex($user);

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
$policy_query_select_return = 'RETURN' . chr(32) . $policy_query_select_vertex;
$policy_query_select->getReturn()->setPlain($policy_query_select_return);
$policy_query_select_statement = $policy_query_select->getStatement();
$policy_query_select_statement_query = $policy_query_select_statement->getQuery();

$group_query_select_return->append('LET hierarchies = FIRST(' . $policy_query_select_statement_query . ')');
$group_query_select_return->append('LET application_manager = IS_SAME_COLLECTION(' . $policy_to_application_collection . chr(44) . chr(32) . $group_query_select_edge . ')');
$group_query_select_return->append('LET manager = application_manager OR hierarchies == null');
$group_query_select_return->append('LET result = MERGE(' . $group_query_select_edge . chr(44) . chr(32) . $group_query_select_vertex . ')');
$group_query_select_return->append('SORT e ASC');
$group_query_select_return->append('LET identifier = PARSE_IDENTIFIER(' . $group_query_select_edge . ')');
$group_query_select_return->append('LET policy = {', false);

$allow = $policy_to_user->getField('allow')->getName();
$reassignment = $policy_to_user->getField('reassignment')->getName();

foreach (OMAPolicy::RESPONSE_FIELDS_NAME as $field_name) {
    if ($field_name == $allow
        || $field_name == $reassignment) continue;

    $group_query_select_return->append($field_name . ': result' . chr(46) . $field_name, false);
    $group_query_select_return->append(chr(44));
}

$group_query_select_return->append($allow . chr(58));
$group_query_select_return->append('manager ? null : ' . $group_query_select_edge . chr(46) . $allow, false);
$group_query_select_return->append(chr(44));
$group_query_select_return->append($reassignment . chr(58));
$group_query_select_return->append('manager ? null : ' . $group_query_select_edge . chr(46) . $reassignment, false);
$group_query_select_return->append(chr(44));
$group_query_select_return->append('manager: manager', false);
$group_query_select_return->append(chr(44));
$group_query_select_return->append('type: identifier.collection', false);
$group_query_select_return->append(chr(44));
$group_query_select_return->append('application: application', false);
$group_query_select_return->append('}', false);
$group_query_select_return->append('LET path = REVERSE(grouping)');
$group_query_select_return->append('COLLECT policy_collect = policy INTO paths = path');
$group_query_select_return->append('LET path = LAST(paths)');
$group_query_select_return->append('SORT COUNT(path) ASC');
$group_query_select_return->append('RETURN DISTINCT {policy: policy_collect, path: path}');
$group_query_select_return->addBindFromStatements($user_query_select_statement, $group_management_query_select_statement, $policy_query_select_statement);

$group_query_select->getReturn()->setFromStatement($group_query_select_return, $group_key_value,  $user->getField(Arango::KEY)->getValue());
$group_query_select->pushWithCollection(...$user_query_select_statement_query_with, ...$policy_query_select_statement_query_with);

$group_query_select_response = new Assignment($group_query_select);
Output::concatenate(Output::APIDATA, $group_query_select_response->getResponse());
Output::print(true);
