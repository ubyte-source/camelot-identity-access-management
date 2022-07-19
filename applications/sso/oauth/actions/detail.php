<?PHP

namespace applications\sso\oauth\actions;

use Knight\armor\Output;
use Knight\armor\Language;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\operations\common\Choose;
use ArangoDB\entity\common\Arango;

use applications\iam\user\database\Vertex as User;
use applications\iam\user\database\edges\UserToOauth;
use applications\iam\policy\database\Vertex as Policy;
use applications\sso\oauth\forms\Upsert as Oauth;

use extensions\Navigator;

Policy::mandatories('sso/oauth/action/detail');

$user = User::login();
$user_query = ArangoDB::start($user);

$oauth = $user->useEdge(UserToOauth::getName())->vertex();
$oauth_fields = $oauth->getFields();
foreach ($oauth_fields as $field) $field->setProtected(true);

$oauth_key_value = parse_url($_SERVER[Navigator::REQUEST_URI], PHP_URL_PATH);
$oauth_key_value = basename($oauth_key_value);

$oauth->getField(Arango::KEY)->setProtected(false)->setRequired(true)->setValue($oauth_key_value);

if (!!$errors = $oauth->checkRequired()->getAllFieldsWarning()) {
    Language::dictionary(__file__);
    $notice = __namespace__ . '\\' . 'notice';
    $notice = Language::translate($notice);
    Output::concatenate('notice', $notice);
    Output::concatenate('errors', $errors);
    Output::print(false);
}

$query_select = $user_query->select();
$query_select->getLimit()->set(1);
$query_select_return = 'RETURN' . chr(32) . $query_select->getPointer(Choose::VERTEX);
$query_select->getReturn()->setPlain($query_select_return);
$query_select_response = $query_select->run();
if (null === $query_select_response
    || empty($query_select_response)) Output::print(false);

$oauth = new Oauth();
$oauth->setSafeMode(false)->setReadMode(true);
$oauth_value = reset($query_select_response);
$oauth->setFromAssociative($oauth_value, $oauth_value);
$oauth_value = $oauth->getAllFieldsValues(false, false);

Output::concatenate(Output::APIDATA, $oauth_value);
Output::print(true);
