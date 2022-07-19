<?PHP

namespace applications\sso\application\database\edges;

use ArangoDB\entity\Edge;

class ApplicationToCluster extends Edge
{
	const TARGET = 'applications\\sso\\cluster\\database';
	const COLLECTION = 'ApplicationToCluster';
	const DIRECTION = Edge::OUTBOUND;
}
