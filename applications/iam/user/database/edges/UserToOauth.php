<?PHP

namespace applications\iam\user\database\edges;

use ArangoDB\entity\Edge;

use applications\sso\oauth\database\edges\OauthToUser;

class UserToOauth extends OauthToUser
{
	const TARGET = 'applications\\sso\\oauth\\database';
	const DIRECTION = Edge::INBOUND;
}
