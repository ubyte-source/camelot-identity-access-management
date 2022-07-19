<?PHP

namespace applications\iam\user\actions;

use Knight\armor\Output;
use Knight\armor\output\Data;
use Knight\armor\Request;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\Statement;
use ArangoDB\operations\common\Choose;
use ArangoDB\entity\common\Arango;

use applications\iam\user\database\Vertex as User;
use applications\iam\user\database\edges\UserToUser;
use applications\iam\policy\database\Vertex as Policy;

Policy::mandatories('iam/user/action/read');

$user = User::login();
$user_query = ArangoDB::start($user);

$edge = $user->useEdge(UserToUser::getName());

$post = Request::post();
$post = array_filter((array)$post, function ($item) {
    return !is_string($item) && !is_numeric($item) || strlen((string)$item);
});

$user = $edge->vertex();
$user->setSafeMode(false);
$user_fields = $user->getFields();
foreach ($user_fields as $field) {
    $field_name = $field->getName();
    if (false === array_key_exists($field_name, $post)
        || $field->getProtected()) continue;

    $user->getField($field_name)->setValue($post[$field_name]);
}

$user_target = [];
array_push($user_target, $user);

if (Policy::check('iam/user/action/read/all')) {
    $user_query = ArangoDB::start($user);
} else {
    array_push($user_target, $user->useEdge(UserToUser::getName(), $edge)->vertex());
    array_push($user_target, $user->useEdge(UserToUser::getName())->vertex()->useEdge(UserToUser::getName(), $edge)->vertex());
}

$user_query_select = $user_query->select();

$or = Request::get('force-use-or');
$or = filter_var($or, FILTER_VALIDATE_BOOLEAN);
if (true === $or && Policy::check('iam/user/action/read/or')) $user_query_select->pushEntitiesUsingOr(...$user_target);

if (!!$count_offset = Request::get('offset')) $user_query_select->getLimit()->setOffset($count_offset);
if (!!$count = Request::get('count')) $user_query_select->getLimit()->set($count);

$user_query_select_vertex = $user_query_select->getPointer(Choose::VERTEX);
$user_query_select_return_statement = new Statement();

$user_unique = $user->getAllFieldsUniqueGroups();
foreach ($user_unique as $group) if (1 === count($group)) {
    $name = reset($group);
    $keys = Request::post($name);
    if (null === $keys
        || false === is_array($keys)
        || 0 === count($keys)) continue;

    $keys = array_values($keys);
    $keys_bound = $user_query_select_return_statement->bound(...$keys);
    $keys_bound = implode(chr(44) . chr(32), $keys_bound);

    $user_query_select_return_statement->append('FILTER POSITION([' . $keys_bound . ']' . chr(44) . chr(32) . $user_query_select_vertex . chr(46) . $name . ')');
    $user_query_select->getLimit()->set(count($keys));
}

if (false === Policy::check('iam/privilege/user/type')) {
    $type = $user->getField('type')->getName();
    $user_query_select_return_statement->append('FILTER' . chr(32) . $user_query_select_vertex . chr(46) . $type . chr(32) . '!=' . chr(32) . '$0', true, User::SERVICE);
}

$user_query_select_return_statement->append('RETURN' . chr(32) . $user_query_select_vertex);
$user_query_select->getReturn()->setFromStatement($user_query_select_return_statement);
$user_query_select_response = $user_query_select->run();
if (null === $user_query_select_response) Output::print(false);

$user = new User();
$user->setSafeMode(false)->setReadMode(true);

$response_only_fields = Data::only(Arango::KEY);
$response_only_fields = array_fill_keys($response_only_fields, null);
array_walk($user_query_select_response, function (&$value) use ($user, $response_only_fields) {
    $clone = clone $user;
    $clone->setFromAssociative($value, $value);
    $value = $clone->getAllFieldsValues(false, false);
    if (false === empty($response_only_fields)) $value = array_intersect_key($value, $response_only_fields);
});

Output::concatenate(Output::APIDATA, array_filter($user_query_select_response));
Output::print(true);
