<?PHP

namespace applications\iam\user\database\edges;

use Knight\armor\Output;
use Knight\armor\Language;

use Entity\Validation;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\Statement;
use ArangoDB\operations\common\Choose;
use ArangoDB\entity\Edge;
use ArangoDB\entity\common\Arango;

use Redis\Cache;

use applications\iam\user\database\Vertex as User;
use applications\iam\user\database\edges\UserToGroup;
use applications\iam\user\database\edges\UserToApplication;
use applications\iam\user\database\edges\Session;
use applications\iam\group\database\edges\GroupToGroup;
use applications\iam\group\database\edges\GroupToPolicy;
use applications\iam\user\map\Policy as Map;
use applications\iam\policy\database\Vertex as Policy;
use applications\sso\application\database\edges\ApplicationToPolicy;

class UserToPolicy extends Edge
{
	const TARGET = 'applications\\iam\\policy\\database';
	const COLLECTION = 'UserToPolicy';
	const DIRECTION = Edge::OUTBOUND;

	const MATCH = '/^(%s)$/';
	
	protected function initialize() : void
	{	
		$allow = $this->addField('allow');
		$allow_validator = Validation::factory('ShowBool');
		$allow->setPatterns($allow_validator);
		$allow->setRequired();
		
		$reassignment = $this->addField('reassignment');
		$reassignment_validator = Validation::factory('ShowBool');
		$reassignment->setPatterns($reassignment_validator);
		$reassignment->setRequired();
	}

	public static function getPolicies(string ...$filters) : array
	{
		static $cache;

		if (null === $cache) {
			$user = User::login();
			$authorization = Session::getAuthorization();
			if (null === $authorization) return User::exception();

			$named = User::getWhoami()->getField(Arango::KEY)->getValue();
			$cache = Cache::get($authorization, $named, function () use ($user) {
				$user_query = ArangoDB::start($user);
				$user->useEdge(UserToApplication::getName())->vertex()->useEdge(ApplicationToPolicy::getName());

				$edge = $user->useEdge(static::getName());
				$edge_field = $edge->getFields();
				foreach ($edge_field as $field) $field->setRequired(false); 

				$edge_field_allow = $edge->getField('allow');
				$edge_field_allow->setProtected(false)->setRequired(true);
				$edge_field_allow->setValue(true);
				$edge->checkRequired();

				if (!!$errors = $edge->getAllFieldsWarning()) {
					Language::dictionary(__file__);
					$notice = __namespace__ . '\\' . 'notice';
					$notice = Language::translate($notice);
					Output::concatenate('notice', $notice);
					Output::concatenate('errors', $errors);
					Output::print(false);
				}

				$group = $user->useEdge(UserToGroup::getName())->vertex();
				$group->useEdge(GroupToGroup::getName())->setForceDirection(Edge::INBOUND)->vertex()->useEdge(GroupToPolicy::getName(), $edge);
				$group->useEdge(GroupToPolicy::getName(), $edge);

				$select = $user_query->shortestPath();
				$select_vertex = $select->getPointer(Choose::VERTEX);
				$select_vertex_route = $edge->vertex()->getField('route')->getName();

				$select_return = new Statement();
				$select_return->append('LET r = CONCAT' . chr(40) . $select_vertex .
					chr(46) . $edge->vertex()->getField(Policy::CACHE)->getName() .
					chr(44) . chr(32) . chr(34) . Policy::SEPARATOR . chr(34) . chr(44) .
					chr(32) . $select_vertex . chr(46) . $select_vertex_route .
					chr(41));
				$select_return_route = chr(123) . $select_vertex_route . chr(58) . chr(32) . 'r' . chr(125);
				$select_return->append('RETURN MERGE' . chr(40) . $select->getPointer(Choose::EDGE) . chr(44) . chr(32) . $select_vertex . chr(44) . chr(32) . $select_return_route . chr(41));
				$select->getReturn()->setFromStatement($select_return);

				$select_response = $select->run();
				if (null === $select_response) return array();

				$cache = [];

				foreach ($select_response as $policy) {
					$map = new Map();
					$map_fields = $map->getFields();
					foreach ($map_fields as $field) {
						$field_pattern = $field->getPatterns();
						$field_pattern = reset($field_pattern);
						$field_pattern_type = $field_pattern->getType();
						if ($field_pattern_type !== ':bool') continue;
						$field->setValue(true);
					}
					$map->getField(Policy::CACHE)->setProtected(false);
					$map->getField(Arango::KEY)->setProtected(false);
					$map->setFromAssociative($policy);
					$map = $map->getStructure();
					array_push($cache, $map);
				}
				return $cache;
			});
		}

		if (!$filters = array_diff($filters, array('%'))) return $cache;

		$find = array('%', '/');
		$replace = array('.*', '\/');
		$filkt = array();
		$regex = array_map(function ($item) use ($find, $replace) {
			return str_replace($find, $replace, $item);
		}, $filters);
		$regex = implode('|', $regex);
		$regex = sprintf(static::MATCH, $regex);
		foreach ($cache as $map)
			if (preg_match($regex, $map->route))
				array_push($filkt, $map);
		return $filkt;
	}
}
