<?PHP
namespace applications\sso\oauth\actions;

use Knight\armor\Request;
use Knight\armor\Output;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\entity\common\Arango;

use applications\iam\user\database\Vertex as User;
use applications\sso\oauth\database\Vertex as Oauth;

use extensions\Navigator;

const STATE = 'state';

$email = Request::post('email');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) Output::print(false);

$user = new User();
$user->getField('email')->setValue($email);

$user_query = ArangoDB::start($user);
$user_query_select = $user_query->select();
$user_query_select->getLimit()->set(1);
$user_query_select_return = 'RETURN 1';
$user_query_select->getReturn()->setPlain($user_query_select_return);
$user_query_select_response = $user_query_select->run();
if (false === empty($user_query_select_response)) {
	$user->getField('type')->setValue(User::OAUTH);
	$user_query_select_response = $user_query_select->run();
	if (empty($user_query_select_response)) Output::print(false);
}

$domain = substr($email, 1 + strrpos($email, chr(64)));
$domain_check = Oauth::check($domain);
if (empty($domain_check)) Output::print(false);

$oauth = new Oauth();
$oauth->setSafeMode(false)->setReadMode(true);
$oauth->setFromAssociative($domain_check);

$authorize_parameters = $oauth->getParameters();
$authorize_parameters->{Navigator::RETURN_URL} = Request::get(Navigator::RETURN_URL);
$authorize_parameters->{STATE} = User::getCipher()->encrypt(Output::json($authorize_parameters));
unset($authorize_parameters->{$oauth->getField('client_secret')->getName()},
	$authorize_parameters->{$oauth->getField(Arango::KEY)->getName()});

$authorize_parameters = http_build_query($authorize_parameters);
$authorize = $oauth->getURI(Oauth::AUTHORIZE) . chr(63) . $authorize_parameters;
Output::concatenate('authorize', $authorize);
Output::print(true);
