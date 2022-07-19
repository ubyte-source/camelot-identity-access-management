<?PHP

namespace applications\sso\server\database;

use Entity\Validation;

use ArangoDB\entity\Vertex as EntityVertex;

class Vertex extends EntityVertex
{
	const COLLECTION = 'Server';

	protected function initialize() : void
	{
		$name = $this->addField('name');
		$name_pattern = Validation::factory('ShowString');
		$name_pattern->setMax(32);
		$name->setPatterns($name_pattern);
		$name->setRequired();

		$cidr = $this->addField('cidr');
		$cidr_validator = Validation::factory('Chip');
		$cidr_validator->setRegex('/^([0-9]{1,3}\.){3}[0-9]{1,3}(\/([0-9]|[1-2][0-9]|3[0-2]))?$/');
		$cidr->setPatterns($cidr_validator);
		$cidr->setRequired();
	}
}
