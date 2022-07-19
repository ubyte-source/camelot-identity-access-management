<?PHP

namespace applications\iam\user\actions;

use Knight\armor\Output;
use Knight\armor\Language;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\Statement;
use ArangoDB\operations\common\Choose;
use ArangoDB\entity\Vertex;
use ArangoDB\entity\common\Arango;

use applications\iam\user\database\Vertex as User;
use applications\iam\user\database\edges\UserToUser;
use applications\iam\policy\database\Vertex as Policy;
use applications\iam\user\forms\Matrioska;

use extensions\Navigator;

Policy::mandatories('iam/user/action/detail');

$user_child_key_value = parse_url($_SERVER[Navigator::REQUEST_URI], PHP_URL_PATH);
$user_child_key_value = basename($user_child_key_value);

$user = User::login();
$user_child = new User();
$user_child_query = ArangoDB::start($user_child);
$user_child_fields = $user_child->getFields();
foreach ($user_child_fields as $field) $field->setProtected(true);

$user_child->getField(Arango::KEY)->setProtected(false)->setRequired(true)->setValue($user_child_key_value);

if (!!$errors = $user_child->checkRequired()->getAllFieldsWarning()) {
    Language::dictionary(__file__);
    $notice = __namespace__ . '\\' . 'notice';
    $notice = Language::translate($notice);
    Output::concatenate('notice', $notice);
    Output::concatenate('errors', $errors);
    Output::print(false);
}

$user_child_query_select = $user_child_query->select();
$user_child_query_select_return_statement = new Statement();
$user_child_query_select->getLimit()->set(1);

if ($user_child_key_value !== $user->getField(Arango::KEY)->getValue() && !Policy::check('iam/user/action/read/all')) {
    $user = User::login();
    $user_query_hierarchy = ArangoDB::start($user);

    $user_child_edge = $user->useEdge(UserToUser::getName());
    $user_child_edge->vertex($user_child);
    $user_child_edge->branch()->vertex()->useEdge(UserToUser::getName())->vertex($user_child);

    $user_query_hierarchy_select = $user_query_hierarchy->select();
    $user_query_hierarchy_select->getLimit()->set(1);
    $user_query_hierarchy_select->useWith(false);
    $user_query_hierarchy_select_statement = $user_query_hierarchy_select->getStatement();
    $user_query_hierarchy_select_statement_query = $user_query_hierarchy_select_statement->getQuery();

    $user_child_query_select_return_statement->addBindFromStatements($user_query_hierarchy_select_statement);
    $user_child_query_select_return_statement->append('LET counter = COUNT(' . $user_query_hierarchy_select_statement_query . ')');
    $user_child_query_select_return_statement->append('FILTER counter == 1');
}

$owner = $user->getField(Vertex::OWNER)->getName();
$statement_vertex = $user_child_query_select->getPointer(Choose::VERTEX);

$user_child_query_select_return_statement->append('LET owner = DOCUMENT(' . User::COLLECTION . chr(44) . chr(32) . $statement_vertex . chr(46) . $owner . ')');
$user_child_query_select_return_statement->append('LET merge = {' . $owner . ': owner}');
$user_child_query_select_return_statement->append('RETURN MERGE(' . $statement_vertex . chr(44) . chr(32) . 'merge)');
$user_child_query_select->getReturn()->setFromStatement($user_child_query_select_return_statement);

$user_child_query_select_response = $user_child_query_select->run();
if (null === $user_child_query_select_response
    || empty($user_child_query_select_response)) Output::print(false);

$matrioska = new Matrioska();
$matrioska->setSafeMode(false)->setReadMode(true);
$matrioska_value = reset($user_child_query_select_response);
$matrioska->setFromAssociative($matrioska_value, $matrioska_value);
$matrioska_value = $matrioska->getAllFieldsValues(false, false);

Output::concatenate(Output::APIDATA, $matrioska_value);
Output::print(true);
