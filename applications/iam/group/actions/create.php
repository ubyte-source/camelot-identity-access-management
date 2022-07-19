<?PHP

namespace applications\iam\group\actions;

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
use applications\iam\user\database\edges\UserToUser;
use applications\iam\user\database\edges\UserToGroup;
use applications\iam\group\database\edges\GroupToUser;
use applications\iam\group\database\edges\GroupToGroup;
use applications\iam\group\database\Vertex as Group;
use applications\iam\policy\database\Vertex as Policy;
use applications\iam\group\forms\Matrioska;

use extensions\Navigator;

Policy::mandatories('iam/group/action/create');

$user = User::login();
$user_field_key = $user->getField(Arango::KEY);

$user_query = ArangoDB::start($user);

$check_user_select = $user_query->select();
$check_user_select->getLimit()->set(1);
$check_user_select_return = 'RETURN 1';
$check_user_select->getReturn()->setPlain($check_user_select_return);
$check_user_select_statement = $check_user_select->getStatement();
$check_user_select_statement->setExpect(1)->setHideResponse(true);

$user_to_group = $user->useEdge(UserToGroup::getName());
$user_to_group->getField('admin')->setProtected(false)->setValue(true);

$group = $user_to_group->vertex();
$group->setFromAssociative((array)Request::post());

foreach (Vertex::MANAGEMENT as $field_name) $group->getField($field_name)->setProtected(false)->setRequired(true)->setValue($user->getField(Arango::KEY)->getValue());

$group_fields_values = $group->getAllFieldsValues();
$group_fields_values = serialize($group_fields_values) . microtime(true) . Navigator::getFingerprint();
$group_fields_values = hash('sha512', $group_fields_values);

$group_field_hash = $group->addField('hash');
$group_field_hash_name = $group_field_hash->getName();
$group_field_hash_pattern = Validation::factory('ShowString');
$group_field_hash->setPatterns($group_field_hash_pattern);
$group_field_hash->addUniqueness();

$group_field_hash->setProtected(false)->setRequired(true);
$group_field_hash->setValue($group_fields_values);

Language::dictionary(__file__);
$exception_message = __namespace__ . '\\' . 'exception' . '\\';

$group_unique = new Group();
$group_unique->addFieldClone($group_field_hash);
$group_unique->getField('hash')->setProtected(false)->setValue($group_fields_values);
$group_unique_query = ArangoDB::start($group_unique);
$group_unique_query_select = $group_unique_query->select();
$group_unique_query_select->getLimit()->set(1);
$group_unique_query_select_return = 'RETURN 1';
$group_unique_query_select->getReturn()->setPlain($group_unique_query_select_return);
$group_unique_query_select_statement = $group_unique_query_select->getStatement();
$group_unique_query_select_statement_exception_message = $exception_message . 'hash';
$group_unique_query_select_statement_exception_message = Language::translate($group_unique_query_select_statement_exception_message);
$group_unique_query_select_statement->setExceptionMessage($group_unique_query_select_statement_exception_message);
$group_unique_query_select_statement->setExpect(0)->setHideResponse(true);

$matrioska = new Matrioska();
$matrioska->setFromAssociative((array)Request::post());

$group_anchor = [
    $matrioska->getField('share')->getName() => GroupToUser::getName(),
    $matrioska->getField('group')->getName() => GroupToGroup::getName(),
    $matrioska->getField('users')->getName() => GroupToUser::getName()
];

$skip = [];
$preliminary = [];

