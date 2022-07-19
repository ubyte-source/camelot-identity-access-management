<?PHP

namespace applications\iam\policy\actions;

use Knight\armor\Output;
use Knight\armor\Request;
use Knight\armor\Language;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\entity\Edge;
use ArangoDB\entity\Vertex;
use ArangoDB\entity\common\Arango;
use ArangoDB\operations\common\Handling;

use applications\iam\user\database\Vertex as User;
use applications\iam\user\database\edges\UserToUser;
use applications\iam\user\database\edges\UserToPolicy;
use applications\iam\user\database\edges\UserToGroup;
use applications\iam\user\database\edges\UserToApplication;
use applications\iam\policy\database\Vertex as Policy;
use applications\iam\group\database\edges\GroupToGroup;
use applications\iam\group\database\edges\GroupToPolicy;
use applications\sso\application\database\edges\ApplicationToPolicy;

use extensions\Navigator;

const TARGET_COLLECTION_ASSIGNMENT_ADMITTED = ['Group', 'User'];
const TARGET_OPTION = 'fields';

Policy::mandatories('iam/policy/action/set/%');

$user = User::login();
$user_query = ArangoDB::start($user);

$target_id = Request::post($user->getField(Arango::ID)->getName());
$target_id = null !== $target_id ? explode(Arango::SEPARATOR, $target_id) : [];
$target_id_count = count($target_id);

if (2 !== $target_id_count) Output::print(false);

list($target_collection_name, $target_key) = $target_id;
if (!in_array($target_collection_name, TARGET_COLLECTION_ASSIGNMENT_ADMITTED)) Output::print(false);

$target_edge_name = 'UserTo' . $target_collection_name;
$target_edge = $user->useEdge($target_edge_name);

$target_vertex = $target_edge->vertex();
$target_vertex_fields = $target_vertex->getFields();
foreach ($target_vertex_fields as $field) $field->setProtected(true);

$target_vertex->getField(Arango::KEY)->setProtected(false)->setRequired(true)->setValue($target_key);
if ($target_collection_name === 'Group') $target_vertex->getField(Vertex::OWNER)->setProtected(false)->setRequired(true)->setValue($user->getField(Arango::KEY)->getValue());

$target_vertex_warnings = $target_vertex->checkRequired()->getAllFieldsWarning();

$user->useEdge(UserToUser::getName())->vertex()->useEdge($target_edge_name, $target_edge);
$user_query_select = $user_query->select();
$user_query_select->getLimit()->set(1);
$user_query_select_return = 'RETURN 1';
$user_query_select->getReturn()->setPlain($user_query_select_return);
$user_query_select_statement = $user_query_select->getStatement();
$user_query_select_statement->setExpect(1)->setHideResponse(true);

$target_vertex_to_policy = $target_vertex->useEdge($target_collection_name . 'ToPolicy');
$target_vertex_to_policy_fields = $target_vertex_to_policy->getFields();
$target_vertex_to_policy_fields_required_name = $target_vertex_to_policy->getAllFieldsRequiredName();

$target_vertex_to_policy_keys_post = Request::post(TARGET_OPTION);
$target_vertex_to_policy_keys_post = null === $target_vertex_to_policy_keys_post || !is_array($target_vertex_to_policy_keys_post) ? [] : $target_vertex_to_policy_keys_post;

$target_vertex_query = ArangoDB::start($target_vertex);
$target_vertex_query_upsert = $target_vertex_query->upsert();
$target_vertex_query_upsert->setActionOnlyEdges(true);
$target_vertex_query_upsert->setReplace(true);
$target_vertex_query_upsert->pushEntitySkips($target_edge);

Language::dictionary(__file__);
$exception_message = __namespace__ . '\\' . 'exception' . '\\';

$policies = UserToPolicy::getPolicies();
$policies = array_column($policies, 'route');

