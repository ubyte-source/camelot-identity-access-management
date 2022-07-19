<?PHP

namespace applications\sso\oauth\database;

use Knight\armor\Output;
use Knight\armor\Language;

use Entity\Validation;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\operations\common\Choose;
use ArangoDB\entity\common\Arango;
use ArangoDB\entity\Vertex as EVertex;
use ArangoDB\Statement;

use applications\sso\oauth\database\vertex\Map;

use extensions\Navigator;

class Vertex extends EVertex
{
	const COLLECTION = 'Oauth';

	const TOKEN = 'token';
	const AUTHORIZE = 'authorize';

	protected function initialize() : void
	{
		$name = $this->addField('name');
		$name_pattern = Validation::factory('ShowString');
		$name_pattern->setMax(32);
		$name->setPatterns($name_pattern);
		$name->setRequired();

		$authority = $this->addField('authority');
		$authority_validator = Validation::factory('ShowString');
		$authority->setPatterns($authority_validator);
		$authority->setRequired();

		$client_id = $this->addField('client_id');
		$client_id_validator = Validation::factory('ShowString');
		$client_id->setPatterns($client_id_validator);
		$client_id->setRequired();
		
		$client_secret = $this->addField('client_secret');
		$client_secret_validator = Validation::factory('ShowString');
		$client_secret->setPatterns($client_secret_validator);
		$client_secret->setRequired();

		$tenant = $this->addField('tenant');
		$tenant_validator = Validation::factory('ShowString');
		$tenant->setPatterns($tenant_validator);
		$tenant->setRequired();

		$authorize = $this->addField(static::AUTHORIZE);
		$authorize_validator = Validation::factory('ShowString');
		$authorize->setPatterns($authorize_validator);
		$authorize->setRequired();

		$token = $this->addField(static::TOKEN);
		$token_validator = Validation::factory('ShowString');
		$token->setPatterns($token_validator);
		$token->setRequired();

		$scope = $this->addField('scope');
		$scope_validator = Validation::factory('Chip');
		$scope->setPatterns($scope_validator);
		$scope->setRequired();

		$domain = $this->addField('domain');
		$domain_pattern = Validation::factory('Chip');
		$domain->setPatterns($domain_pattern);	
		
		$remote_whoami = $this->addField('remote_whoami');
		$remote_whoami_validator = Validation::factory('ShowString');
		$remote_whoami->setPatterns($remote_whoami_validator);
		$remote_whoami->setRequired();

		$fields_relation = $this->addField('fields_relation');
		$fields_relation_pattern_map = new Map();
        $fields_relation_pattern = Validation::factory('Matrioska', $fields_relation_pattern_map);
        $fields_relation_pattern->setMultiple(true);
		$fields_relation->setPatterns($fields_relation_pattern);
	}

	public function getURI(string $name) : string
	{
		$authority = $this->getField('authority');
		$authority_value = $authority->getValue();

		$tenant = $this->getField('tenant');
		$tenant_value = $tenant->getValue();

		$field = $this->getField($name);
		$field_value = $field->getValue();

		return $authority_value
			. chr(47) . $tenant_value
			. chr(47) . $field_value;
	}

	public function getParameters() : object
	{
		if (!!$errors = $this->checkRequired()->getAllFieldsWarning()) {
			Language::dictionary(__file__);
			$notice = __namespace__ . '\\' . 'notice';
			$notice = Language::translate($notice);
			Output::concatenate('notice', $notice);
			Output::concatenate('errors', $errors);
			Output::print(false);
		}

		$parameters = (object)$this->getAllFieldsValues(true, false);
		$parameters->response_type = 'code';
		$parameters->scope = implode(chr(32), $parameters->scope);

		return $parameters;
	}

	public static function get(int $key) :? self
	{
		$oauth = new static();
		$oauth_fields = $oauth->getFields();
		foreach ($oauth_fields as $field) $field->setProtected(true);
		
		$oauth->getField(Arango::KEY)->setProtected(false)->setRequired(true)->setValue($key);
		
		if (!!$errors = $oauth->checkRequired()->getAllFieldsWarning()) {
			Language::dictionary(__file__);
			$notice = __namespace__ . '\\' . 'notice';
			$notice = Language::translate($notice);
			Output::concatenate('notice', $notice);
			Output::concatenate('errors', $errors);
			Output::print(false);
		}

		$user_query = ArangoDB::start($oauth);
		$query_select = $user_query->select();
		$query_select->getLimit()->set(1);
		$query_select_return = 'RETURN' . chr(32) . $query_select->getPointer(Choose::VERTEX);
		$query_select->getReturn()->setPlain($query_select_return);
		$query_select_response = $query_select->run();
		if (null === $query_select_response
			|| empty($query_select_response)) return null;

		$oauth = new static();
		$oauth->setSafeMode(false)->setReadMode(true);
		$oauth_value = reset($query_select_response);
		$oauth->setFromAssociative($oauth_value, $oauth_value);
		return $oauth;
	}

	public static function check(string $domain) : array
	{
		$oauth = new static();
		$oauth_fields = $oauth->getFields();
		foreach ($oauth_fields as $field) $field->setProtected(true);

		$oauth_query_domain = $oauth->getField('domain');
		$oauth_query_domain = $oauth_query_domain->getName();

		$oauth_query = ArangoDB::start($oauth);
		$oauth_query_select = $oauth_query->select();
		$oauth_query_select->getLimit()->set(1);
		$oauth_query_select_main_iteration_vertex = $oauth_query_select->getPointer(Choose::VERTEX);

		$oauth_query_select_return_statement = new Statement();
		$oauth_query_select_return_statement->append('FILTER POSITION' . chr(40) . $oauth_query_select_main_iteration_vertex . chr(46) . $oauth_query_domain . chr(44) . chr(32) . '$0' . chr(41));
		$oauth_query_select_return_statement->append('RETURN');
		$oauth_query_select_return_statement->append($oauth_query_select_main_iteration_vertex);
		$oauth_query_select->getReturn()->setFromStatement($oauth_query_select_return_statement, $domain);
		$oauth_query_select_response = $oauth_query_select->run();
		if (null === $oauth_query_select_response
			|| empty($oauth_query_select_response)) return array();
		return reset($oauth_query_select_response)
			?: array();
	}
}
