<?PHP

namespace applications\iam\policy\actions;

use Knight\armor\Output;
use Knight\armor\Language;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\entity\common\Arango;

use applications\iam\user\database\Vertex as User;
use applications\iam\policy\database\Vertex as Policy;
use applications\iam\policy\database\edges\PolicyToApplication;
use applications\sso\application\database\edges\ApplicationToUser;

use extensions\Navigator;

Policy::mandatories('iam/policy/action/delete');

$edges = new Policy();
ArangoDB::start($edges);
$edges = $edges->getAllUsableEdgesName(true);

Language::dictionary(__file__);
$exception_message = __namespace__ . '\\' . 'exception';

$user = User::Login();

$delete = parse_url($_SERVER[Navigator::REQUEST_URI], PHP_URL_PATH);
$delete = basename($delete);

$policy = new Policy();
$policy_fields = $policy->getFields();
foreach ($policy_fields as $field) $field->setProtected(true);
$policy->getField(Arango::KEY)->setProtected(false)->setRequired(true)->setValue($delete);

if (!!$errors = $policy->checkRequired()->getAllFieldsWarning()) {
    Language::dictionary(__file__);
    $notice = __namespace__ . '\\' . 'notice';
    $notice = Language::translate($notice);
    Output::concatenate('notice', $notice);
    Output::concatenate('errors', $errors);
    Output::print(false);
}

$policy_query = ArangoDB::start($policy);

$policy_to_application_vertex = $policy->useEdge(PolicyToApplication::getName())->vertex();
$policy_to_application_vertex->useEdge(ApplicationToUser::getName())->vertex($user);
$policy_query_select = $policy_query->select();
$policy_query_select->getLimit()->set(1);
$policy_query_select_return = 'RETURN 1';
$policy_query_select->getReturn()->setPlain($policy_query_select_return);
$policy_query_select_statement = $policy_query_select->getStatement();
$policy_query_select_statement_exception_message = Language::translate($exception_message, $delete);
$policy_query_select_statement->setExceptionMessage($policy_query_select_statement_exception_message);
$policy_query_select_statement->setExpect(1)->setHideResponse(true);
$policy->getContainer()->removeEdgesByName(PolicyToApplication::getName());

$policy_clone_query_clone = clone $policy;
$policy_clone_query = ArangoDB::start($policy_clone_query_clone);

$policy_query_delete = $policy_query->remove();
$policy_query_delete->pushStatementsPreliminary($policy_query_select_statement);
foreach ($edges as $edge_name) $policy_query_delete->pushEntitySkips($policy->useEdge($edge_name)->vertex());

$policy_query_delete_response = $policy_query_delete->run();
if (null !== $policy_query_delete_response) Output::print(true);
Output::print(false);
