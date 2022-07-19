<?PHP

namespace applications\iam\user\database\edges;

use Entity\Validation;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\operations\common\Choose;
use ArangoDB\entity\Edge;

use applications\iam\user\database\Vertex as User;

use extensions\widgets\infinite\Setting;

class UserToSetting extends Edge
{
	const TARGET = 'applications\\sso\\application\\database';
	const DIRECTION = Edge::OUTBOUND;
	const COLLECTION = 'UserToSetting';
	const IDENTIFIER = [
		'module',
		'view',
		'widget'
	];

	protected function initialize() : void
	{
		foreach (static::IDENTIFIER as $name) {
			$field = $this->addField($name);
			$field_validator = Validation::factory('ShowString');
			$field->setPatterns($field_validator);
			$field->addUniqueness(Edge::TYPE);
			$field->setRequired(true);
		}

		$value = $this->addField('value');
		$value_validator = new Setting();
		$value_validator = Validation::factory('Matrioska', $value_validator);
		$value_validator->setMultiple();
		$value->setPatterns($value_validator);
		$value->setRequired();
	}

	public static function getSettings(User $user, string ...$filters) : self
	{
		$user_query = ArangoDB::start($user);
		$edge = $user->useEdge(static::COLLECTION);
		
		if (!!$filters) {
			$application = $edge->vertex();
			$application->getField('basename')->setValue(array_shift($filters));
			
			$edge_fields = $edge->getFields();
			foreach ($edge_fields as $field) {
				if (!in_array($field->getName(), static::IDENTIFIER)) continue;
				$field->setProtected(false)->setValue(array_shift($filters));
			}
		}

		$user_query_select = $user_query->select();
		$user_query_select->getLimit()->set(1);
		$user_query_select_return = 'RETURN' . chr(32) . $user_query_select->getPointer(Choose::EDGE);
		$user_query_select->getReturn()->setPlain($user_query_select_return);

		$user_query_select_response = $user_query_select->run();
		if (null === $user_query_select_response
			|| empty($user_query_select_response)) return new static();
		
		$current = new static();
		$current->setReadMode(true);
		$current_value = reset($user_query_select_response);
		$current->setFromAssociative($current_value);

		return $current;
	}
}
