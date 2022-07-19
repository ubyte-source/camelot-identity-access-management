<?PHP

namespace applications\sso\oauth\actions;

use Knight\armor\Output;
use Knight\armor\Language;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\entity\Vertex;
use ArangoDB\entity\common\Arango;

use applications\iam\user\database\Vertex as User;
use applications\iam\policy\database\Vertex as Policy;
use applications\sso\oauth\database\Vertex as Oauth;
use applications\sso\oauth\database\edges\OauthToUser;

use extensions\Navigator;

Policy::mandatories('sso/oauth/action/delete');

$follow_oauth_edges = new Oauth();
ArangoDB::start($follow_oauth_edges);
$follow_oauth_edges = $follow_oauth_edges->getAllUsableEdgesName(true);

Language::dictionary(__file__);
$exception_message = __namespace__ . '\\' . 'exception';

$user = User::Login();

$delete = parse_url($_SERVER[Navigator::REQUEST_URI], PHP_URL_PATH);
$delete = basename($delete);

$oauth = new Ad();
$oauth_fields = $oauth->getFields();
foreach ($oauth_fields as $field) $field->setProtected(true);

$oauth->getField(Vertex::OWNER)->setProtected(false)->setRequired(true)->setValue($user->getField(Arango::KEY)->getValue());
$oauth->getField(Arango::KEY)->setProtected(false)->setRequired(true)->setValue($delete);

if (!!$errors = $oauth->checkRequired()->getAllFieldsWarning()) {
    Language::dictionary(__file__);
    $notice = __namespace__ . '\\' . 'notice';
    $notice = Language::translate($notice);
    Output::concatenate('notice', $notice);
    Output::concatenate('errors', $errors);
    Output::print(false);
}

$oauth_query = ArangoDB::start($oauth);
$oauth->useEdge(OauthToUser::getName())->vertex($user);

$oauth_query_select = $oauth_query->select();
$oauth_query_select->getLimit()->set(1);
$oauth_query_select_return = 'RETURN 1';
$oauth_query_select->getReturn()->setPlain($oauth_query_select_return);
$oauth_query_select_statement = $oauth_query_select->getStatement();
$oauth_query_select_statement_exception_message = Language::translate($exception_message, $delete);
$oauth_query_select_statement->setExceptionMessage($oauth_query_select_statement_exception_message);
$oauth_query_select_statement->setExpect(1)->setHideResponse(true);
$oauth->getContainer()->removeEdgesByName(OauthToUser::getName());

$oauth_to_check_edge_select_query_clone = clone $oauth;
$oauth_to_check_edge_select_query = ArangoDB::start($oauth_to_check_edge_select_query_clone);

$oauth_query_delete = $oauth_query->remove();
$oauth_query_delete->pushStatementsPreliminary($oauth_query_select_statement);
foreach ($follow_oauth_edges as $edge_name) $oauth_query_delete->pushEntitySkips($oauth->useEdge($edge_name)->vertex());

$oauth_query_delete_response = $oauth_query_delete->run();
if (null !== $oauth_query_delete_response) Output::print(true);
Output::print(false);
