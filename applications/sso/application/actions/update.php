<?PHP

namespace applications\sso\application\actions;

use Knight\armor\Output;
use Knight\armor\Request;
use Knight\armor\Language;

use Entity\Map;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\Statement;
use ArangoDB\operations\common\Choose;
use ArangoDB\operations\Update;
use ArangoDB\entity\Vertex;
use ArangoDB\entity\common\Arango;
use ArangoDB\operations\common\Handling;

use applications\iam\user\database\Vertex as User;
use applications\iam\user\database\edges\UserToUser;
use applications\iam\user\database\edges\UserToApplication;
use applications\iam\policy\database\Vertex as Policy;
use applications\sso\application\database\Vertex as Application;
use applications\sso\application\database\edges\ApplicationToUser;
use applications\sso\application\database\edges\ApplicationToCluster;
use applications\sso\cluster\database\edges\ClusterToUser;
use applications\sso\application\forms\Matrioska;

use extensions\Navigator;

Policy::mandatories('sso/application/action/update');

$application_key_value = parse_url($_SERVER[Navigator::REQUEST_URI], PHP_URL_PATH);
$application_key_value = basename($application_key_value);

$user = User::login();
$user_query = ArangoDB::start($user);

$application = $user->useEdge(UserToApplication::getName())->vertex();
$application_check = clone $application;
$application_check->getField(Arango::KEY)->setProtected(false)->setValue($application_key_value);

if (null !== Request::post('share') || null !== Request::post('cluster')) {
    $application_field_owner = $application->getField(Vertex::OWNER);
    $application_field_owner->setProtected(false)->setRequired(true)->setValue($user->getField(Arango::KEY)->getValue());
    $application_check->addFieldClone($application_field_owner);
}

$application_check_query = ArangoDB::start($application_check);
$application_check->useEdge(ApplicationToUser::getName())->vertex($user);
$application_check_select = $application_check_query->select();
$application_check_select->getLimit()->set(1);
$application_check_select_return = 'RETURN 1';
$application_check_select->getReturn()->setPlain($application_check_select_return);
$application_check_select_statement = $application_check_select->getStatement();
$application_check_select_statement->setExpect(1)->setHideResponse(true);

$application_uploads = array_column($_FILES, 'tmp_name');
$application_uploads_keys = array_keys($_FILES);
$application_uploads = array_combine($application_uploads_keys, $application_uploads);
$application->setFromAssociative((array)Request::post(), $application_uploads);
$application->getField(Arango::KEY)->setProtected(false)->setRequired(true)->setValue($application_key_value);

$application_field_basename_value = $application->getField('basename')->getValue();
if (Application::checkLocalExists($application_field_basename_value)) $application->getField('link')->setProtected(true);

$application_warnings = $application->checkRequired(true)->getAllFieldsWarning();

$application_clone = clone $application;
$application_clone_fields = $application_clone->getFields();
foreach ($application_clone_fields as $field) $field->setRequired(true);

$application_clone_query = ArangoDB::start($application_clone);
$application_clone_query_update = $application_clone_query->update();

$management = [];
$management_input = Vertex::MANAGEMENT;
if (!array_key_exists($icon = $application->getField('icon')->getName(), $application_uploads)) array_push($management_input, $icon);
foreach ($management_input as $field_name) {
    $application_clone_field_name = Update::SEARCH . chr(46) . $field_name;
    $application_clone->getField($field_name)->setSafeModeDetached(false)->setRequired(true)->setValue($application_clone_field_name);
    array_push($management, $application_clone_field_name);
}

$application_clone_query_update->setReplace(true);
$application_clone_query_update->pushStatementsPreliminary($application_check_select_statement);
$application_clone_query_update->pushStatementSkipValues(...$management);
$application_clone_query_update_return = 'RETURN' . chr(32) . Handling::RNEW;
$application_clone_query_update->getReturn()->setPlain($application_clone_query_update_return);
$application_clone_query_update->setEntityEnableReturns($application_clone);
$application_clone_query_update_transaction = $application_clone_query_update->getTransaction();

$remove = [];
$writer = [];
$preliminary = [];

$matrioska = new Matrioska();
$matrioska->setFromAssociative((array)Request::post());
$matrioska->getField('cluster')->setRequired(false);

if (false === $matrioska->getField('cluster')->isDefault()) {

    $matrioska->getField('cluster')->setProtected(false)->setRequired(true);

    $application_to_cluster = $application->useEdge(ApplicationToCluster::getName());
    $application_to_cluster_collection = $application_to_cluster->getCollectionName();
    array_push($writer, $application_to_cluster_collection);

    $remove_application_edges_query = ArangoDB::start($application);
    $remove_application_edges = $remove_application_edges_query->remove();
    $remove_application_edges->setActionOnlyEdges(true);
    $remove_application_edges_transaction = $remove_application_edges->getTransaction();
    array_push($remove, $remove_application_edges_transaction);

    $cluster = $matrioska->getField('cluster')->getValue();
    if (empty($cluster->checkRequired()->getAllFieldsWarning())) {
        $cluster = $application_to_cluster->vertex($cluster);
        $cluster->useEdge(ClusterToUser::getName())->vertex($user);
        $cluster_query = ArangoDB::start($cluster);
        $cluster_query_select = $cluster_query->select();
        $cluster_query_select->getLimit()->set(1);
        $cluster_query_select_return = 'RETURN 1';
        $cluster_query_select->getReturn()->setPlain($cluster_query_select_return);
        $cluster_query_select_statement = $cluster_query_select->getStatement();
        $cluster_query_select_statement->setExpect(1)->setHideResponse(true);
        array_push($preliminary, $cluster_query_select_statement);
        $cluster->getContainer()->removeEdgesByName(ClusterToUser::getName());
    }
}

