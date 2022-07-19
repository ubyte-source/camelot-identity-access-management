<?PHP

namespace applications\iam\group\database\edges;

use ArangoDB\entity\Edge;

use applications\iam\user\database\edges\UserToPolicy;

class GroupToPolicy extends UserToPolicy
{
	const DIRECTION = Edge::OUTBOUND;
	const COLLECTION = 'GroupToPolicy';
}
