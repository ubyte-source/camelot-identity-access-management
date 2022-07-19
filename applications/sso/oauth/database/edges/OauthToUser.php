<?PHP

namespace applications\sso\oauth\database\edges;

use ArangoDB\entity\Edge;

class OauthToUser extends Edge
{
	const TARGET = 'applications\\iam\\user\\database';
	const COLLECTION = 'OauthToUser';
	const DIRECTION = Edge::OUTBOUND;
}
