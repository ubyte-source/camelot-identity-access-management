<?PHP

namespace applications\iam\user\actions;

use Knight\armor\Output;
use Knight\armor\Navigator;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\Statement;
use ArangoDB\operations\common\Choose;

use applications\iam\user\database\Vertex as User;
use applications\iam\user\database\edges\UserToUser;
use applications\iam\policy\database\Vertex as Policy;

Policy::mandatories('iam/user/action/hierarchy');

$name = parse_url($_SERVER[Navigator::REQUEST_URI], PHP_URL_PATH);
$name = basename($name);

$user = User::login();
$user_query = ArangoDB::start($user);

$edge = $user->useEdge(UserToUser::getName());
$user->useEdge(UserToUser::getName(), $edge)->vertex();
$user->useEdge(UserToUser::getName())->vertex()->useEdge(UserToUser::getName(), $edge)->vertex();

$user_query_select = $user_query->select();
$user_query_select_return_statement = new Statement();
$user_query_select_return_statement->append('RETURN' . chr(32) . $user_query_select->getPointer(Choose::VERTEX) . chr(46) . $user->getField($name)->getName());
$user_query_select->getReturn()->setFromStatement($user_query_select_return_statement);
$user_query_select_response = $user_query_select->run();
if (null === $user_query_select_response) Output::print(false);

Output::concatenate(Output::APIDATA, $user_query_select_response);
Output::print(true);
