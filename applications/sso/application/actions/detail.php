<?PHP

namespace applications\sso\application\actions;

use Knight\armor\Output;
use Knight\armor\Language;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\Statement;
use ArangoDB\operations\common\Choose;
use ArangoDB\entity\Edge;
use ArangoDB\entity\Vertex;
use ArangoDB\entity\common\Arango;

use applications\iam\user\database\Vertex as User;
use applications\iam\user\database\edges\UserToUser;
use applications\iam\user\database\edges\UserToApplication;
use applications\iam\policy\database\Vertex as Policy;
use applications\sso\application\database\Vertex as Application;
use applications\sso\application\database\edges\ApplicationToCluster;
use applications\sso\application\database\edges\ApplicationToUser;
use applications\sso\application\forms\Matrioska;

use extensions\Navigator;

Policy::mandatories('sso/application/action/detail');

$user = User::login();
$user_query = ArangoDB::start($user);

$application = $user->useEdge(UserToApplication::getName())->vertex();
$application_fields = $application->getFields();
foreach ($application_fields as $field) $field->setProtected(true);

$application_key_value = parse_url($_SERVER[Navigator::REQUEST_URI], PHP_URL_PATH);
$application_key_value = basename($application_key_value);
$application->getField(Arango::KEY)->setProtected(false)->setRequired(true)->setValue($application_key_value);

if (!!$errors = $application->checkRequired()->getAllFieldsWarning()) {
    Language::dictionary(__file__);
    $notice = __namespace__ . '\\' . 'notice';
    $notice = Language::translate($notice);
    Output::concatenate('notice', $notice);
    Output::concatenate('errors', $errors);
    Output::print(false);
}

$application->useEdge(ApplicationToCluster::getName());

$share_user = new Application();
$share_user->getField(Arango::KEY)->setProtected(false)->setValue($application_key_value);
$share_user_query = ArangoDB::start($share_user);

$share_user_vertex = $share_user->useEdge(ApplicationToUser::getName())->vertex();
$share_user_branch = $share_user_vertex->useEdge(UserToUser::getName())->setForceDirection(Edge::INBOUND);
$share_user_branch->vertex($user);
$share_user_branch->branch()->vertex()->useEdge(UserToUser::getName())->setForceDirection(Edge::INBOUND)->vertex($user);

$share_user_sub_query_select = $share_user_query->select();
$share_user_sub_query_select->useWith(false);
$share_user_sub_query_select_variable_main_iteration_traversal_vertex = $share_user_sub_query_select->getPointer(Choose::TRAVERSAL_VERTEX);

$share_user_sub_query_select_return = new Statement();
$share_user_sub_query_select_return->append('LET users = (', false);
$share_user_sub_query_select_return->append('FOR i IN ' . $share_user_sub_query_select_variable_main_iteration_traversal_vertex);
$share_user_sub_query_select_return->append('FILTER IS_SAME_COLLECTION(' . User::COLLECTION . chr(44) . chr(32) . 'i)');
$share_user_sub_query_select_return->append('FILTER i._key != $0');
$share_user_sub_query_select_return->append('RETURN i', false);
$share_user_sub_query_select_return->append(')');
$share_user_sub_query_select_return->append('RETURN FIRST(users)', false);

$share_user_sub_query_select->getReturn()->setFromStatement($share_user_sub_query_select_return, $user->getField(Arango::KEY)->getValue());
$share_user_sub_query_select_statement = $share_user_sub_query_select->getStatement();
$share_user_sub_query_select_statement_query = $share_user_sub_query_select_statement->getQuery();

$query_select = $user_query->select();
$query_select->getLimit()->set(1);

$query_select_traversal_vertex = $query_select->getPointer(Choose::TRAVERSAL_VERTEX);
$owner = $share_user->getField(Vertex::OWNER)->getName();

$query_select_return_statement = new Statement();
$query_select_return_statement->append('LET shift = SHIFT(' . $query_select_traversal_vertex . ')');
$query_select_return_statement->append('LET name = FIRST(shift)');
$query_select_return_statement->append('LET owner = DOCUMENT(' . User::COLLECTION . ', name' . chr(46) . $owner . ')');
$query_select_return_statement->append('LET application = MERGE(name, {' . $owner . ': owner})');
$query_select_return_statement->append('LET share = name.' . $owner . chr(32) . '== $0 ? (' . $share_user_sub_query_select_statement_query . ') : []');
$query_select_return_statement->append('RETURN MERGE(application, {cluster: LAST(' . $query_select_traversal_vertex . '), share: share})');
$query_select_return_statement->addBindFromStatements($share_user_sub_query_select_statement);

$query_select->getReturn()->setFromStatement($query_select_return_statement, $user->getField(Arango::KEY)->getValue());
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
