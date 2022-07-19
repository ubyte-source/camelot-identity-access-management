<?PHP

namespace applications\iam\policy\database\edges;

use ArangoDB\entity\Edge;

use applications\iam\user\database\edges\UserToPolicy;

class PolicyToUser extends UserToPolicy
{
	const TARGET = 'applications\\iam\\user\\database';
	const DIRECTION = Edge::INBOUND;
}