foreach ($group_anchor as $field_name => $edge_name) if (false === $matrioska->getField($field_name)->isDefault()) {
    $assign = $matrioska->getField($field_name)->getValue();
    foreach ($assign as $destination) if (empty($destination->checkRequired()->getAllFieldsWarning())) {
        $edge = $group->useEdge($edge_name);
        if ($field_name === 'share') $edge->getField('admin')->setProtected(false)->setRequired(true)->setValue(true);

        $target = $edge->vertex($destination);

        $check_user = User::login();
        $check_user_query =  ArangoDB::start($check_user);

        $used = explode('To', $edge_name);
        $used = end($used);
        $edge_reverse = $check_user->useEdge('UserTo' . $used);
        $edge_reverse->vertex($target);
        $check_user->useEdge(UserToUser::getName())->vertex()->useEdge('UserTo' . $used, $edge_reverse);

        $check_user_query_select = $check_user_query->select();
        $check_user_query_select->getLimit()->set(1);
        $check_user_query_select_return = 'RETURN 1';
        $check_user_query_select->getReturn()->setPlain($check_user_query_select_return);
        $check_user_query_select_statement = $check_user_query_select->getStatement();
        $check_user_query_select_statement_exception_message = Language::translate($exception_message . $field_name, $destination->getField(Arango::KEY)->getValue());
        $check_user_query_select_statement->setExceptionMessage($check_user_query_select_statement_exception_message);
        $check_user_query_select_statement->setExpect(1)->setHideResponse(true);
        array_push($preliminary, $check_user_query_select_statement);
        array_push($skip, $target);

        if ($field_name !== 'group') continue;

        $clone = clone $target;
        $clone_query = ArangoDB::start($clone);

        $clone_edge = $clone->useEdge(GroupToGroup::getName());
        $clone_edge->vertex($group);
        $clone_edge->branch()->vertex()->useEdge(GroupToGroup::getName(), $clone_edge);

        $clone_query_select = $clone_query->select();
        $clone_query_select->getLimit()->set(1);
        $clone_query_select_return = 'RETURN 1';
        $clone_query_select->getReturn()->setPlain($clone_query_select_return);
        $clone_query_select_statement = $clone_query_select->getStatement();
        $clone_query_select_statement_exception_message = $exception_message . 'loop';
        $clone_query_select_statement_exception_message = Language::translate($clone_query_select_statement_exception_message, $destination->getField(Arango::KEY)->getValue());
        $clone_query_select_statement->setExceptionMessage($clone_query_select_statement_exception_message);
        $clone_query_select_statement->setExpect(0)->setHideResponse(true);
        array_push($preliminary, $clone_query_select_statement);
    }
}

if (!!$errors = $group->checkRequired()->getAllFieldsWarning()) {
    Language::dictionary(__file__);
    $notice = __namespace__ . '\\' . 'notice';
    $notice = Language::translate($notice);
    Output::concatenate('notice', $notice);
    Output::concatenate('errors', $errors);
    Output::print(false);
}

$management = [];

$group_remove_hash = clone $group;
$group_remove_hash_fields = $group_remove_hash->getFields();
foreach ($group_remove_hash_fields as $field) $field->setRequired(true);
$group_remove_hash->getField('hash')->setRequired(false);
$group_remove_hash_query = ArangoDB::start($group_remove_hash);
$group_remove_hash_query->setUseAdapter(false);
$group_remove_hash_query_update = $group_remove_hash_query->update();
$group_remove_hash_query_update->setReplace(true);

foreach (Vertex::MANAGEMENT as $field_name) {
    $group_remove_hash_field_hash_value = Update::SEARCH . chr(46) . $field_name;
    $group_remove_hash->getField($field_name)->setSafeModeDetached(false)->setValue($group_remove_hash_field_hash_value);
    array_push($management, $group_remove_hash_field_hash_value);
}

$group_remove_hash_query_update->pushStatementSkipValues(...$management);
$group_remove_hash_query_update_transaction = $group_remove_hash_query_update->getTransaction();

$user_query_insert = $user_query->insert();
$user_query_insert->pushEntitySkips($user, ...$skip);
$user_query_insert->pushStatementsPreliminary($check_user_select_statement, $group_unique_query_select_statement, ...$preliminary);
$user_query_insert->pushTransactionsFinal($group_remove_hash_query_update_transaction);
$user_query_insert_return = 'RETURN' . chr(32) . Handling::RNEW;
$user_query_insert->getReturn()->setPlain($user_query_insert_return);
$user_query_insert->setEntityEnableReturns($group);
$user_query_insert_response = $user_query_insert->run();
if (null === $user_query_insert_response
    || empty($user_query_insert_response)) Output::print(false);

$group = new Group();
$group->setSafeMode(false)->setReadMode(true);
$group_value = reset($user_query_insert_response);
$group->setFromAssociative($group_value, $group_value);
$group_value = $group->getAllFieldsValues(false, false);
Output::concatenate(Output::APIDATA, $group_value);
Output::print(true);
