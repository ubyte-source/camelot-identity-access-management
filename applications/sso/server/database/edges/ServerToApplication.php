<?PHP

namespace applications\sso\server\database\edges;

use ArangoDB\entity\Edge;

class ServerToApplication extends Edge
{
	const TARGET = 'applications\\sso\\application\\database';
	const COLLECTION = 'ServerToApplication';
	const DIRECTION = Edge::OUTBOUND;
}
