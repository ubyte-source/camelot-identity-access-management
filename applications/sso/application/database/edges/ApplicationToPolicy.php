<?PHP

namespace applications\sso\application\database\edges;

use ArangoDB\entity\Edge;

use applications\iam\policy\database\edges\PolicyToApplication;

class ApplicationToPolicy extends PolicyToApplication
{
	const TARGET = 'applications\\iam\\policy\\database';
	const DIRECTION = Edge::INBOUND;
}
