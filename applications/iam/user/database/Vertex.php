<?PHP

namespace applications\iam\user\database;

use IAM\Sso;
use IAM\Request as IAMRequest;

use Knight\armor\Output;
use Knight\armor\Cookie;
use Knight\armor\Request;
use Knight\armor\Language;
use Knight\armor\CustomException;

use Entity\Field;
use Entity\Validation;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\Statement;
use ArangoDB\operations\common\Choose;
use ArangoDB\entity\Edge;
use ArangoDB\entity\Vertex as EntityVertex;
use ArangoDB\entity\common\Arango;

use extensions\Cipher;
use extensions\Navigator;
use extensions\widgets\menu\Base as Menu;

use applications\iam\user\database\edges\Session;
use applications\iam\user\forms\Login;
use applications\iam\user\database\edges\UserToSetting;
use applications\iam\user\database\edges\UserToPolicy;
use applications\iam\user\database\edges\Token;
use applications\iam\policy\database\Vertex as Policy;
use applications\sso\application\database\Vertex as Application;
use applications\sso\application\database\edges\ApplicationToCluster;
use applications\sso\cluster\database\Vertex as Cluster;

class Vertex extends EntityVertex
{
	const ROOT = 'root@energia-europa.com';
	const COLLECTION = 'User';
	const SERVICE = 'service';
	const HUMAN = 'human';
	const OAUTH = 'oauth';
	const CONTACT = 'contact';
	
	const INFO = 'Info';

	const SSO_LOGIN_PAGE = 'iam/user/login';

	protected static $whoami; // Vertex

	public static function getCipher() : Cipher
	{
		$cipher = new Cipher();
		$cipher_personal = Navigator::getFingerprint();
		$cipher->setKeyPersonal($cipher_personal);
		return $cipher;
	}

