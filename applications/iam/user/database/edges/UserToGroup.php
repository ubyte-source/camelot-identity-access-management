<?PHP

namespace applications\iam\user\database\edges;

use Entity\Validation;

use ArangoDB\entity\Edge;

class UserToGroup extends Edge
{
	const TARGET = 'applications\\iam\\group\\database';
	const COLLECTION = 'UserToGroup';
	const DIRECTION = Edge::OUTBOUND;
	
	protected function initialize() : void
	{
		$admin = $this->addField('admin');
		$admin_validator = Validation::factory('ShowBool');
		$admin->setPatterns($admin_validator);
	}
}