Language::dictionary(__file__);
$exception_message = __namespace__ . '\\' . 'exception' . '\\';

if (null !== Request::post('share')) {
    $share = clone $application_check;
    $share_query = ArangoDB::start($share);
    $share_query_remove = $share_query->select();
    $share_query_remove_edge = $share_query_remove->getPointer(Choose::EDGE);
    $share_query_remove_vertex = $share_query_remove->getPointer(Choose::VERTEX);
    $share_to_users_collection = $share->useEdge(ApplicationToUser::getName())->getCollectionName();

    $assign = $matrioska->getField('share')->getValue();

    $share_query_remove_return = new Statement();
    $share_query_remove_return->append('FILTER');
    $share_query_remove_return->append($share_query_remove_vertex . chr(46) . '_key != $0');
    $share_query_remove_return->append('REMOVE');
    $share_query_remove_return->append($share_query_remove_edge);
    $share_query_remove_return->append('IN');
    $share_query_remove_return->append($share_to_users_collection);
    $share_query_remove_return->append('OPTIONS');
    $share_query_remove_return->append('{waitForSync: true, ignoreErrors: true}');
    $share_query_remove_return->append('RETURN 1');
    $share_query_remove->getReturn()->setFromStatement($share_query_remove_return, $user->getField(Arango::KEY)->getValue());
    $share_query_remove_statement = $share_query_remove->getStatement();
    $share_query_remove_statement->setHideResponse(true);

    array_push($preliminary, $share_query_remove_statement);
    array_push($writer, $share_to_users_collection);

    if (false === $matrioska->getField('share')->isDefault()) {
        $assign = $matrioska->getField('share')->getValue();
        foreach ($assign as $destination) if (empty($destination->checkRequired()->getAllFieldsWarning())) {
            $share = $application->useEdge(ApplicationToUser::getName())->vertex($destination);

            $check_user = User::login();
            $check_user_query = ArangoDB::start($check_user);
            $check_user_to_user = $check_user->useEdge(UserToUser::getName());
            $check_user_to_user->vertex($share);
            $check_user_to_user->branch()->vertex()->useEdge(UserToUser::getName(), $check_user_to_user);

            $check_user_query_select = $check_user_query->select();
            $check_user_query_select->getLimit()->set(1);
            $check_user_query_select_return = 'RETURN 1';
            $check_user_query_select->getReturn()->setPlain($check_user_query_select_return);
            $check_user_query_select_statement = $check_user_query_select->getStatement();
            $check_user_query_select_statement_exception_message = Language::translate($exception_message . 'share', $destination->getField(Arango::KEY)->getValue());
            $check_user_query_select_statement->setExceptionMessage($check_user_query_select_statement_exception_message);
            $check_user_query_select_statement->setExpect(1)->setHideResponse(true);
            array_push($preliminary, $check_user_query_select_statement);
        }
    }
}

$matrioska_warnings = $matrioska->checkRequired()->getAllFieldsWarning();

$application_unique = new Application();
$application_unique_fields = $application_unique->getFields();
foreach ($application_unique_fields as $field) $field->setProtected(true); 

$application_unique->getField('basename')->setProtected(false)->setRequired(true);
$application_unique->setFromAssociative((array)Request::post());
$application_unique_warnings = $application_unique->checkRequired()->getAllFieldsWarning();

if (!!$errors = array_merge($application_warnings, $matrioska_warnings, $application_unique_warnings)) {
    Language::dictionary(__file__);
    $notice = __namespace__ . '\\' . 'notice';
    $notice = Language::translate($notice);
    Output::concatenate('notice', $notice);
    Output::concatenate('errors', $errors);
    Output::print(false);
}

$application_unique_query = ArangoDB::start($application_unique);
$application_unique_query_select = $application_unique_query->select();
$application_unique_query_select->getLimit()->set(2);
$application_unique_query_select_statement = $application_unique_query_select->getStatement();
$application_unique_query_select_statement_exception_message = $exception_message . 'constrain';
$application_unique_query_select_statement_exception_message = Language::translate($application_unique_query_select_statement_exception_message);
$application_unique_query_select_statement->setExceptionMessage($application_unique_query_select_statement_exception_message);
$application_unique_query_select_statement->setExpect(1)->setHideResponse(true);

if (empty($preliminary)) {
    $application_clone_query_update_transaction->pushStatementsFinal($application_unique_query_select_statement);
    $application_clone_query_update_transaction_response = $application_clone_query_update_transaction->commit();
    if (null !== $application_clone_query_update_transaction_response) Output::print(true);
    Output::print(false);
}

$insert_application_edges_query = ArangoDB::start($application);
$insert_application_edges = $insert_application_edges_query->insert();
$insert_application_edges->setActionOnlyEdges(true);
$insert_application_edges->pushStatementsPreliminary(...$preliminary);
$insert_application_edges->pushTransactionsPreliminary(...$remove);
$insert_application_edges->pushTransactionsFinal($application_clone_query_update_transaction);
$insert_application_edges->pushStatementsFinal($application_unique_query_select_statement);
$insert_application_edges_transaction = $insert_application_edges->getTransaction();
$insert_application_edges_transaction->openCollectionsWriteMode(...$writer);
$insert_application_edges_transaction_response = $insert_application_edges_transaction->commit();
if (null === $insert_application_edges_transaction_response
    || empty($insert_application_edges_transaction_response)) Output::print(false);

$application = new Application();
$application->setSafeMode(false)->setReadMode(true);
$application_value = reset($insert_application_edges_transaction_response);
$application->setFromAssociative($application_value, $application_value);
$application_value = $application->getAllFieldsValues(false, false);
Output::concatenate('document', $application_value);
Output::print(true);
