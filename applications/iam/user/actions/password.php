<?PHP

namespace applications\iam\user\actions;

use SendGrid;
use SendGrid\Mail\Mail;

use Knight\armor\Output;
use Knight\armor\Request;
use Knight\armor\Language;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\Statement;
use ArangoDB\entity\Edge;
use ArangoDB\entity\common\Arango;
use ArangoDB\operations\common\Choose;
use ArangoDB\operations\common\Handling;

use applications\iam\user\database\Vertex as User;
use applications\iam\user\database\edges\UserToUser;
use applications\iam\policy\database\Vertex as Policy;
use applications\iam\user\forms\Password;

use extensions\Navigator;

use configurations\mail\Sendgrid as Configuration;

Policy::mandatories('iam/user/action/password');

function password()
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charlength = strlen($characters) - 1;
    $randstring = '';
    for ($i = 0; $i < 8; $i++) {
        $character_random_position = rand(0, $charlength);
        $randstring .= $characters[$character_random_position];
    }
    return $randstring;
}

$user = User::login();
$user_child = new User();
$user_child_fields = $user_child->getFields();
foreach ($user_child_fields as $field) $field->setProtected(true);

$user_child_type = $user_child->getField('type');

$user_child_key = parse_url($_SERVER[Navigator::REQUEST_URI], PHP_URL_PATH);
$user_child_key = basename($user_child_key);
$user_child->getField(Arango::KEY)->setProtected(false)->setRequired(true)->setValue($user_child_key);

Language::dictionary(__file__);
$exception_message = __namespace__ . '\\' . 'exception';

$user_child_query = ArangoDB::start($user_child);

$user_child_branch = $user_child->useEdge(UserToUser::getName())->setForceDirection(Edge::INBOUND);
$user_child_branch->vertex($user);
$user_child_branch->branch()->vertex()->useEdge(UserToUser::getName())->setForceDirection(Edge::INBOUND)->vertex($user);

$user_child_query_to_user_vertex = $user_child_query->select();
$user_child_query_to_user_vertex_traversal_vertex = $user_child_query_to_user_vertex->getPointer(Choose::TRAVERSAL_VERTEX);
$user_child_query_to_user_vertex->getLimit()->set(1);
$user_child_query_to_user_vertex_return = new Statement();
$user_child_query_to_user_vertex_return->append('LET you = FIRST(', false);
$user_child_query_to_user_vertex_return->append($user_child_query_to_user_vertex_traversal_vertex, false);
$user_child_query_to_user_vertex_return->append(')');
$user_child_query_to_user_vertex_return->append('FILTER');
$user_child_query_to_user_vertex_return->append('you' . chr(46) . $user_child_type->getName());
$user_child_query_to_user_vertex_return->append('!= $0');
$user_child_query_to_user_vertex_return->append('RETURN 1');
$user_child_query_to_user_vertex->getReturn()->setFromStatement($user_child_query_to_user_vertex_return, User::OAUTH);
$user_child_query_to_user_vertex_statement = $user_child_query_to_user_vertex->getStatement();
$user_child_query_to_user_vertex_statement_exception_message = $exception_message . '\\' . 'hierarchy';
$user_child_query_to_user_vertex_statement_exception_message = Language::translate($exception_message);
$user_child_query_to_user_vertex_statement->setExceptionMessage($user_child_query_to_user_vertex_statement_exception_message);
$user_child_query_to_user_vertex_statement->setExpect(1)->setHideResponse(true);
$user_child->getContainer()->removeEdgesByName(UserToUser::getName());

$password = new Password();
$password_posted = Request::post($password->getField('password')->getName());

$type = Request::post($password->getField('type')->getName());
$type_condition = null === $type || $type === 'auto';
if ($type_condition) $password_posted = password();

$user_child->getField('password')->setProtected(false)->setRequired(true)->setValue($password_posted);

if (!!$errors = $user_child->checkRequired()->getAllFieldsWarning()) {
    Language::dictionary(__file__);
    $notice = __namespace__ . '\\' . 'notice';
    $notice = Language::translate($notice);
    Output::concatenate('notice', $notice);
    Output::concatenate('errors', $errors);
    Output::print(false);
}

$user_child_fields = $user_child->getFields();
foreach ($user_child_fields as $field) $field->setRequired(true);

$query_update = $user_child_query->upsert();
$query_update->setReplace(false);
$query_update->pushStatementsPreliminary($user_child_query_to_user_vertex_statement);
$query_update_return = 'RETURN' . chr(32) . Handling::RNEW;
$query_update->getReturn()->setPlain($query_update_return);
$query_update->setEntityEnableReturns($user_child);
$query_update_response = $query_update->run();
if (null === $query_update_response
    || empty($query_update_response)) Output::print(false);

$user_child_get = new User();
$user_child_get->setReadMode(true);
$user_child_get_value = reset($query_update_response);
$user_child_get->setFromAssociative($user_child_get_value);
$user_child_get_value = $user_child_get->getAllFieldsValues(true, false);
if (false === array_key_exists('email', $user_child_get_value)) Output::print(true);

Language::setSpeech($user_child_get->getField('language')->getValue());

$user_child_get_value['password'] = $type_condition ? $password_posted : Language::translate($exception_message . '\\' . 'admin');
$user_child_get_value_exception = __namespace__ . '\\' . 'email';
$user_child_get_value += Language::getTextsNamespaceName($user_child_get_value_exception);

$email = new Mail();
$email->enableBypassListManagement();
$email->setFrom('password@management.energia-europa.com', 'Identity and Access Management');
$email->setTemplateId('d-b98eeb91257c4511a00cc2ef815c7b46');
$email->addTo($user_child_get_value['email'], $user_child_get_value['firstname'] . chr(32) . $user_child_get_value['lastname'], $user_child_get_value, 0);

try {
    $sendgrid = new SendGrid(Configuration::KEY);
    $response = $sendgrid->send($email);
    Output::concatenate('email', 202 === $response->statusCode());
} catch (Exception $exception) {
    Output::concatenate('errors', $exception->getMessage());
	Output::print(false);
}

Output::print(true);
