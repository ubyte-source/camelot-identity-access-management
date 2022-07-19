<?PHP

namespace applications\iam\group\database\edges;

use ArangoDB\entity\Edge;

use applications\iam\user\database\edges\UserToGroup;

class GroupToUser extends UserToGroup
{
	const TARGET = 'applications\\iam\\user\\database';
	const DIRECTION = Edge::INBOUND;
}
