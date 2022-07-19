<?PHP

namespace applications\iam\policy\database\edges;

use ArangoDB\entity\Edge;

use applications\iam\group\database\edges\GroupToPolicy;

class PolicyToGroup extends GroupToPolicy
{
	const TARGET = 'applications\\iam\\group\\database';
	const DIRECTION = Edge::INBOUND;
}