foreach ($target_vertex_to_policy_fields as $field) {
    $field->setProtected(true)->setRequired(false);
    $field_name = $field->getName();
    if (false === in_array($field_name, $target_vertex_to_policy_fields_required_name)) continue;

    $field->setProtected(false)->setRequired(true);
    if (array_key_exists($field_name, $target_vertex_to_policy_keys_post)) {
        $field->setValue($target_vertex_to_policy_keys_post[$field_name]);
    } else {
        $policy_document_field_value = Handling::ROLD . chr(46) . $field_name;
        $target_vertex_query_upsert->pushStatementSkipValues($policy_document_field_value);
        $field->setSafeModeDetached(false)->setValue($policy_document_field_value);
        continue;
    }

    $field_required_policy = 'iam/policy/action/set/' . $field_name;
    if (in_array($field_required_policy, $policies)) continue;

    Language::dictionary(__file__);
    $notice = __namespace__ . '\\' . 'policy';
    $notice = Language::translate($notice, str_replace(chr(47), Language::SHASH_ESCAPE, $field_required_policy));
    Output::concatenate('notice', $notice);
    Output::concatenate('errors', $errors);
    Output::print(false);
}

$check_policy = User::login();
$check_policy_query = ArangoDB::start($check_policy);

$check_policy_edge = $check_policy->useEdge(UserToPolicy::getName());
if (array_key_exists($check_policy_edge->getField('reassignment')->getName(), $target_vertex_to_policy_keys_post)) $check_policy_edge->getField('reassignment')->setProtected(false)->setValue(true);

$policy_key_value = parse_url($_SERVER[Navigator::REQUEST_URI], PHP_URL_PATH);
$policy_key_value = basename($policy_key_value);

$check_policy_vertex = $check_policy_edge->vertex();
$check_policy_vertex->getField(Arango::KEY)->setProtected(false)->setValue($policy_key_value);

$check_policy->useEdge(UserToApplication::getName())->vertex()->useEdge(ApplicationToPolicy::getName(), $check_policy_edge);
$check_policy_group = $check_policy->useEdge(UserToGroup::getName())->vertex();
$check_policy_group->useEdge(GroupToGroup::getName())->setForceDirection(Edge::INBOUND)->vertex()->useEdge(GroupToPolicy::getName(), $check_policy_edge);
$check_policy_group->useEdge(GroupToPolicy::getName(), $check_policy_edge);

$check_policy_query_select = $check_policy_query->select();
$check_policy_query_select->getLimit()->set(1);
$check_policy_query_select_return = 'RETURN 1';
$check_policy_query_select->getReturn()->setPlain($check_policy_query_select_return);
$check_policy_query_select_statement = $check_policy_query_select->getStatement();
$check_policy_query_select_statement_exception_message = $exception_message . 'manager';
$check_policy_query_select_statement_exception_message = Language::translate($check_policy_query_select_statement_exception_message);
$check_policy_query_select_statement->setExceptionMessage($check_policy_query_select_statement_exception_message);
$check_policy_query_select_statement->setExpect(1)->setHideResponse(true);

$target_vertex_to_policy_vertex = $target_vertex_to_policy->vertex();
$target_vertex_to_policy_vertex_fields = $target_vertex_to_policy_vertex->getFields();
foreach ($target_vertex_to_policy_vertex_fields as $field) $field->setProtected(true);

$target_vertex_to_policy_vertex->getField(Arango::KEY)->setProtected(false)->setRequired(true)->setValue($policy_key_value);
$target_vertex_to_policy_vertex_warnings = $target_vertex_to_policy_vertex->checkRequired()->getAllFieldsWarning();

if (!!$errors = array_merge($target_vertex_warnings, $target_vertex_to_policy_vertex_warnings)) {
    Language::dictionary(__file__);
    $notice = __namespace__ . '\\' . 'notice';
    $notice = Language::translate($notice);
    Output::concatenate('notice', $notice);
    Output::concatenate('errors', $errors);
    Output::print(false);
}

$target_vertex_query_upsert->pushStatementsPreliminary($user_query_select_statement, $check_policy_query_select_statement);
$target_vertex_query_upsert_response = $target_vertex_query_upsert->run();
if (null !== $target_vertex_query_upsert_response) Output::print(true);
Output::print(false);