	protected function initialize() : void
	{
		$phone_number_prefix_validator = Validation::factory('Enum');
		$phone_number_prefix_validator->addAssociative('0039', array('icon' => 'flag-icon flag-icon-it'));
		$phone_number_prefix_validator->addAssociative('0032', array('icon' => 'flag-icon flag-icon-be'));
		$phone_number_prefix_validator->addAssociative('0031', array('icon' => 'flag-icon flag-icon-nl'));
		$phone_number_prefix_validator->addAssociative('0034', array('icon' => 'flag-icon flag-icon-es'));
		$phone_number_prefix_validator->addAssociative('00358', array('icon' => 'flag-icon flag-icon-fi'));
		$phone_number_prefix_validator->addAssociative('00386', array('icon' => 'flag-icon flag-icon-si'));
		$phone_number_prefix_validator->addAssociative('0041', array('icon' => 'flag-icon flag-icon-ch'));
		$phone_number_prefix_validator->addAssociative('0043', array('icon' => 'flag-icon flag-icon-at'));
		$phone_number_prefix_validator->addAssociative('0046', array('icon' => 'flag-icon flag-icon-se'));
		$phone_number_prefix_validator->addAssociative('0048', array('icon' => 'flag-icon flag-icon-pl'));
		$phone_number_prefix_validator->addAssociative('00972', array('icon' => 'flag-icon flag-icon-il'));
		$phone_number_prefix_validator->addAssociative('0090', array('icon' => 'flag-icon flag-icon-tr'));
		$phone_number_prefix_validator->addAssociative('0033', array('icon' => 'flag-icon flag-icon-fr'));
		$phone_number_prefix_validator->addAssociative('00372', array('icon' => 'flag-icon flag-icon-ee'));
		$phone_number_prefix_validator->addAssociative('0040', array('icon' => 'flag-icon flag-icon-ro'));
		$phone_number_prefix_validator->addAssociative('00421', array('icon' => 'flag-icon flag-icon-sk'));
		$phone_number_prefix_validator->addAssociative('00371', array('icon' => 'flag-icon flag-icon-lv'));
		$phone_number_prefix_validator->addAssociative('0091', array('icon' => 'flag-icon flag-icon-in'));
		$phone_number_prefix_validator->addAssociative('00964', array('icon' => 'flag-icon flag-icon-ir'));
		$phone_number_prefix_validator->addAssociative('00962', array('icon' => 'flag-icon flag-icon-jo'));
		$phone_number_prefix_validator->addAssociative('00351', array('icon' => 'flag-icon flag-icon-pt'));
		$phone_number_prefix_validator->addAssociative('00370', array('icon' => 'flag-icon flag-icon-lt'));
		$phone_number_prefix_validator->addAssociative('00994', array('icon' => 'flag-icon flag-icon-az'));
		$phone_number_prefix_validator->addAssociative('001', array('icon' => 'flag-icon flag-icon-us'));
		$phone_number_prefix_validator->addAssociative('00423', array('icon' => 'flag-icon flag-icon-li'));
		$phone_number_prefix_validator->addAssociative('0081', array('icon' => 'flag-icon flag-icon-jp'));
		$phone_number_prefix_validator->addAssociative('00350', array('icon' => 'flag-icon flag-icon-gi'));
		$phone_number_prefix_validator->addAssociative('0063', array('icon' => 'flag-icon flag-icon-ph'));
		$phone_number_prefix_validator->addAssociative('0086', array('icon' => 'flag-icon flag-icon-cn'));
		$phone_number_prefix_validator->addAssociative('00213', array('icon' => 'flag-icon flag-icon-dz'));
		$phone_number_prefix_validator->addAssociative('007', array('icon' => 'flag-icon flag-icon-kz'));
		$phone_number_prefix_validator->addAssociative('0062', array('icon' => 'flag-icon flag-icon-id'));
		$phone_number_prefix_validator->addAssociative('00441624', array('icon' => 'flag-icon flag-icon-im'));
		$phone_number_prefix_validator->addAssociative('0065', array('icon' => 'flag-icon flag-icon-sg'));
		$phone_number_prefix_validator->addAssociative('00965', array('icon' => 'flag-icon flag-icon-kw'));
		$phone_number_prefix_validator->addAssociative('00385', array('icon' => 'flag-icon flag-icon-hr'));
		$phone_number_prefix_validator->addAssociative('0044', array('icon' => 'flag-icon flag-icon-gb'));
		$phone_number_prefix_validator->addAssociative('00212', array('icon' => 'flag-icon flag-icon-ma'));
		$phone_number_prefix_validator->addAssociative('00298', array('icon' => 'flag-icon flag-icon-fo'));
		$phone_number_prefix_validator->addAssociative('00420', array('icon' => 'flag-icon flag-icon-cz'));
		$phone_number_prefix_validator->addAssociative('00353', array('icon' => 'flag-icon flag-icon-ie'));
		$phone_number_prefix_validator->addAssociative('00974', array('icon' => 'flag-icon flag-icon-qa'));
		$phone_number_prefix_validator->addAssociative('0060', array('icon' => 'flag-icon flag-icon-my'));
		$phone_number_prefix_validator->addAssociative('00971', array('icon' => 'flag-icon flag-icon-ae'));
		$phone_number_prefix_validator->addAssociative('00374', array('icon' => 'flag-icon flag-icon-am'));
		$phone_number_prefix_validator->addAssociative('00381', array('icon' => 'flag-icon flag-icon-rs'));
		$phone_number_prefix_validator->addAssociative('00378', array('icon' => 'flag-icon flag-icon-sm'));
		$phone_number_prefix_validator->addAssociative('0047', array('icon' => 'flag-icon flag-icon-no'));
		$phone_number_prefix_validator->addAssociative('00389', array('icon' => 'flag-icon flag-icon-mk'));
		$phone_number_prefix_validator->addAssociative('00852', array('icon' => 'flag-icon flag-icon-hk'));
		$phone_number_prefix_validator->addAssociative('0094', array('icon' => 'flag-icon flag-icon-lk'));
		$phone_number_prefix_validator->addAssociative('0084', array('icon' => 'flag-icon flag-icon-vn'));
		$phone_number_prefix_validator->addAssociative('0055', array('icon' => 'flag-icon flag-icon-br'));
		$phone_number_prefix_validator->addAssociative('003906', array('icon' => 'flag-icon flag-icon-va'));
		$phone_number_prefix_validator->addAssociative('00375', array('icon' => 'flag-icon flag-icon-by'));
		$phone_number_prefix_validator->addAssociative('001876', array('icon' => 'flag-icon flag-icon-jm'));
		$phone_number_prefix_validator->addAssociative('0054', array('icon' => 'flag-icon flag-icon-ar'));
		$phone_number_prefix_validator->addAssociative('00995', array('icon' => 'flag-icon flag-icon-ge'));
		$phone_number_prefix_validator->addAssociative('00376', array('icon' => 'flag-icon flag-icon-ad'));
		$phone_number_prefix_validator->addAssociative('00595', array('icon' => 'flag-icon flag-icon-py'));
		$phone_number_prefix_validator->addAssociative('001787', array('icon' => 'flag-icon flag-icon-pr'));
		$phone_number_prefix_validator->addAssociative('001939', array('icon' => 'flag-icon flag-icon-pr'));
		$phone_number_prefix_validator->addAssociative('0082', array('icon' => 'flag-icon flag-icon-kr'));
		$phone_number_prefix_validator->addAssociative('0052', array('icon' => 'flag-icon flag-icon-mx'));
		$phone_number_prefix_validator->addAssociative('0056', array('icon' => 'flag-icon flag-icon-cl'));
		$phone_number_prefix_validator->addAssociative('00373', array('icon' => 'flag-icon flag-icon-md'));
		$phone_number_prefix_validator->addAssociative('0061', array('icon' => 'flag-icon flag-icon-au'));
		$phone_number_prefix_validator->addAssociative('00356', array('icon' => 'flag-icon flag-icon-mt'));
		$phone_number_prefix_validator->addAssociative('0049', array('icon' => 'flag-icon flag-icon-de'));
		$phone_number_prefix_validator->addAssociative('0066', array('icon' => 'flag-icon flag-icon-th'));
		$phone_number_prefix_validator->addAssociative('00357', array('icon' => 'flag-icon flag-icon-cy'));
		$phone_number_prefix_validator->addAssociative('0027', array('icon' => 'flag-icon flag-icon-za'));
		$phone_number_prefix_validator->addAssociative('0095', array('icon' => 'flag-icon flag-icon-mm'));
		$phone_number_prefix_validator->addAssociative('00966', array('icon' => 'flag-icon flag-icon-sa'));
		$phone_number_prefix_validator->addAssociative('00387', array('icon' => 'flag-icon flag-icon-ba'));
		$phone_number_prefix_validator->addAssociative('00977', array('icon' => 'flag-icon flag-icon-np'));
		$phone_number_prefix_validator->addAssociative('00380', array('icon' => 'flag-icon flag-icon-ua'));
		$phone_number_prefix_validator->addAssociative('00268', array('icon' => 'flag-icon flag-icon-sz'));
		$phone_number_prefix_validator->addAssociative('0045', array('icon' => 'flag-icon flag-icon-dk'));
		$phone_number_prefix_validator->addAssociative('00352', array('icon' => 'flag-icon flag-icon-lu'));
		$phone_number_prefix_validator->addAssociative('00355', array('icon' => 'flag-icon flag-icon-al'));
		$phone_number_prefix_validator->addAssociative('00886', array('icon' => 'flag-icon flag-icon-tw'));
		$phone_number_prefix_validator->addAssociative('00359', array('icon' => 'flag-icon flag-icon-bg'));
		$phone_number_prefix_validator->addAssociative('00377', array('icon' => 'flag-icon flag-icon-mc'));
		$phone_number_prefix_validator->addAssociative('00507', array('icon' => 'flag-icon flag-icon-pa'));
		$phone_number_prefix_validator->addAssociative('00216', array('icon' => 'flag-icon flag-icon-tn'));
		$phone_number_prefix_validator->addAssociative('001', array('icon' => 'flag-icon flag-icon-ca'));
		$phone_number_prefix_validator->addAssociative('0020', array('icon' => 'flag-icon flag-icon-eg'));
		$phone_number_prefix_validator->addAssociative('0064', array('icon' => 'flag-icon flag-icon-nz'));
		$phone_number_prefix_validator->addAssociative('00968', array('icon' => 'flag-icon flag-icon-om'));
		$phone_number_prefix_validator->addAssociative('00218', array('icon' => 'flag-icon flag-icon-ly'));
		$phone_number_prefix_validator->addAssociative('00354', array('icon' => 'flag-icon flag-icon-is'));
		$phone_number_prefix_validator->addAssociative('0030', array('icon' => 'flag-icon flag-icon-gr'));
		$phone_number_prefix_validator->addAssociative('0036', array('icon' => 'flag-icon flag-icon-hu'));
		$phone_number_prefix_validator->addAssociative('007', array('icon' => 'flag-icon flag-icon-ru'));
		$phone_number_prefix_validator->addAssociative('00382', array('icon' => 'flag-icon flag-icon-me'));
		$phone_number_prefix_validator_keys = $phone_number_prefix_validator->getKeys();
		$phone_number_prefix_validator_keys = '/^(?!(' . implode('|', $phone_number_prefix_validator_keys) . '))\w+$/i';

		$key = $this->getField(Arango::KEY);
		$key_validator = Validation::factory('Regex');
		$key_validator->setRegex('/\d+/');
		$key->setPatterns($key_validator);

		$image = $this->addField('picture');
		$image_validator = Validation::factory('Image');
		$image_validator->setMaxSize(1024 * 8); //8KB
		$image_validator->setMimeValid('image/jpeg', 'image/png', 'image/gif');
		$image_validator->setResize(120, 120);
		$image_validator->setClosureMagic(function (Field $field) use ($image_validator) {
			$field_readmode = $field->getReadMode();
			$field_safemode = $field->getSafeMode();
			if (true === $field_readmode || $field_safemode === false) return true;

			$field_value = $field->getValue();
			$field_value_content = file_get_contents($field_value);
			$field_value_content = base64_encode($field_value_content);
			$field_value_content_mime = $image_validator::getMime($field_value);
			$field_value_content = 'data:' . $field_value_content_mime . ';base64,' . $field_value_content;
			$field->setValue($field_value_content, Field::OVERRIDE);
			return true;
		});
		$image->setPatterns($image_validator);

		$password = $this->addField('password');
		$password_validation = Validation::factory('Password');
		$password_validation->setClosureMagic(function (Field $field) {
			$field_readmode = $field->getReadMode();
			$field_safemode = $field->getSafeMode();
			if (true === $field_readmode || $field_safemode === false) return true;

			$field_value = $field->getValue();
			$field_value = md5($field_value);
			$field->setValue($field_value, Field::OVERRIDE);
			return true;
		});
		$password->setPatterns($password_validation);
		$password->setProtected();

		$firstname = $this->addField('firstname');
		$firstname_validation = Validation::factory('ShowString');
		$firstname_validation->setMax(32);
		$firstname->setPatterns($firstname_validation);
		$firstname->setRequired();

		$user = $firstname->getRow()->setName('user');
		$user = clone $user;
		$user->setPriority(2);

		$lastname = $this->addField('lastname');
		$lastname_validator = Validation::factory('ShowString');
		$lastname_validator->setMax(32);
		$lastname->setPatterns($lastname_validator);
		$lastname->setRequired();
		$lastname->setRow($user);

		$role = $this->addField('role');
		$role_validation = Validation::factory('ShowString');
		$role_validation->setMax(64);
		$role->setPatterns($role_validation);

		$department = $this->addField('department');
		$department_validation = Validation::factory('ShowString');
		$department_validation->setMax(32);
		$department->setPatterns($department_validation);

		$email = $this->addField('email');
		$email_validator = Validation::factory('Email');
		$email_validator->setMin(6)->setMax(128);
		$email->setPatterns($email_validator);
		$email->addUniqueness('email');
		$email->setRequired();

		
		$phone_number_prefix = $this->addField('phone_number_prefix');
		$phone_number_prefix->setPatterns($phone_number_prefix_validator);
		$phone_number_prefix->getRow()->setName('mobile');
		
		$phone_number = $this->addField('phone_number');
		$phone_number_pattern = Validation::factory('ShowString');
		$phone_number_pattern->setMin(1);
		$phone_number_pattern->setClosureMagic(function (Field $field) use ($phone_number_prefix) {
			$field_readmode = $field->getReadMode();
			$field_safemode = $field->getSafeMode();
			if (true === $field_readmode || $field_safemode === false) return true;

			$field_value = $field->getValue();
			if (is_string($field_value)
				&& strlen($field_value)) $phone_number_prefix->setRequired();

			return true;
		});
		$phone_number->setPatterns($phone_number_pattern);
		$phone_number->getRow()->setName('mobile');
		$phone_number->addUniqueness(static::INFO);
		
		$phone_number_office_prefix = $this->addField('phone_number_office_prefix');
		$phone_number_office_prefix->setPatterns($phone_number_prefix_validator);
		$phone_number_office_prefix->getRow()->setName('internal');
				
		$phone_number_office = $this->addField('phone_number_office');
		$phone_number_office_pattern = Validation::factory('ShowString');
		$phone_number_office_pattern->setMin(1);
		$phone_number_office_pattern->setClosureMagic(function (Field $field) use ($phone_number_office_prefix) {
			$field_readmode = $field->getReadMode();
			$field_safemode = $field->getSafeMode();
			if (true === $field_readmode || $field_safemode === false) return true;

			$field_value = $field->getValue();
			if (is_string($field_value)
				&& strlen($field_value)) $phone_number_office_prefix->setRequired();

			return true;
		});
		$phone_number_office->setPatterns($phone_number_office_pattern);
		$phone_number_office->getRow()->setName('internal');
		$phone_number_office->addUniqueness(static::INFO);
		
		$phone_number_office_internal = $this->addField('phone_number_office_internal');
		$phone_number_office_internal_pattern = Validation::factory('ShowString');
		$phone_number_office_internal_pattern->setMin(1);
		$phone_number_office_internal->setPatterns($phone_number_office_internal_pattern);
		$phone_number_office_internal->getRow()->setName('internal');
		$phone_number_office_internal->addUniqueness(static::INFO);

		$type = $this->addField('type');
		$type_validator = Validation::factory('Enum');
		$type_validator->addAssociative(static::SERVICE, array('icon' => 'material-icons manage_accounts'));
		$type_validator->addAssociative(static::HUMAN, array('icon' => 'material-icons account_circle'));
		$type_validator->addAssociative(static::OAUTH, array('icon' => 'material-icons apps_outage'));
		$type_validator->addAssociative(static::CONTACT, array('icon' => 'material-icons contact_mail'));
		$type->setPatterns($type_validator);
		$type->setRequired();

		$language = $this->addField('language');
		$language_validator = Validation::factory('Enum');
		$language_validator->addAssociative('it', array('icon' => 'flag-icon flag-icon-it'));
		$language_validator->addAssociative('en', array('icon' => 'flag-icon flag-icon-gb'));
		$language_validator->addAssociative('de', array('icon' => 'flag-icon flag-icon-de'));
		$language_validator->addAssociative('fr', array('icon' => 'flag-icon flag-icon-fr'));
		$language_validator->addAssociative('es', array('icon' => 'flag-icon flag-icon-es'));
		$language->setPatterns($language_validator);
		$language->setRequired();
	}

