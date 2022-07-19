<?PHP

namespace applications\sso\application\database\edges;

use ArangoDB\entity\Edge;

class ApplicationToUser extends Edge
{
	const TARGET = 'applications\\iam\\user\\database';
	const COLLECTION = 'ApplicationToUser';
	const DIRECTION = Edge::OUTBOUND;
}
