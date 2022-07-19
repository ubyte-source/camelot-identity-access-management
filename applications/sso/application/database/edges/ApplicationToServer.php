<?PHP

namespace applications\sso\application\database\edges;

use ArangoDB\entity\Edge;

use applications\sso\server\database\edges\ServerToApplication;

class ApplicationToServer extends ServerToApplication
{
	const TARGET = 'applications\\sso\\server\\database';
	const DIRECTION = Edge::INBOUND;
}