	public static function login(self $user = null) : self
	{
		if (null !== static::getWhoami()) return clone static::getWhoami();
		if (null !== $user) {
			$user_fields = $user->getFields();
			foreach ($user_fields as $field) $field->setRequired(false);

			$user->getField('password')->setRequired(true);
			$user->getField('email')->setRequired(true);
			if (empty($user->checkRequired()->getAllFieldsWarning())) {
				$expired = Session::generate($user);
				if (null === $expired) static::exception();

				Session::sendCookie($expired);
				return clone static::setWhoami(Session::getAuthorizationUser());
			}
		}

		static::checkFromAuthorization();
		if (null === static::getWhoami()) static::exception();
		return clone static::getWhoami();
	}

	public static function checkFromAuthorization() : bool
	{
		$session = Session::setAuthorizationFromRequest();
		if (null === $session) return false;

		static::setWhoami(Session::getAuthorizationUser());

		$session->check();
		Token::postpone();

		return true;
	}

	public static function logout() : void
	{
		static::login();

		$user = clone static::getWhoami();
		$user_query = ArangoDB::start($user);
		$user_query_remove = $user_query->remove();
		$user_query_remove->setActionOnlyEdges(true);
		$user_query_remove->setActionPreventLoop(false);

		$session_authorization = static::getCipher()->decryptJWT(Session::getAuthorization());
		$session_authorization = substr($session_authorization, 0, Session::COOKIE_PASSPHRASE_LENGTH);

		$user->useEdge(Session::getName())->getField('passphrase')->setValue($session_authorization);
		$user_query_remove->run();

		Cookie::set(Session::COOKIE_NAME, 'null', time() - 36e2);
	}

