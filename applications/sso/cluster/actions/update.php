<?PHP

namespace applications\sso\cluster\actions;

use Knight\armor\Output;
use Knight\armor\Request;
use Knight\armor\Language;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\operations\Update;
use ArangoDB\entity\Vertex;
use ArangoDB\entity\common\Arango;
use ArangoDB\operations\common\Handling;

use applications\iam\user\database\Vertex as User;
use applications\iam\user\database\edges\UserToCluster;
use applications\iam\policy\database\Vertex as Policy;
use applications\sso\cluster\database\Vertex as Cluster;
use applications\sso\cluster\database\edges\ClusterToUser;

use extensions\Navigator;

Policy::mandatories('sso/cluster/action/update');

$cluster_key_value = parse_url($_SERVER[Navigator::REQUEST_URI], PHP_URL_PATH);
$cluster_key_value = basename($cluster_key_value);

$user = User::Login();
$user_query = ArangoDB::start($user);

$cluster = $user->useEdge(UserToCluster::getName())->vertex();
$cluster->getField(Arango::KEY)->setProtected(false)->setRequired(true)->setValue($cluster_key_value);

$cluster_query = ArangoDB::start($cluster);
$cluster->useEdge(ClusterToUser::getName())->vertex($user);
$cluster_select = $cluster_query->select();
$cluster_select->getLimit()->set(1);
$cluster_select_return = 'RETURN 1';
$cluster_select->getReturn()->setPlain($cluster_select_return);
$cluster_select_statement = $cluster_select->getStatement();
$cluster_select_statement->setExpect(1)->setHideResponse(true);
$cluster->getContainer()->removeEdgesByName(ClusterToUser::getName());

$cluster->setFromAssociative((array)Request::post());
$cluster->getField(Arango::KEY)->setValue($cluster_key_value);

$cluster_query_update = $cluster_query->update();
$cluster_query_update->setReplace(true);
$cluster_query_update_return = 'RETURN' . chr(32) . Handling::RNEW;
$cluster_query_update->getReturn()->setPlain($cluster_query_update_return);
$cluster_query_update->setEntityEnableReturns($cluster);

$management = [];

foreach (Vertex::MANAGEMENT as $field_name) {
    $cluster_field_name = Update::SEARCH . chr(46) . $field_name;
    $cluster->getField($field_name)->setSafeModeDetached(false)->setRequired(true)->setValue($cluster_field_name);
    array_push($management, $cluster_field_name);
}

if (!!$errors = $cluster->checkRequired(true)->getAllFieldsWarning()) {
	Language::dictionary(__file__);
    $notice = __namespace__ . '\\' . 'notice';
    $notice = Language::translate($notice);
    Output::concatenate('notice', $notice);
    Output::concatenate('errors', $errors);
    Output::print(false);
}

$cluster_fields = $cluster->getFields();
foreach ($cluster_fields as $field) $field->setRequired(true);

$cluster_query_update->pushStatementSkipValues(...$management);
$cluster_query_update->pushStatementsPreliminary($cluster_select_statement);
$cluster_query_update_response = $cluster_query_update->run();
if (null === $cluster_query_update_response
    || empty($cluster_query_update_response)) Output::print(false);

$cluster = new Cluster();
$cluster->setSafeMode(false)->setReadMode(true);
$cluster_value = reset($cluster_query_update_response);
$cluster->setFromAssociative($cluster_value, $cluster_value);
$cluster_value = $cluster->getAllFieldsValues(false, false);
Output::concatenate(Output::APIDATA, $cluster_value);
Output::print(true);
