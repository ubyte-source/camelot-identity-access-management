<?PHP

namespace applications\iam\user\database\edges;

use IAM\Sso;
use IAM\Request as IAMRequest;

use Knight\armor\Cookie;
use Knight\armor\Request;

use Entity\Validation;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\Statement;
use ArangoDB\entity\Edge;
use ArangoDB\entity\common\Arango;
use ArangoDB\operations\common\Choose;
use ArangoDB\operations\common\Handling;

use applications\iam\user\database\Vertex as User;
use applications\iam\user\database\edges\Token;

class Session extends Edge
{
	const COLLECTION = 'Session';
	const TARGET = 'applications\\iam\\user\\database';
	const DIRECTION = Edge::OUTBOUND;

	const REFRESHIN = 900;

	const COOKIE_NAME = 'alive';
	const COOKIE_EXPIRED_TIME = 14400;
	const COOKIE_PASSPHRASE_LENGTH = 16;
	const RANDOM_CHARACTERS = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

	const ENABLECRYPT = true;

	protected static $authorization; // (string)

	protected function initialize() : void
	{
		$passphrase = $this->addField('passphrase');
		$passphrase_validation = Validation::factory('ShowString');
		$passphrase->setPatterns($passphrase_validation);
		$passphrase->setRequired();

		$expiry = $this->addField(Cookie::EXPIRY);
		$expiry_validation = Validation::factory('DateTime', null, 'U');
		$expiry->setPatterns($expiry_validation);
		$expiry->setRequired();
	}

	public function check() : void
	{
		$expiry = $this->getField(Cookie::EXPIRY)->getValue();
		$expiry = $expiry - time();
		if (static::REFRESHIN > $expiry) {
			$user = User::getWhoami();
			if (null !== $user) static::sendCookie(static::generate(clone $user));
		}
	}

	public static function generate(User $user, int $expiry = null) :? int
	{
		$expiry = $expiry ?? static::COOKIE_EXPIRED_TIME;
		$expiry = time() + $expiry;

		$user->unsetAdapter();
		$session_query = ArangoDB::start($user);
		$session_check = $session_query->select();
		$session_check->setMaxHop(1);
		$session_check->getLimit()->set(1);
		$session_check_return = 'RETURN 1';
		$session_check->getReturn()->setPlain($session_check_return);
		$session_check_statement = $session_check->getStatement();
		$session_check_statement->setExpect(1)->setHideResponse(true);
		$session = $user->useEdge(static::COLLECTION);
		$session_field_passphrase_value = static::getRandomString(static::COOKIE_PASSPHRASE_LENGTH);
		$session->getField('passphrase')->setValue($session_field_passphrase_value);
		$session->getField(Cookie::EXPIRY)->setValue($expiry);
		$session->vertex($user);

		if (!!$session->checkRequired(true)->getAllFieldsWarning()) return null;

		$insert = $session_query->insert();
		$insert->pushStatementsPreliminary($session_check_statement);
		$insert->setActionOnlyEdges(true);
		$insert->setActionPreventLoop(false);
		$insert_return = 'RETURN' . chr(32) . Handling::RNEW;
		$insert->getReturn()->setPlain($insert_return);
		$insert->setEntityEnableReturns($session);
		$insert_response = $insert->run();
		$insert_response = reset($insert_response);
		if (false === $insert_response) User::exception();

		$user_response_key = $insert_response[EDGE::FROM];
		$user_response_key = substr($user_response_key, 1 + strlen($user->getCollectionName()));

		static::setAuthorization($session_field_passphrase_value . $user_response_key, static::ENABLECRYPT);

		return $expiry;
	}

	public static function verify() :? self
	{
		$authenticator = static::getAuthorization();
		$authenticator = User::getCipher()->decryptJWT($authenticator);
		if (null === $authenticator) return null;

		$user = new User();
		$user_query = ArangoDB::start($user);
		$user_field_key_value = substr($authenticator, static::COOKIE_PASSPHRASE_LENGTH);
		$user->getField(Arango::KEY)->setProtected(false)->setValue($user_field_key_value);

		$session = $user->useEdge(static::COLLECTION);
		$session_field_passphrase_value = substr($authenticator, 0, static::COOKIE_PASSPHRASE_LENGTH);
		$session->getField('passphrase')->setProtected(false)->setValue($session_field_passphrase_value);

		$user_query_select = $user_query->select();
		$user_query_select->getLimit()->set(1);
		$user_query_select->setMaxHop(1);
		$user_query_select_return = 'RETURN' . chr(32) . $user_query_select->getPointer(Choose::EDGE);
		$user_query_select->getReturn()->setPlain($user_query_select_return);
		$user_query_select_response = $user_query_select->run();
		$user_query_select_response = reset($user_query_select_response);
		if (false === $user_query_select_response) return null;
	
		$session = new static();
		$session->setFromAssociative($user_query_select_response);

		return $session;
	}

	public static function getRandomString(int $string_length = 4) {
		$string = '';
		for ($i = 0, $length = strlen(static::RANDOM_CHARACTERS) - 1; $i < $string_length; $i++) {
			$character_random_position = rand(0, $length);
			$string .= static::RANDOM_CHARACTERS[$character_random_position];
		}
		return $string;
	}

	public static function getAuthorization() :? string
	{
		return static::$authorization;
	}

	public static function setAuthorization(?string $authorization, bool $crypt = false) : void
	{
		static::$authorization = $authorization;
		if ($crypt !== static::ENABLECRYPT
			|| $authorization === null) return;

		static::$authorization = User::getCipher()->encryptJWT($authorization);
	}

	public static function getAuthorizationUser() :? int
	{
		$user_key = User::getCipher()->decryptJWT(static::getAuthorization());
		if (null !== $user_key) return substr($user_key, static::COOKIE_PASSPHRASE_LENGTH);
		return null;
	}

	public static function sendCookie(?int $expires) : void
	{
		if (null !== $expires) {
			$session_authorization = static::getAuthorization();
			if (null !== $session_authorization)
				Cookie::set(static::COOKIE_NAME, $session_authorization, $expires);
		}
	}

	public static function setAuthorizationFromRequest() :? self
	{
		$header_authorization = Request::header(IAMRequest::HEADER_AUTHOTIZATION);
		if (null === $header_authorization
			&& !array_key_exists(static::COOKIE_NAME, $_COOKIE)) return null;

		$authorization = $header_authorization ?? $_COOKIE[static::COOKIE_NAME];
		$authorization = preg_replace('/^(' . Sso::AUTHORIZATION_TYPE . ')(\s)?/', '', $authorization);
		if (rawurldecode(trim($authorization)) !== trim($authorization)) $authorization = rawurldecode($authorization);
		static::setAuthorization($authorization);

		return static::verify();
	}
}