	public static function getWhoami(bool $data = false) :? self
	{
		if (false === $data
			|| null === static::$whoami) return static::$whoami;

		static $user;
		if ($user instanceof Vertex) return $user;

		$user = new static();
		$user->setSafeMode(false)->setReadMode(true);
		$user_password = $user->getField('password')->getName();

		$query = ArangoDB::start(static::$whoami);
		$query_select = $query->select();
		$query_select->getLimit()->set(1);
		$query_select_return = 'RETURN UNSET' . chr(40) . chr(32) . $query_select->getPointer(Choose::VERTEX) . chr(44) . chr(32) . chr(34) . $user_password . chr(34) . chr(41);
		$query_select->getReturn()->setPlain($query_select_return);
		$query_select_response = $query_select->run();
		$query_select_response = reset($query_select_response);
		if (false === $query_select_response) return null;

		$user->setFromAssociative($query_select_response, $query_select_response);
		Language::setSpeech($user->getField('language')->getValue());

		return $user;
	}

	public static function getHumanSettings(string ...$filters) : array
	{
		$login = static::login();
		$settings = new UserToSetting();
		$settings_name = $settings->getField('value')->getName();
		$settings = $settings->getSettings($login, ...$filters);
		$settings = $settings->getAllFieldsValues(false, false);
		return $settings[$settings_name];
	}

