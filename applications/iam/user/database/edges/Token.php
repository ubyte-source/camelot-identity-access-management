<?PHP

namespace applications\iam\user\database\edges;

use Knight\armor\Curl;
use Knight\armor\Cookie;
use Knight\armor\Language;
use Knight\armor\Output;

use Entity\Field;
use Entity\Validation;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\Statement;
use ArangoDB\entity\Edge;
use ArangoDB\entity\common\Arango;
use ArangoDB\operations\common\Choose;
use ArangoDB\operations\common\Handling;

use applications\iam\user\database\Vertex as User;
use applications\sso\oauth\database\Vertex as Oauth;

class Token extends Edge
{
	const COLLECTION = 'Token';
	const TARGET = 'applications\\iam\\user\\database';
	const DIRECTION = Edge::OUTBOUND;

	const REFRESHIN = 900;
	const ONLYEMAIL = 0x1;

	const AUTHORIZATION = 'Authorization';

	const GRANT_REFRESH = 'refresh_token';
	const GRANT_AUTHORIZATION = 'authorization_code';
	const GRANT_TYPE = 'grant_type';

	protected function initialize() : void
	{
		$oauth_key = $this->addField('oauth_key');
		$oauth_key_validation = Validation::factory('ShowString');
		$oauth_key->setPatterns($oauth_key_validation);
		$oauth_key->setProtected(true);
		$oauth_key->setRequired();

		$token_type = $this->addField('token_type');
		$token_type_validation = Validation::factory('ShowString');
		$token_type->setPatterns($token_type_validation);
		$token_type->setRequired();

		$access_token = $this->addField('access_token');
		$access_token_validation = Validation::factory('ShowString');
		$access_token->setPatterns($access_token_validation);
		$access_token->setRequired();

		$refresh_token = $this->addField('refresh_token');
		$refresh_token_validation = Validation::factory('ShowString');
		$refresh_token->setPatterns($refresh_token_validation);
		$refresh_token->setRequired();

		$expiry = $this->addField(Cookie::EXPIRY);
		$expiry_validation = Validation::factory('Number', 0);
		$expiry->setPatterns($expiry_validation);
		$expiry->setRequired();

		$token_expires_in = $this->addField('expires_in');
		$token_expires_in_validation = Validation::factory('Number', 0);
		$token_expires_in_validation->setClosureMagic(function (Field $field) use ($expiry) {

			$field_value = $field->getValue();
			$field_value = time() + $field_value;
			$field->setDefault();

			$expiry->setValue($field_value, Field::OVERRIDE);
			return true;
		});
		$token_expires_in->setPatterns($token_expires_in_validation);
	}

	public function getCurl() : Curl
	{
		$curl = new Curl();
		$curl->setHeader(static::AUTHORIZATION . chr(58) . chr(32) . $this->getAuthorization());
		return $curl;
	}

	public static function get() : array
	{
		$user = clone User::getWhoami();
		$user_query = ArangoDB::start($user);
		$user->useEdge(static::getName())->vertex();
		$user_query_select = $user_query->select();
		$user_query_select->getLimit()->set(1);
		$user_query_select->setMaxHop(1);
		$user_query_select_return = 'RETURN' . chr(32) . $user_query_select->getPointer(Choose::EDGE);
		$user_query_select->getReturn()->setPlain($user_query_select_return);
		$user_query_select_response = $user_query_select->run();
		if (null === $user_query_select_response) return array();

		$instance = new Token();
		$instance->getField('oauth_key')->setProtected(false);

		array_walk($user_query_select_response, function (array &$token) use ($instance) {
			$clone = clone $instance;
			$clone->setFromAssociative($token);
			$token = $clone;
		});

		return $user_query_select_response;
	}

	public static function postpone() : void
	{
		$user = clone User::getWhoami();
		$tokens = static::get();
		foreach ($tokens as $instance) {
			$instance_expiry = $instance->getField(Cookie::EXPIRY)->getValue();
			$instance_expiry = $instance_expiry - time();
			if ($instance_expiry < static::REFRESHIN)
				$instance->update($user);
		}
	}

	public function update(User $user) : self
	{
		$oauth_key = $this->getField('oauth_key');
		$oauth_key->setProtected(false);

		if (!!$errors = $this->checkRequired()->getAllFieldsWarning()) {
			Language::dictionary(__file__);
			$notice = __namespace__ . '\\' . 'notice';
			$notice = Language::translate($notice);
			Output::concatenate('notice', $notice);
			Output::concatenate('errors', $errors);
			Output::print(false);
		}

		$refresh_token = $this->getField('refresh_token');

		$oauth = Oauth::get($oauth_key->getValue());
		$oauth_parameters = $oauth->getParameters();
		$oauth_parameters->{static::GRANT_TYPE} = static::GRANT_REFRESH;
		$oauth_parameters->{$refresh_token->getName()} = $refresh_token->getValue();

		$token_value = new Curl();
		$token_value = $token_value->request($oauth->getURI(Oauth::TOKEN), (array)$oauth_parameters);
		$token = new static();
		$token->setFromAssociative((array)$token_value);
		$token->getField($oauth_key->getName())->setProtected(false)->setValue($oauth_key->getValue());
		$token->insert($user);

		return $this;
	}

	public function insert(User $user) :? self
	{
		$user->unsetAdapter();
		$user_query = ArangoDB::start($user);
		$user->useEdge(static::getName(), $this->unsetAdapter())->vertex($user);
		$user_query_insert = $user_query->insert();
		$user_query_insert->setActionOnlyEdges(true);
		$user_query_insert->setActionPreventLoop(false);
		$user_query_insert_response = $user_query_insert->run();
		if (null === $user_query_insert_response) return null;
		return $this;
	}

	public function getWhoami(Oauth $oauth, int $flags = 0) : User
	{
		$curl = $this->getCurl();
		$curl_remote = $oauth->getField('remote_whoami')->getValue();
		$curl_remote = (array)$curl->request($curl_remote);

		$user = new User();
		$user_email = $user->getField('email')->getName();
		$user_relations = $oauth->getField('fields_relation')->getValue();
		foreach ($user_relations as $map) {
			$local = $map->getField('local')->getValue();
			if ((bool)($flags & static::ONLYEMAIL) && $local !== $user_email) continue;
			if (array_key_exists($remote = $map->getField('remote')->getValue(), $curl_remote)) 
				$user->getField($local)->setValue($curl_remote[$remote]);
		}

		return $user;
	}

	private function getAuthorization() : string
	{
		if (!!$errors = $this->checkRequired()->getAllFieldsWarning()) {
			Language::dictionary(__file__);
			$notice = __namespace__ . '\\' . 'notice';
			$notice = Language::translate($notice);
			Output::concatenate('notice', $notice);
			Output::concatenate('errors', $errors);
			Output::print(false);
		}

		return $this->getField('token_type')->getValue() . chr(32) . $this->getField('access_token')->getValue();
	}
}
