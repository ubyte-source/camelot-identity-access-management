<?PHP

namespace applications\iam\policy\database;

use Knight\armor\Output;
use Knight\armor\Language;

use Entity\Validation;

use ArangoDB\entity\Vertex as EntityVertex;

use applications\iam\user\database\Vertex as User;
use applications\iam\user\database\edges\UserToPolicy;

class Vertex extends EntityVertex
{
	const SEPARATOR = '/';
	const COLLECTION = 'Policy';
	const MATCH = '/^(%s)$/';
	const CACHE = '_cache';

	protected static $overload; // (array)

	public static function setOverload(string ...$policies) : void
	{
		static::$overload = $policies;
	}

	public static function getOverload() :? array
	{
		return static::$overload;
	}

	public static function mandatories(string ...$policies) : void
	{
		if (static::check(...$policies)) return;
		Language::dictionary(__file__);
		$required = __namespace__ . '\\' . 'required';
		$required = Language::translate($required);
		Output::concatenate('notice', $required);
		Output::concatenate(Output::APIDATA, $policies);
		Output::print(false);
	}

	public static function check(string ...$filters) : bool
	{
		$policies = UserToPolicy::getPolicies(...$filters);
		$policies = array_column($policies, 'route');
		if (!!$overload = static::getOverload()) $policies = array_merge($policies, $overload);

		$find = array('%', '/');
		$replace = array('(.*)', '\/');
		$policies_filters_regex = array_map(function ($item) use ($find, $replace) {
			return str_replace($find, $replace, $item);
		}, $filters);
		$result = count($policies_filters_regex);
		foreach ($policies_filters_regex as $filter) {
			$regex_match = sprintf(static::MATCH, $filter);
			foreach ($policies as $rule) {
				if (!preg_match($regex_match, $rule)) continue;
				$result--;
				continue 2;
			}
		}
		return 0 === $result;
	}

	protected function initialize() : void
	{	
		$name = $this->addField('name');
		$name_validator = Validation::factory('ShowString');
		$name_validator->setMax(64);
		$name->setPatterns($name_validator);
		$name->setRequired();

		$route = $this->addField('route');
		$route_validator = Validation::factory('ShowString');
		$route_validator->setMax(128);
		$route->setPatterns($route_validator);
		$route->setRequired();

		$description = $this->addField('description');
		$description_validator = Validation::factory('Textarea');
		$description_validator->setMax(1024);
		$description->setPatterns($description_validator);

		$cache = $this->addField(static::CACHE);
		$cache_validator = Validation::factory('ShowString');
		$cache->setPatterns($cache_validator);
		$cache->setProtected();
	}
}