	public static function getMenu(string ...$routes) : array
	{
		$filters = [];
		if (!!$navigator = reset($routes)) array_push($filters, $navigator . Policy::SEPARATOR . chr(37));

		$policies = UserToPolicy::getPolicies(...$filters);
		$policies_route = array_column($policies, 'route');
		$policies_route = preg_grep('/view|environment/', $policies_route);
		$policies_route = array_filter($policies_route);

		$items_response = [];

		if (empty($policies_route)) return $items_response;
		if (!!$filters) {
			$basename = reset($routes);
			foreach ($policies_route as $policy_route) {
				$views_exploded = explode(Policy::SEPARATOR, $policy_route, Navigator::getDepth() + 1);
				$views_exploded_depth = Navigator::getDepth() - 1;
				if (!array_key_exists($views_exploded_depth, $views_exploded)
					|| $views_exploded[$views_exploded_depth] !== 'view') continue;
				unset($views_exploded[$views_exploded_depth]);

				$views_exploded_path = implode(DIRECTORY_SEPARATOR, $views_exploded);
				$views_exploded_path = APPLICATIONS . $views_exploded_path;
				$views_exploded_path = dirname($views_exploded_path);

				$named = basename($views_exploded_path);
				if (!array_key_exists($named, $items_response)) {
					$item = Menu::getItemFromPath($views_exploded_path);
					if (null === $item) continue;

					$items_response[$named] = $item;
					$items_response_module_field_name = $items_response[$named]->getField('name');
					$items_response_module_field_name_value = $items_response_module_field_name->getValue();
					$items_response_module_field_name_value = chr(47) . $basename . chr(47) . $items_response_module_field_name_value;
					$items_response_module_field_name->setValue($items_response_module_field_name_value);
				}

				$policy_view = array_pop($views_exploded);
				$items_response[$named]->pushPolicies($policy_view);
			}
		} else {
			$policies_cache = array_column($policies, Policy::CACHE);
			$policies_cache = array_unique($policies_cache);
			$policies_cache = array_filter($policies_cache);
			foreach ($policies_cache as $basename) {
				$application = new Application();
				$application->getField('basename')->setProtected(false)->setValue($basename);
				array_push($filters, $application);
			}

			$application_query = ArangoDB::start(...$filters);
			$application->useEdge(ApplicationToCluster::getName());
			$application_query_select = $application_query->select();
			$application_query_select_vertex = $application_query_select->getPointer(Choose::VERTEX);

			$application_query_select_return = new Statement();
			$application_query_select_return->append('LET a = DOCUMENT' . chr(40) . $application_query_select->getPointer(Choose::EDGE) . chr(46) . Edge::FROM . chr(41));
			$application_query_select_return->append('RETURN {[a.basename]: {cluster: ' . $application_query_select->getPointer(Choose::VERTEX) . chr(44) . chr(32) . 'application: a}}');
			$application_query_select->getReturn()->setFromStatement($application_query_select_return);

			$response = $application_query_select->run();
			if (null === $response) Output::print(false);

			$response = call_user_func_array('array_merge', $response);
			array_walk($policies, function (object &$item) use ($response) {
				$item = (object)array_merge((array)$item, (array)$response[$item->_cache]);
			});

			foreach ($policies as $object) {
				if (false === in_array($object->route, $policies_route)) continue;

				$cluster = new Cluster();
				$cluster->setReadMode(true);
				$cluster->setFromAssociative($object->cluster, $object->cluster);
				if (!!$cluster->getAllFieldsWarning()
					|| !is_string($object->route)
					|| !strlen($object->route)) continue;

				$application = new Application();
				$application->setReadMode(true);
				$application->getField('basename')->setProtected(false);
				$application->setFromAssociative($object->application, $object->application);
				if (!!$application->getAllFieldsWarning()) continue;

				$basename = $application->getField('basename')->getValue();
				if (Application::checkLocalExists($basename)) {
					$views_exploded = explode(Policy::SEPARATOR, $object->route);
					$views_exploded = array_slice($views_exploded, 0, Navigator::getDepth() + 1);
					$views_exploded_depth = Navigator::getDepth() - 1;

					if (!array_key_exists($views_exploded_depth, $views_exploded) || $views_exploded[$views_exploded_depth] !== 'view') continue;
					if (!array_key_exists($basename, $items_response)) $items_response[$basename] = Menu::createFromDatabase($cluster, $application);

					$items_response_available = $items_response[$basename]->getItem($views_exploded[1]);
					if (null === $items_response_available) continue;

					$named = array_pop($views_exploded);
					$items_response_available->pushPolicies($named);
				} else {
					$link = $application->getField('link');
					if ($link->isDefault()
						|| array_key_exists($basename, $items_response)) continue;

					$items_response[$basename] = Menu::createFromDatabase($cluster, $application);

					$link_value = $link->getValue();
					$link_value_parsed = parse_url($link_value, PHP_URL_SCHEME);
					if (null === $link_value_parsed) {
						$link_value = trim($link_value, chr(47));
						$link_value = 'http://' . $link_value;
					}

					$link_value = rtrim($link_value, chr(47));
					$authorization = Session::getAuthorization();
					if (null !== $authorization) {
						$environment = basename($object->route);
						if (Application::ENVIRONMENT !== $environment) { 
							$authorization = base64_encode($authorization);
							$link_value .= chr(47) . Sso::AUTHORIZATION . chr(47) . $authorization;
						}
						$items_response[$basename]->getField('href')->setValue($link_value);
					}
				}
			}
		}

		$items_response = array_map(function (Menu $menu) {
			$href = $menu->getHref();
			if (null === $href) return null;
			$menu->getField('href')->setValue($href);
			return $menu->output();
		}, $items_response);
		$items_response = array_filter($items_response);
		$items_response = array_values($items_response);

		return $items_response;
	}

