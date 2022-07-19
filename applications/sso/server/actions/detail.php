<?PHP

namespace applications\sso\server\actions;

use Knight\armor\Output;
use Knight\armor\Language;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\Statement;
use ArangoDB\operations\common\Choose;
use ArangoDB\entity\Vertex;
use ArangoDB\entity\common\Arango;

use applications\iam\user\database\Vertex as User;
use applications\iam\user\database\edges\UserToServer;
use applications\iam\policy\database\Vertex as Policy;
use applications\sso\server\database\edges\ServerToApplication;
use applications\sso\server\forms\Matrioska;

use extensions\Navigator;

Policy::mandatories('sso/server/action/detail');

$user = User::login();
$user_query = ArangoDB::start($user);

$server = $user->useEdge(UserToServer::getName())->vertex();
$server_fields = $server->getFields();
foreach ($server_fields as $field) $field->setProtected(true);

$server_key_value = parse_url($_SERVER[Navigator::REQUEST_URI], PHP_URL_PATH);
$server_key_value = basename($server_key_value);

$server->getField(Arango::KEY)->setProtected(false)->setRequired(true)->setValue($server_key_value);

if (!!$errors = $server->checkRequired()->getAllFieldsWarning()) {
    Language::dictionary(__file__);
    $notice = __namespace__ . '\\' . 'notice';
    $notice = Language::translate($notice);
    Output::concatenate('notice', $notice);
    Output::concatenate('errors', $errors);
    Output::print(false);
}

$server->useEdge(ServerToApplication::getName());

$query_select = $user_query->select();
$query_select_vertex = $query_select->getPointer(Choose::VERTEX);
$query_select_traversal_vertex = $query_select->getPointer(Choose::TRAVERSAL_VERTEX);

$owner = $server->getField(Vertex::OWNER)->getName();

$query_select_return_statement = new Statement();
$query_select_return_statement->append('COLLECT traversal = SLICE(' . $query_select_traversal_vertex . ', 1, 1) INTO applications = ' . $query_select_vertex);
$query_select_return_statement->append('LET first = FIRST(traversal)');
$query_select_return_statement->append('LET owner = DOCUMENT(' . User::COLLECTION . ', first.' . $owner . ')');
$query_select_return_statement->append('LET server = MERGE(first, {' . $owner . ': owner}, {application: applications})');
$query_select_return_statement->append('RETURN server');
$query_select->getReturn()->setFromStatement($query_select_return_statement);

$query_select_response = $query_select->run();
if (null === $query_select_response
    || empty($query_select_response)) Output::print(false);

$matrioska = new Matrioska();
$matrioska->setSafeMode(false)->setReadMode(true);
$matrioska_value = reset($query_select_response);
$matrioska->setFromAssociative($matrioska_value, $matrioska_value);
$matrioska_value = $matrioska->getAllFieldsValues(false, false);

Output::concatenate(Output::APIDATA, $matrioska_value);
Output::print(true);
