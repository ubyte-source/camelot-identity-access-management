<?PHP

namespace applications\sso\oauth\actions;

use Knight\armor\Output;
use Knight\armor\Request;
use Knight\armor\Language;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\operations\Update;
use ArangoDB\entity\Vertex;
use ArangoDB\entity\common\Arango;
use ArangoDB\operations\common\Handling;

use applications\iam\user\database\Vertex as User;
use applications\iam\user\database\edges\UserToOauth;
use applications\iam\policy\database\Vertex as Policy;
use applications\sso\oauth\database\Vertex as Oauth;
use applications\sso\oauth\database\edges\OauthToUser;

use extensions\Navigator;

Policy::mandatories('sso/oauth/action/update');

$oauth_key_value = parse_url($_SERVER[Navigator::REQUEST_URI], PHP_URL_PATH);
$oauth_key_value = basename($oauth_key_value);

$user = User::Login();
$user_query = ArangoDB::start($user);

$oauth = $user->useEdge(UserToOauth::getName())->vertex();
$oauth_check = clone $oauth;
$oauth_check_fields = $oauth_check->getFields();
foreach ($oauth_check_fields as $field) $field->setProtected(true);

$oauth_check->getField(Arango::KEY)->setProtected(false)->setRequired(true)->setValue($oauth_key_value);

$oauth_check_query = ArangoDB::start($oauth_check);
$oauth_check->useEdge(OauthToUser::getName())->vertex($user);
$oauth_check_query_select = $oauth_check_query->select();
$oauth_check_query_select->getLimit()->set(1);
$oauth_check_query_select_return = 'RETURN 1';
$oauth_check_query_select->getReturn()->setPlain($oauth_check_query_select_return);
$oauth_check_query_select_statement = $oauth_check_query_select->getStatement();
$oauth_check_query_select_statement->setExpect(1)->setHideResponse(true);

$oauth->setFromAssociative((array)Request::post());
$oauth->getField(Arango::KEY)->setProtected(false)->setRequired(true)->setValue($oauth_key_value);

$management = [];

$oauth_clone = clone $oauth;
$oauth_clone_fields = $oauth_clone->getFields();
foreach ($oauth_clone_fields as $field) $field->setRequired(true);

$oauth_clone_query = ArangoDB::start($oauth_clone);
$oauth_clone_query_update = $oauth_clone_query->update();
$oauth_clone_query_update->setReplace(true);
$oauth_clone_query_update_return = 'RETURN' . chr(32) . Handling::RNEW;
$oauth_clone_query_update->getReturn()->setPlain($oauth_clone_query_update_return);
$oauth_clone_query_update->setEntityEnableReturns($oauth_clone);

foreach (Vertex::MANAGEMENT as $field_name) {
    $oauth_clone_field_name = Update::SEARCH . chr(46) . $field_name;
    $oauth_clone->getField($field_name)->setSafeModeDetached(false)->setRequired(true)->setValue($oauth_clone_field_name);
    array_push($management, $oauth_clone_field_name);
}

Language::dictionary(__file__);
$exception_message = __namespace__ . '\\' . 'exception' . '\\';

if (!!$errors = $oauth->checkRequired()->getAllFieldsWarning()) {
    Language::dictionary(__file__);
    $notice = __namespace__ . '\\' . 'notice';
    $notice = Language::translate($notice);
    Output::concatenate('notice', $notice);
    Output::concatenate('errors', $errors);
    Output::print(false);
}

$oauth_clone_query_update->pushStatementsPreliminary($oauth_check_query_select_statement);
$oauth_clone_query_update->pushStatementSkipValues(...$management);
$oauth_clone_query_update_response = $oauth_clone_query_update->run();
if (null === $oauth_clone_query_update_response
    || empty($oauth_clone_query_update_response)) Output::print(false);

$oauth = new Oauth();
$oauth->setSafeMode(false)->setReadMode(true);
$oauth_value = reset($oauth_clone_query_update_response);
$oauth->setFromAssociative($oauth_value, $oauth_value);
$oauth_value = $oauth->getAllFieldsValues(false, false);
Output::concatenate(Output::APIDATA, $oauth_value);
Output::print(true);
