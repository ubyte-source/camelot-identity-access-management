<?PHP

namespace applications\iam\user\actions;

use Knight\armor\Output;
use Knight\armor\Request;

use applications\iam\user\database\Vertex as User;
use applications\iam\user\database\edges\Session;

$user = new User();
$user_fields = $user->getFields();
foreach ($user_fields as $field) $field->setProtected(false);

$user->setFromAssociative((array)Request::post());

User::login($user);

$authorization = Session::getAuthorization();
Output::concatenate('authorization', $authorization);
Output::print(true);
