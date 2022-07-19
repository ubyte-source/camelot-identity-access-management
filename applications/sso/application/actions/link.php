<?PHP

namespace applications\sso\application\actions;

use Knight\armor\Output;
use Knight\armor\Language;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\operations\common\Choose;

use applications\iam\user\database\Vertex as User;
use applications\sso\application\forms\Application;

use extensions\Navigator;

User::login();

$application = new Application();
$application_fields = $application->getFields();
foreach ($application_fields as $field) $field->setProtected(true);

$application_basename_value = parse_url($_SERVER[Navigator::REQUEST_URI], PHP_URL_PATH);
$application_basename_value = basename($application_basename_value);

$self_directory = scandir(APPLICATIONS);
if (in_array($application_basename_value, $self_directory)) {
    Output::concatenate(Output::APIDATA, Navigator::getUrl());
    Output::print(true);
}

$application->getField('basename')->setProtected(false)->setRequired(true)->setValue($application_basename_value);

if (!!$errors = $application->checkRequired()->getAllFieldsWarning()) {
    Language::dictionary(__file__);
    $notice = __namespace__ . '\\' . 'notice';
    $notice = Language::translate($notice);
    Output::concatenate('notice', $notice);
    Output::concatenate('errors', $errors);
    Output::print(false);
}

$application_query = ArangoDB::start($application);
$application_query_select = $application_query->select();
$application_query_select->getLimit()->set(1);
$application_query_select_vertex = $application_query_select->getPointer(Choose::VERTEX);
$application_query_select_return = 'RETURN' . chr(32) . $application_query_select_vertex;
$application_query_select->getReturn()->setPlain($application_query_select_return);

$application_query_select_response = $application_query_select->run();
if (null === $application_query_select_response
    || empty($application_query_select_response)) Output::print(false);

$application = new Application();
$application->setReadMode(true);
$application_fields = $application->getFields();
foreach ($application_fields as $field) $field->setProtected(false);

$application_value = reset($application_query_select_response);
$application->setFromAssociative($application_value);

Output::concatenate(Output::APIDATA, $application->getField('link')->getValue());
Output::print(true);
