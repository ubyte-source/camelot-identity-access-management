<?PHP

namespace applications\iam\user\actions;

use Knight\armor\Output;
use Knight\armor\Request;
use Knight\armor\Language;

use Entity\Field;
use Entity\Map as Entity;

use ArangoDB\Initiator as ArangoDB;

use applications\iam\user\database\Vertex as User;
use applications\iam\user\database\edges\UserToSetting;
use applications\iam\policy\database\Vertex as Policy;
use applications\sso\application\database\Vertex as Application;

use extensions\widgets\infinite\Setting;

Policy::mandatories('iam/user/action/save/widget/setting');

$user = User::login();
$user_query = ArangoDB::start($user);
$user_query_select = $user_query->select();
$user_query_select->getLimit()->set(1);
$user_query_select_return = 'RETURN 1';
$user_query_select->getReturn()->setPlain($user_query_select_return);
$user_query_select_statement = $user_query_select->getStatement();
$user_query_select_statement->setExpect(1)->setHideResponse(true);

$application = new Application();
$application_fields = $application->getFields();
foreach ($application_fields as $field) $field->setProtected(true);

$basename = $application->getField('basename')->setProtected(false)->setRequired(true);
$basename_value = Request::post('application');
if (is_array($basename_value)) $application->setFromAssociative($basename_value);

$application_warnings = $application->checkRequired()->getAllFieldsWarning();

$user_to_setting = $user->useEdge(UserToSetting::getName());
$user_to_setting->setFromAssociative((array)Request::post());
$user_to_setting->vertex($application);
$user_to_setting_fields = $user_to_setting->getFields();
foreach ($user_to_setting_fields as $field) $field->setRequired(true);

$check_target_query = ArangoDB::start($application);
$check_target_query_select = $check_target_query->select();
$check_target_query_select->getLimit()->set(1);
$check_target_query_select_return = 'RETURN 1';
$check_target_query_select->getReturn()->setPlain($check_target_query_select_return);
$check_target_query_select_statement = $check_target_query_select->getStatement();
$check_target_query_select_statement->setExpect(1)->setHideResponse(true);

$widget = $user_to_setting->getField('widget')->getValue();
$widget = strtolower($widget);
$widget = ucfirst($widget);

$module = $user_to_setting->getField('module')->getValue();
$called = 'applications' . '\\' . $basename->getValue() . '\\' . $module;
$called_abstraction = $called . '\\' . 'forms' . '\\' . $widget;

$target = Entity::factory($called_abstraction);
$target_fields_name = $target->getAllFieldsKeys();
$target_fields_name_protected = $target->getAllFieldsProtectedName();
$target_fields_name = array_diff($target_fields_name, $target_fields_name_protected);

$value = $user_to_setting->getField('value');
$value_value = $value->getValue();
$value_value = array_filter($value_value, function (Setting $setting) use ($target_fields_name) {
	return in_array($setting->getField('name')->getValue(), $target_fields_name);
});
$value_value = array_values($value_value);
$value->setSafeModeDetached(false)->setValue($value_value, Field::OVERRIDE);

$errors = $user_to_setting->checkRequired()->getAllFieldsWarning();
$errors = array_merge($application_warnings, $errors);
if (!!$errors) {	
	Language::dictionary(__file__);
    $notice = __namespace__ . '\\' . 'notice';
    $notice = Language::translate($notice);
    Output::concatenate('notice', $notice);
    Output::concatenate('errors', $errors);
    Output::print(false);
}

$query_upsert = $user_query->upsert();
$query_upsert->pushStatementsPreliminary($user_query_select_statement, $check_target_query_select_statement);
$query_upsert->setActionOnlyEdges(true);
$query_upsert_response = $query_upsert->run();

if (null !== $query_upsert_response) Output::print(true);
Output::print(false);
