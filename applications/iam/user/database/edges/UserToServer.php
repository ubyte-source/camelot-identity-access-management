<?PHP

namespace applications\iam\user\database\edges;

use ArangoDB\entity\Edge;

use applications\sso\server\database\edges\ServerToUser;

class UserToServer extends ServerToUser
{
	const TARGET = 'applications\\sso\\server\\database';
	const DIRECTION = Edge::INBOUND;
}
