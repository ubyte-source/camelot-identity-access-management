<?PHP

namespace applications\iam\policy\actions;

use Knight\armor\Output;
use Knight\armor\Language;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\Statement;
use ArangoDB\operations\common\Choose;
use ArangoDB\entity\Vertex;
use ArangoDB\entity\common\Arango;

use applications\iam\user\database\Vertex as User;
use applications\iam\user\database\edges\UserToApplication;
use applications\iam\policy\database\Vertex as Policy;
use applications\iam\policy\forms\Matrioska;
use applications\sso\application\database\edges\ApplicationToPolicy;

use extensions\Navigator;

Policy::mandatories('iam/policy/action/detail');

$user = User::login();
$user_query = ArangoDB::start($user);

$policy = $user->useEdge(UserToApplication::getName())->vertex()->useEdge(ApplicationToPolicy::getName())->vertex();
$policy_fields = $policy->getFields();
foreach ($policy_fields as $field) $field->setProtected(true);

$policy_key_value = parse_url($_SERVER[Navigator::REQUEST_URI], PHP_URL_PATH);
$policy_key_value = basename($policy_key_value);
$policy->getField(Arango::KEY)->setProtected(false)->setRequired(true)->setValue($policy_key_value);

if (!!$errors = $policy->checkRequired()->getAllFieldsWarning()) {
    Language::dictionary(__file__);
    $notice = __namespace__ . '\\' . 'notice';
    $notice = Language::translate($notice);
    Output::concatenate('notice', $notice);
    Output::concatenate('errors', $errors);
    Output::print(false);
}

$user_query_select = $user_query->select();
$user_query_select->getLimit()->set(1);

$owner = $policy->getField(Vertex::OWNER)->getName();

$user_query_select_return = new Statement();
$user_query_select_return->append('LET traversal_vertex = REMOVE_NTH(' . $user_query_select->getPointer(Choose::TRAVERSAL_VERTEX) . ', 0)');
$user_query_select_return->append('LET traversal_vertex_last = LAST(traversal_vertex)');
$user_query_select_return->append('LET owner = DOCUMENT(' . User::COLLECTION . ', traversal_vertex_last.' . $owner . ')');
$user_query_select_return->append('LET policy = MERGE(traversal_vertex_last, {' . $owner . ': owner})');
$user_query_select_return->append('LET application = FIRST(traversal_vertex)');
$user_query_select_return->append('RETURN MERGE(policy, {application: application})');
$user_query_select->getReturn()->setFromStatement($user_query_select_return);

$user_query_select_response = $user_query_select->run();
if (null === $user_query_select_response
    || empty($user_query_select_response)) Output::print(false);

$matrioska = new Matrioska();
$matrioska->setSafeMode(false)->setReadMode(true);
$matrioska_value = reset($user_query_select_response);
$matrioska->setFromAssociative($matrioska_value, $matrioska_value);
$matrioska_value = $matrioska->getAllFieldsValues(false, false);

Output::concatenate(Output::APIDATA, $matrioska_value);
Output::print(true);
