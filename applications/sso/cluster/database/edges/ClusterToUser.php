<?PHP

namespace applications\sso\cluster\database\edges;

use ArangoDB\entity\Edge;

class ClusterToUser extends Edge
{
	const TARGET = 'applications\\iam\\user\\database';
	const COLLECTION = 'ClusterToUser';
	const DIRECTION = Edge::OUTBOUND;
}
