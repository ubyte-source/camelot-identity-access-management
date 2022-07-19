<?PHP

namespace applications\iam\user\actions;

use Knight\armor\Output;
use Knight\armor\Request;
use Knight\armor\Language;
use Knight\armor\Navigator;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\Statement;
use ArangoDB\operations\common\Choose;
use ArangoDB\entity\Edge;
use ArangoDB\entity\common\Arango;

use applications\iam\user\database\Vertex as User;
use applications\iam\user\database\edges\UserToUser;
use applications\iam\user\database\edges\UserToPolicy;
use applications\iam\user\database\edges\UserToGroup;
use applications\iam\user\database\edges\UserToApplication;
use applications\iam\group\database\edges\GroupToUser;
use applications\iam\group\database\edges\GroupToGroup;
use applications\iam\group\database\edges\GroupToPolicy;
use applications\iam\policy\database\Vertex as Policy;
use applications\iam\policy\database\edges\PolicyToApplication;
use applications\iam\policy\database\edges\PolicyToUser;
use applications\iam\policy\database\edges\PolicyToGroup;
use applications\sso\application\database\edges\ApplicationToPolicy;

const UP = 1;
const DOWN = 0;

Policy::mandatories('iam/user/action/escalation');

$uri = parse_url($_SERVER[Navigator::REQUEST_URI], PHP_URL_PATH);
$uri = trim($uri, chr(47));
$uri = explode(chr(47), $uri);
$uri = array_filter($uri, 'strlen');
$uri = array_values($uri);
$uri = array_slice($uri, 1 + Navigator::getDepth());

if (2 > count($uri)) Output::print(false);

$user = User::login();
$user_query = ArangoDB::start($user);

$escalation_direction = Request::get('direction');
$escalation_direction = null === $escalation_direction || $escalation_direction !== 'down' ? UP : DOWN;

$user_parent_edge = $user->useEdge(UserToUser::getName());
if (UP === $escalation_direction) $user_parent_edge->setForceDirection(Edge::INBOUND);
$user_parent_edge->branch()->vertex()->useEdge(UserToUser::getName(), $user_parent_edge);

$user_query = $user_query->select();
$user_query->useWith(false);

$user_query_return = new Statement();
$user_query_return->append('LET u = (', false);
$user_query_return->append('FOR i IN');
$user_query_return->append('SLICE'. chr(40));
$user_query_return->append($user_query->getPointer(Choose::TRAVERSAL_VERTEX));
$user_query_return->append(chr(44) . chr(32) . chr(49) . chr(41));

$inside = new User();
$inside_id = 'i' . chr(46) . $inside->getField(Arango::ID)->getName();
$inside->getField(Arango::ID)->setProtected(false)->setValue($inside_id);
$inside_query = ArangoDB::start($inside);

$inside_to_policy = $inside->useEdge(UserToPolicy::getName());
$inside_to_policy->getField('allow')->setProtected(false)->setValue(true);

$policy = $inside_to_policy->vertex();
$policy_fields = $policy->getFields();
foreach ($policy_fields as $field) $field->setProtected(true);

$application = $policy->useEdge(PolicyToApplication::getName())->vertex();
$application_fields = $application->getFields();
foreach ($application_fields as $field) $field->setProtected(true);

$application_field_basename = $application->getField('basename');
$application_field_basename->setProtected(false)->setRequired(true);
$application_field_basename_value = array_shift($uri);
$application_field_basename->setValue($application_field_basename_value);

$policy_field_route = $policy->getField('route');
$policy_field_route->setProtected(false)->setRequired(true);
$policy_field_route_value = implode(Policy::SEPARATOR, $uri);
$policy_field_route->setValue($policy_field_route_value);

$policy_route = $application_field_basename->getValue() . Policy::SEPARATOR . $policy_field_route->getValue();
$policy_route = UserToPolicy::getPolicies($policy_route);

if (Request::get('skip') !== 'me' && false === empty($policy_route)) {
    $user_query_response = array();
    $user_query_response_whoami = User::getWhoami(true);
    $user_query_response_whoami = $user_query_response_whoami->getAllFieldsValues(false, false);
    array_push($user_query_response, $user_query_response_whoami);
    Output::concatenate(Output::APIDATA, $user_query_response);
    Output::print(true);
}

