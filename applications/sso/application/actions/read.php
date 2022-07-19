<?PHP

namespace applications\sso\application\actions;

use Knight\armor\Output;
use Knight\armor\Request;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\Statement;
use ArangoDB\operations\common\Choose;

use applications\iam\user\database\Vertex as User;
use applications\iam\user\database\edges\UserToApplication;
use applications\iam\policy\database\Vertex as Policy;
use applications\sso\application\forms\Application;

Policy::mandatories('sso/application/action/read');

$user = User::login();
$user_query = ArangoDB::start($user);

$post = Request::post();
$post = array_filter((array)$post, function ($item) {
    return !is_string($item) && !is_numeric($item) || strlen((string)$item);
});

$application = $user->useEdge(UserToApplication::getName())->vertex();
$application->setSafeMode(false);
$application_fields = $application->getFields();
foreach ($application_fields as $field) {
    $name = $field->getName();
    if (false === array_key_exists($name, $post)
        || $field->getProtected()) continue;

    $application->getField($name)->setValue($post[$name]);
}

if (Policy::check('sso/application/action/read/all')) $user_query = ArangoDB::start($application);

$user_query_select = $user_query->select();

$or = Request::get('force-use-or');
$or = filter_var($or, FILTER_VALIDATE_BOOLEAN);
if (true === $or && Policy::check('sso/application/action/read/or')) $user_query_select->pushEntitiesUsingOr($application);

if (!!$count_offset = Request::get('offset')) $user_query_select->getLimit()->setOffset($count_offset);
if (!!$count = Request::get('count')) $user_query_select->getLimit()->set($count);

$user_query_select_vertex = $user_query_select->getPointer(Choose::VERTEX);
$user_query_select_return_statement = new Statement();

$application_unique = $application->getAllFieldsUniqueGroups();
foreach ($application_unique as $group) if (1 === count($group)) {
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

$user_query_select_return_statement->append('RETURN' . chr(32) . $user_query_select_vertex);
$user_query_select->getReturn()->setFromStatement($user_query_select_return_statement);
$user_query_select_response = $user_query_select->run();
if (null === $user_query_select_response) Output::print(false);

$application = new Application();
$application->setSafeMode(false)->setReadMode(true);

array_walk($user_query_select_response, function (&$value) use ($application) {
    $clone = clone $application;
    $clone->setFromAssociative($value, $value);
    $value = $clone->getAllFieldsValues(false, false);
});

Output::concatenate(Output::APIDATA, $user_query_select_response);
Output::print(true);
