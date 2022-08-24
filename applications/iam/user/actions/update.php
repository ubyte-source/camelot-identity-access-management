<?PHP

namespace applications\iam\user\actions;

use Knight\armor\Output;
use Knight\armor\Request;
use Knight\armor\Language;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\operations\Update;
use ArangoDB\entity\Vertex;
use ArangoDB\entity\common\Arango;
use ArangoDB\operations\common\Handling;

use applications\iam\user\database\Vertex as User;
use applications\iam\user\database\edges\UserToUser;
use applications\iam\policy\database\Vertex as Policy;

use extensions\Navigator;

$user_child_key_value = parse_url($_SERVER[Navigator::REQUEST_URI], PHP_URL_PATH);
$user_child_key_value = basename($user_child_key_value);

$user = User::login();

$current_policies_check = $user_child_key_value === $user->getField(Arango::KEY)->getValue() ? 'iam/user/action/update/me' : 'iam/user/action/update';
Policy::mandatories($current_policies_check);

$user_child = new User();
$user_child_query = ArangoDB::start($user_child);

$user_child_field_key = $user_child->getField(Arango::KEY);
$user_child_field_key->setProtected(false)->setRequired(true);
$user_child_field_key->setValue($user_child_key_value);

$user_child_uploads = array_column($_FILES, 'tmp_name');
$user_child_uploads_keys = array_keys($_FILES);
$user_child_uploads = array_combine($user_child_uploads_keys, $user_child_uploads);
$user_child->setFromAssociative((array)Request::post(), $user_child_uploads);

$noreplace = Vertex::MANAGEMENT;
array_push($noreplace, $user_child->getField('password')->getName());

$picture = $user_child->getField('picture')->getName();
if (false === array_key_exists($picture, $user_child_uploads)) array_push($noreplace, $picture);

$management = [];
foreach ($noreplace as $field_name) {
	$user_child_document_field_name = Update::SEARCH . chr(46) . $field_name;
	$user_child->getField($field_name)->setSafeModeDetached(false)->setRequired(true)->setValue($user_child_document_field_name);
    array_push($management, $user_child_document_field_name);
}

$user_child_warning = $user_child->checkRequired()->getAllFieldsWarning();

$user_unique = new User();
$user_unique_fields = $user_unique->getFields();
foreach ($user_unique_fields as $field) $field->setProtected(true);

$user_unique_field_email = $user_unique->getField('email');
$user_unique_field_email->setProtected(false)->setRequired(true);
$user_unique->setFromAssociative((array)Request::post());
$user_unique_warnings = $user_unique->checkRequired()->getAllFieldsWarning();

if (!!$errors = array_merge($user_child_warning, $user_unique_warnings)) {
	Language::dictionary(__file__);
    $notice = __namespace__ . '\\' . 'notice';
    $notice = Language::translate($notice);
    Output::concatenate('notice', $notice);
    Output::concatenate('errors', $errors);
    Output::print(false);
}

$preliminary = [];

if ($user_child_key_value !== $user->getField(Arango::KEY)->getValue()) {
	$user_hierarchy = User::login();
	$user_hierarchy_query = ArangoDB::start($user_hierarchy);

	$user_hierarchy_edge = $user_hierarchy->useEdge(UserToUser::getName());
	$user_hierarchy = $user_hierarchy_edge->vertex();
	$user_hierarchy->getField(Arango::KEY)->setProtected(false)->setRequired(true)->setValue($user_child_key_value);
	$user_hierarchy_edge->branch()->vertex()->useEdge(UserToUser::getName())->vertex($user_hierarchy);

	$user_hierarchy_query_select = $user_hierarchy_query->select();
	$user_hierarchy_query_select->getLimit()->set(1);
	$user_hierarchy_query_select_return = 'RETURN 1';
	$user_hierarchy_query_select->getReturn()->setPlain($user_hierarchy_query_select_return);
	$user_hierarchy_query_select_statement = $user_hierarchy_query_select->getStatement();
	$user_hierarchy_query_select_statement->setExpect(1)->setHideResponse(true);
	array_push($preliminary, $user_hierarchy_query_select_statement);
}

Language::dictionary(__file__);
$exception_message = __namespace__ . '\\' . 'exception' . '\\';

$user_unique_query = ArangoDB::start($user_unique);
$user_unique_query_select = $user_unique_query->select();
$user_unique_query_select->getLimit()->set(2);
$user_unique_query_select_statement = $user_unique_query_select->getStatement();
$user_unique_query_select_statement_exception_message = $exception_message . 'constrain';
$user_unique_query_select_statement_exception_message = Language::translate($user_unique_query_select_statement_exception_message);
$user_unique_query_select_statement->setExceptionMessage($user_unique_query_select_statement_exception_message);
$user_unique_query_select_statement->setExpect(1)->setHideResponse(true);

$user_child_fields = $user_child->getFields();
foreach ($user_child_fields as $field) $field->setRequired(true);

$user_child_query_update = $user_child_query->update();
$user_child_query_update->setReplace(true);
$user_child_query_update->pushStatementsPreliminary(...$preliminary);
$user_child_query_update->pushStatementSkipValues(...$management);
$user_child_query_update->pushStatementsFinal($user_unique_query_select_statement);
$user_child_query_update_return = 'RETURN' . chr(32) . Handling::RNEW;
$user_child_query_update->getReturn()->setPlain($user_child_query_update_return);
$user_child_query_update->setEntityEnableReturns($user_child);

$user_child_query_update_response = $user_child_query_update->run();
if (null === $user_child_query_update_response
	|| empty($user_child_query_update_response)) Output::print(false);

$user = new User();
$user->setSafeMode(false)->setReadMode(true);
$user_value = reset($user_child_query_update_response);
$user->setFromAssociative($user_value, $user_value);
$user_value = $user->getAllFieldsValues(false, false);
Output::concatenate(Output::APIDATA, $user_value);
Output::print(true);