	public static function exception() : void
	{
		$sso = Navigator::getUrl() . static::SSO_LOGIN_PAGE;

		Navigator::exception(function (string $current) use ($sso) {
			$redirect = base64_encode($current);
			$redirect = urlencode($redirect);
			$redirect = Navigator::RETURN_URL . chr(61) . $redirect;
			$redirect = $sso . chr(63) . $redirect;

			if (!in_array('Content-type: application/json', headers_list())) return $redirect;

			$redirect = str_replace(chr(47), Language::SHASH_ESCAPE, $redirect);

			Language::dictionary(__file__);
			$notice = __namespace__ . '\\' . 'navigator' . '\\' . 'guest';
			$notice = Language::translate($notice, $redirect);
			$errors = new Login();
			$errors = $errors->checkRequired()->getAllFieldsWarning();
			Output::concatenate('notice', $notice);
			Output::concatenate('errors', $errors);
			Output::print(false);
		});
	}

	protected static function setWhoami(int $session_form_user_key) : self
	{
		static::$whoami = new static();
		static::$whoami->setReadMode(true);
		$fields = static::$whoami->getFields();
		foreach ($fields as $field) $field->setProtected(true); 

		static::$whoami->getField(Arango::KEY)->setProtected(false)->setValue($session_form_user_key);

		return static::$whoami;
	}
}
