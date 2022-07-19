<?PHP

namespace applications\iam\group\database\edges;

use ArangoDB\entity\Edge;

class GroupToGroup extends Edge
{
	const TARGET = 'applications\\iam\\group\\database';
	const COLLECTION = 'GroupToGroup';
	const DIRECTION = Edge::OUTBOUND;
}
