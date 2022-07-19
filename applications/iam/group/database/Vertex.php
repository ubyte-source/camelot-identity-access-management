<?PHP

namespace applications\iam\group\database;

use Entity\Validation;

use ArangoDB\entity\Vertex as EntityVertex;

class Vertex extends EntityVertex
{
	const COLLECTION = 'Group';

	protected function initialize() : void
	{
		$name = $this->addField('name');
		$name_validator = Validation::factory('ShowString');
		$name_validator->setMax(32);
		$name->setPatterns($name_validator);
		$name->setRequired();

		$description = $this->addField('description');
		$description_validator = Validation::factory('Textarea');
		$description_validator->setMax(1024);
		$description->setPatterns($description_validator);
	}
}
