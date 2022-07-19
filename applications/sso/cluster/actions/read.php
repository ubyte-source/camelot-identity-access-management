<?PHP

namespace applications\sso\cluster\actions;

use Knight\armor\Output;
use Knight\armor\Request;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\operations\common\Choose;

use applications\iam\user\database\Vertex as User;
use applications\iam\user\database\edges\UserToCluster;
use applications\iam\policy\database\Vertex as Policy;
use applications\sso\cluster\forms\Cluster;

Policy::mandatories('sso/cluster/action/read');

$post = Request::post();
$post = array_filter((array)$post, function ($item) {
    return !is_string($item) && !is_numeric($item) || strlen((string)$item);
});

$user = User::login();
$user_query = ArangoDB::start($user);

$cluster = $user->useEdge(UserToCluster::getName())->vertex();
$cluster->setSafeMode(false);
$cluster_fields = $cluster->getFields();
foreach ($cluster_fields as $field) {
    $field_name = $field->getName();
    if (false === array_key_exists($field_name, $post)
        || $field->getProtected()) continue;

    $cluster->getField($field_name)->setValue($post[$field_name]);
}

$user_query_select = $user_query->select();

$or = Request::get('force-use-or');
$or = filter_var($or, FILTER_VALIDATE_BOOLEAN);
if (true === $or && Policy::check('sso/cluster/action/read/or')) $user_query_select->pushEntitiesUsingOr($cluster);

if (!!$count_offset = Request::get('offset')) $user_query_select->getLimit()->setOffset($count_offset);
if (!!$count = Request::get('count')) $user_query_select->getLimit()->set($count);

$user_query_select_return = 'RETURN' . chr(32) . $user_query_select->getPointer(Choose::VERTEX);
$user_query_select->getReturn()->setPlain($user_query_select_return);
$user_query_select_response = $user_query_select->run();
if (null === $user_query_select_response) Output::print(false);

$cluster = new Cluster();
$cluster->setSafeMode(false)->setReadMode(true);

array_walk($user_query_select_response, function (&$value) use ($cluster) {
    $clone = clone $cluster;
    $clone->setFromAssociative($value);
    $value = $clone->getAllFieldsValues(false, false);
});

Output::concatenate(Output::APIDATA, $user_query_select_response);
Output::print(true);
