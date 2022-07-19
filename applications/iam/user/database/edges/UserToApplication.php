<?PHP

namespace applications\iam\user\database\edges;

use ArangoDB\entity\Edge;

use applications\sso\application\database\edges\ApplicationToUser;

class UserToApplication extends ApplicationToUser
{
	const TARGET = 'applications\\sso\\application\\database';
    const DIRECTION = Edge::INBOUND;
}
