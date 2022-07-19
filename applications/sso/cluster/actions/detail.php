<?PHP

namespace applications\sso\cluster\actions;

use Knight\armor\Output;
use Knight\armor\Language;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\Statement;
use ArangoDB\operations\common\Choose;
use ArangoDB\entity\Vertex;
use ArangoDB\entity\common\Arango;

use applications\iam\user\database\Vertex as User;
use applications\iam\user\database\edges\UserToCluster;
use applications\iam\policy\database\Vertex as Policy;
use applications\sso\cluster\forms\Cluster;

use extensions\Navigator;

Policy::mandatories('sso/cluster/action/detail');

$user = User::login();
$user_query = ArangoDB::start($user);

$cluster = $user->useEdge(UserToCluster::getName())->vertex();
$cluster_fields = $cluster->getFields();
foreach ($cluster_fields as $field) $field->setProtected(true);

$cluster_key_value = parse_url($_SERVER[Navigator::REQUEST_URI], PHP_URL_PATH);
$cluster_key_value = basename($cluster_key_value);
$cluster->getField(Arango::KEY)->setProtected(false)->setRequired(true)->setValue($cluster_key_value);

if (!!$errors = $cluster->checkRequired()->getAllFieldsWarning()) {
    Language::dictionary(__file__);
    $notice = __namespace__ . '\\' . 'notice';
    $notice = Language::translate($notice);
    Output::concatenate('notice', $notice);
    Output::concatenate('errors', $errors);
    Output::print(false);
}

$user_query_select = $user_query->select();
$user_query_select->getLimit()->set(1);
$user_query_select_vertex = $user_query_select->getPointer(Choose::VERTEX);

$owner = $cluster->getField(Vertex::OWNER)->getName();

$user_query_select_return = new Statement();
$user_query_select_return->append('LET owner = DOCUMENT(' . User::COLLECTION . chr(44) . chr(32) . $user_query_select_vertex . chr(46) . $owner . ')');
$user_query_select_return->append('LET cluster = MERGE(' . $user_query_select_vertex . ',' . chr(32) . '{' . $owner . ': owner})');
$user_query_select_return->append('RETURN cluster');

$user_query_select->getReturn()->setFromStatement($user_query_select_return);
$user_query_select_response = $user_query_select->run();
if (null === $user_query_select_response
    || empty($user_query_select_response)) Output::print(false);

$cluster = new Cluster();
$cluster->setSafeMode(false)->setReadMode(true);
$cluster_value = reset($user_query_select_response);
$cluster->setFromAssociative($cluster_value);
$cluster_value = $cluster->getAllFieldsValues(false, false);

Output::concatenate(Output::APIDATA, $cluster_value);
Output::print(true);
