<?PHP

namespace applications\sso\cluster\actions;

use Knight\armor\Output;
use Knight\armor\Request;
use Knight\armor\Language;

use Entity\Validation;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\operations\Update;
use ArangoDB\entity\Vertex;
use ArangoDB\entity\common\Arango;
use ArangoDB\operations\common\Handling;

use applications\iam\user\database\Vertex as User;
use applications\iam\user\database\edges\UserToCluster;
use applications\iam\policy\database\Vertex as Policy;
use applications\sso\cluster\database\Vertex as Cluster;

use extensions\Navigator;

Policy::mandatories('sso/cluster/action/create');

$user = User::login();
$user_query = ArangoDB::start($user);

$user_query_select = $user_query->select();
$user_query_select->getLimit()->set(1);
$user_query_select_return = 'RETURN 1';
$user_query_select->getReturn()->setPlain($user_query_select_return);
$user_query_select_statement = $user_query_select->getStatement();
$user_query_select_statement->setExpect(1)->setHideResponse(true);

$cluster = $user->useEdge(UserToCluster::getName())->vertex();
$cluster->setFromAssociative((array)Request::post());

foreach (Vertex::MANAGEMENT as $field_name) $cluster->getField($field_name)->setProtected(false)->setRequired(true)->setValue($user->getField(Arango::KEY)->getValue());

$cluster_fields_values = $cluster->getAllFieldsValues();
$cluster_fields_values = serialize($cluster_fields_values) . microtime(true) . Navigator::getFingerprint();
$cluster_fields_values = hash('sha512', $cluster_fields_values);

$cluster_field_hash = $cluster->addField('hash');
$cluster_field_hash_pattern = Validation::factory('ShowString');
$cluster_field_hash->setPatterns($cluster_field_hash_pattern);
$cluster_field_hash->addUniqueness();

$cluster_field_hash->setProtected(false)->setRequired(true);
$cluster_field_hash->setValue($cluster_fields_values);

Language::dictionary(__file__);
$exception_message = __namespace__ . '\\' . 'exception' . '\\';

$cluster_unique = new Cluster();
$cluster_unique->addFieldClone($cluster_field_hash);
$cluster_unique_field_hash = $cluster_unique->getField($cluster_field_hash->getName());
$cluster_unique_field_hash->setProtected(false);
$cluster_unique_field_hash->setValue($cluster_fields_values);

$cluster_unique_query = ArangoDB::start($cluster_unique);
$cluster_unique_query_select = $cluster_unique_query->select();
$cluster_unique_query_select->getLimit()->set(1);
$cluster_unique_query_select_return = 'RETURN 1';
$cluster_unique_query_select->getReturn()->setPlain($cluster_unique_query_select_return);
$cluster_unique_query_select_statement = $cluster_unique_query_select->getStatement();
$cluster_unique_query_select_statement_exception_message = $exception_message . 'hash';
$cluster_unique_query_select_statement_exception_message = Language::translate($cluster_unique_query_select_statement_exception_message);
$cluster_unique_query_select_statement->setExceptionMessage($cluster_unique_query_select_statement_exception_message);
$cluster_unique_query_select_statement->setExpect(0)->setHideResponse(true);

if (!!$errors = $cluster->checkRequired(true)->getAllFieldsWarning()) {
    Language::dictionary(__file__);
    $notice = __namespace__ . '\\' . 'notice';
    $notice = Language::translate($notice);
    Output::concatenate('notice', $notice);
    Output::concatenate('errors', $errors);
    Output::print(false);
}

$management = [];

$cluster_remove_hash = clone $cluster;
$cluster_remove_hash_fields = $cluster_remove_hash->getFields();
foreach ($cluster_remove_hash_fields as $field) $field->setRequired(true);
$cluster_remove_hash->getField('hash')->setRequired(false);
$cluster_remove_hash_query = ArangoDB::start($cluster_remove_hash);
$cluster_remove_hash_query->setUseAdapter(false);
$cluster_remove_hash_query_update = $cluster_remove_hash_query->update();
$cluster_remove_hash_query_update->setReplace(true);

foreach (Vertex::MANAGEMENT as $field_name) {
    $cluster_remove_hash_field_name = Update::SEARCH . chr(46) . $field_name;
    $cluster_remove_hash->getField($field_name)->setSafeModeDetached(false)->setValue($cluster_remove_hash_field_name);
    array_push($management, $cluster_remove_hash_field_name);
}

$cluster_remove_hash_query_update->pushStatementSkipValues(...$management);
$cluster_remove_hash_query_update_transaction = $cluster_remove_hash_query_update->getTransaction();

$query_insert = $user_query->insert();
$query_insert->pushEntitySkips($user);
$query_insert->pushStatementsPreliminary($user_query_select_statement, $cluster_unique_query_select_statement);
$query_insert->pushTransactionsFinal($cluster_remove_hash_query_update_transaction);
$query_insert_return = 'RETURN' . chr(32) . Handling::RNEW;
$query_insert->getReturn()->setPlain($query_insert_return);
$query_insert->setEntityEnableReturns($cluster);
$query_insert_response = $query_insert->run();
if (null === $query_insert_response
    || empty($query_insert_response)) Output::print(false);

$cluster = new Cluster();
$cluster->setSafeMode(false)->setReadMode(true);
$cluster_value = reset($query_insert_response);
$cluster->setFromAssociative($cluster_value, $cluster_value);
$cluster_value = $cluster->getAllFieldsValues(false, false);
Output::concatenate(Output::APIDATA, $cluster_value);
Output::print(true);
