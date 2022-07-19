<?PHP

namespace applications\sso\oauth\actions;

use Knight\armor\Curl;
use Knight\armor\Cookie;
use Knight\armor\Request;
use Knight\armor\Language;
use Knight\armor\Output;

use Entity\Validation;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\entity\Edge;
use ArangoDB\entity\Vertex;
use ArangoDB\entity\common\Arango;

use applications\iam\user\database\Vertex as User;
use applications\iam\user\database\edges\UserToUser;
use applications\iam\user\database\edges\Session;
use applications\iam\user\database\edges\Token;
use applications\sso\oauth\database\Vertex as Oauth;

use extensions\Navigator;

const CODE = 'code';

$authorize_parameters_encrypt = Request::get('state');
if (null === $authorize_parameters_encrypt) {
	$tokens = Tokern::get();
    if (empty($tokens)) Output::print(false); 
    Output::concatenate('tokens', $tokens);
    Output::print(true); 
}

$authorize_parameters = User::getCipher()->decrypt($authorize_parameters_encrypt);
$authorize_parameters = Request::JSONDecode($authorize_parameters);

$oauth = new Oauth();
$oauth->setSafeMode(false)->setReadMode(true);
$oauth->setFromAssociative((array)$authorize_parameters);

if (!!$errors = $oauth->checkRequired()->getAllFieldsWarning()) {
    Language::dictionary(__file__);
    $notice = __namespace__ . '\\' . 'notice';
    $notice = Language::translate($notice);
    Output::concatenate('notice', $notice);
    Output::concatenate('errors', $errors);
    Output::print(false);
}

$authorize_parameters->{CODE} = Request::get(CODE);
$authorize_parameters->{Token::GRANT_TYPE} = Token::GRANT_AUTHORIZATION;

$token_value = new Curl();
$token_value = $token_value->request($oauth->getURI(Oauth::TOKEN), (array)$authorize_parameters);

$token = new Token();
$token->setFromAssociative((array)$token_value);
$token->getField('oauth_key')->setProtected(false)->setValue($oauth->getField(Arango::KEY)->getValue());

$user = $token->getWhoami($oauth);
$user_query = ArangoDB::start($user);

$user_owner = $user->useEdge(UserToUser::getName())->setForceDirection(Edge::INBOUND)->vertex();
$user_owner_fields = $user_owner->getFields();
foreach ($user_owner_fields as $field) $field->setProtected(true);

$user_owner->getField(User::KEY)->setProtected(false)->setRequired(true)->setValue($oauth->getField(Oauth::OWNER)->getValue());

$user_language = $user->getField('language');
if (!!$user_language->isDefault()) $user_language->setValue(Language::getSpeech());

$user->getField('type')->setValue(User::OAUTH);
foreach (Vertex::MANAGEMENT as $field_name)
    $user->getField($field_name)->setProtected(false)->setRequired(true)->setValue($oauth->getField(Oauth::OWNER)->getValue());

if (!!$errors = $user->checkRequired()->getAllFieldsWarning()) {
    Language::dictionary(__file__);
    $notice = __namespace__ . '\\' . 'notice';
    $notice = Language::translate($notice);
    Output::concatenate('notice', $notice);
    Output::concatenate('errors', $errors);
    Output::print(false);
}

$user_query_insert = $user_query->insert();
$user_query_insert->pushEntitySkips($user_owner);
$user_query_insert_return = 'RETURN 1';
$user_query_insert->getReturn()->setPlain($user_query_insert_return);
$user_query_insert_response = $user_query_insert->run();

$return_url = Navigator::getUrl() . 'iam/user/unauth/404';

$authenticated = $token->getWhoami($oauth, Token::ONLYEMAIL);
$authenticated_query = ArangoDB::start($authenticated);

if (null !== $token->insert($authenticated)) {
    $return_url = Navigator::getUrl();
    $return_url_querystring = Request::get(Navigator::RETURN_URL);
    if (null !== $return_url_querystring) $return_url = base64_decode($return_url);
    if (null !== $user_query_insert_response) $return_url = Navigator::getUrl() . 'iam/user/unauth/policy';
    Session::sendCookie(Session::generate($authenticated));
}

header('HTTP/1.1 301 Moved Permanently');
header('Location: ' . $return_url);

exit;
