<?PHP

namespace applications\sso\server\database\edges;

use ArangoDB\entity\Edge;

class ServerToUser extends Edge
{
	const TARGET = 'applications\\iam\\user\\database';
	const COLLECTION = 'ServerToUser';
	const DIRECTION = Edge::OUTBOUND;
}
