<?PHP

namespace applications\iam\policy\database\edges;

use ArangoDB\entity\Edge;

class PolicyToApplication extends Edge
{
	const TARGET = 'applications\\sso\\application\\database';
	const COLLECTION = 'PolicyToApplication';
	const DIRECTION = Edge::OUTBOUND;
}