$inside->useEdge(UserToApplication::getName())->vertex($application);
$group = $inside->useEdge(UserToGroup::getName())->vertex();
$group->useEdge(GroupToGroup::getName())->setForceDirection(Edge::INBOUND)->vertex()->useEdge(GroupToPolicy::getName(), $inside_to_policy)->vertex($policy, true);
$group->useEdge(GroupToPolicy::getName(), $inside_to_policy)->vertex($policy, true);

$inside_query_select = $inside_query->select();
$inside_query_select->useWith(false);
$inside_query_select->getLimit()->set(1);
$inside_query_select->pushStatementSkipValues($inside_id);
$inside_query_select_statement = $inside_query_select->getStatement();
$inside_query_select_with = $inside_query_select->getWithCollectionsParsed();

$user_query_return_check = $inside_query_select_statement->getQuery();
$user_query_return->append('LET hierarchy = FIRST(' . $user_query_return_check . ')');
$user_query_return->append('FILTER null != hierarchy');
$user_query_return->append('RETURN i', false);
$user_query_return->append(')');
$user_query_return->append('FILTER u != null');
$user_query_return->append('RETURN DISTINCT FIRST(u)');
$user_query_return->addBindFromStatements($inside_query_select_statement);
$user_query->getReturn()->setFromStatement($user_query_return);
$user_query->pushWithCollection(...$inside_query_select_with);
$user_query_statement = $user_query->getStatement();
$user_query_with = $user_query->getWithCollectionsParsed();

$user_query_response = $user_query->run();
if (null !== $user_query_response) {
    $user_query_response = array_values($user_query_response);
    Output::concatenate(Output::APIDATA, $user_query_response);
    Output::print(true);
}

Policy::mandatories('iam/user/action/escalation/discovery');

$application_query = ArangoDB::start($application);
$application_policy = $application->useEdge(ApplicationToPolicy::getName())->vertex($policy);
$application_policy_user = $application_policy->useEdge(PolicyToUser::getName(), $inside_to_policy)->branch()->vertex();
$application_policy_user_to_user = $application_policy_user->useEdge(UserToUser::getName());
if (UP === $escalation_direction) $application_policy_user_to_user->setForceDirection(Edge::INBOUND);

$group = $application_policy->useEdge(PolicyToGroup::getName(), $inside_to_policy)->vertex();
$group_to_group_to_user = $group->useEdge(GroupToGroup::getName())->setForceDirection(Edge::INBOUND)->vertex()->useEdge(GroupToUser::getName());
if (UP === $escalation_direction) $group_to_group_to_user->setForceDirection(Edge::INBOUND);

$application_query_select = $application_query->select();
$application_query_select->useWith(false);

$discovery = new Statement();
$discovery_with_collections = $application_query_select->getWithCollectionsParsed();
$discovery_with_collections = implode(chr(44) . chr(32), $discovery_with_collections);
$discovery->append('WITH');
$discovery->append($discovery_with_collections);
$discovery->append('LET a = (', false);

$application_query_select_return = new Statement();
$application_query_select_return->append('LET u = (', false);
$application_query_select_return->append('FOR i IN');
$application_query_select_return->append($application_query_select->getPointer(Choose::TRAVERSAL_VERTEX));
$application_query_select_return->append('FILTER IS_SAME_COLLECTION(' . User::COLLECTION . chr(44) . chr(32) . 'i)');
$application_query_select_return->append('RETURN i', false);
$application_query_select_return->append(')');
$application_query_select_return->append('LET f = FIRST(u)');
$application_query_select_return->append('COLLECT y = f INTO w = u[*]._key');
$application_query_select_return->append('LET k = FLATTEN(w)');
$application_query_select_return->append('LET h = UNIQUE(k)');
$application_query_select_return->append('COLLECT i = COUNT(h) - 1 INTO g = y');
$application_query_select_return->append('RETURN {i, g}', false);
$application_query_select->getReturn()->setFromStatement($application_query_select_return);

$application_query_select_statement = $application_query_select->getStatement();
$discovery->append($application_query_select_statement->getQuery(), false);
$discovery->append(')');
$discovery->append('LET m = MAX(a[*].i)');
$discovery->append('LET f = FLATTEN(', false);
$discovery->append('FOR i IN a');
$discovery->append('FILTER i.i == m');
$discovery->append('RETURN i.g', false);
$discovery->append(')');
$discovery->append('RETURN f', false);
$discovery->addBindFromStatements($application_query_select_statement);

$user_query_response = $discovery->execute();
if (null !== $user_query_response) {
    $user_query_response = reset($user_query_response);
    Output::concatenate(Output::APIDATA, $user_query_response);
    Output::print(true);
}

Output::print(false);
