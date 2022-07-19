<?PHP

namespace applications\iam\policy\forms;

use Entity\Validation;

use ArangoDB\entity\Vertex;

class Delete extends Vertex
{
	protected function initialize() : void
	{	
		$number = $this->addField('number');
		$number_pattern = Validation::factory('Number');
		$number->setPatterns($number_pattern);
	}
}
