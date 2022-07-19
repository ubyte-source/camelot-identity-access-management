<?PHP

namespace applications\iam\group\actions;

use Knight\armor\Output;
use Knight\armor\Request;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\operations\common\Choose;

use applications\iam\user\database\Vertex as User;
use applications\iam\user\database\edges\UserToGroup;
use applications\iam\policy\database\Vertex as Policy;
use applications\iam\group\forms\Group;

Policy::mandatories('iam/group/action/read');

$user = User::login();
$user_query = ArangoDB::start($user);

$user_to_group = $user->useEdge(UserToGroup::getName());
$user_to_group->getField('admin')->setProtected(false)->setValue(true);
$user_to_group->getField('admin')->setProtected(true);

$post = Request::post();
$post = array_filter((array)$post, function ($item) {
    return !is_string($item) && !is_numeric($item) || strlen((string)$item);
});

$group = $user_to_group->vertex();
$group->setSafeMode(false);
$group_fields = $group->getFields();
foreach ($group_fields as $field) {
    $field_name = $field->getName();
    if (false === array_key_exists($field_name, $post)
        || $field->getProtected()) continue;

    $group->getField($field_name)->setValue($post[$field_name]);
}

$user_query_select = $user_query->select();

$or = Request::get('force-use-or');
$or = filter_var($or, FILTER_VALIDATE_BOOLEAN);
if (true === $or && Policy::check('iam/group/action/read/or')) $user_query_select->pushEntitiesUsingOr($group);

if (!!$count_offset = Request::get('offset')) $user_query_select->getLimit()->setOffset($count_offset);
if (!!$count = Request::get('count')) $user_query_select->getLimit()->set($count);

$user_query_select_return = 'RETURN' . chr (32) . $user_query_select->getPointer(Choose::VERTEX);
$user_query_select->getReturn()->setPlain($user_query_select_return);
$user_query_select_response = $user_query_select->run();
if (null === $user_query_select_response) Output::print(false);

$group = new Group();
$group->setSafeMode(false)->setReadMode(true);

array_walk($user_query_select_response, function (&$value) use ($group) {
    $clone = clone $group;
    $clone->setFromAssociative($value);
    $value = $clone->getAllFieldsValues(false, false);
});

Output::concatenate(Output::APIDATA, $user_query_select_response);
Output::print(true);
