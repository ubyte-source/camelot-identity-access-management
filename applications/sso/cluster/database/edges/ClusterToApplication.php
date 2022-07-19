<?PHP

namespace applications\sso\cluster\database\edges;

use ArangoDB\entity\Edge;

use applications\sso\application\database\edges\ApplicationToCluster;

class ClusterToApplication extends ApplicationToCluster
{
	const TARGET = 'applications\\sso\\application\\database';
	const DIRECTION = Edge::INBOUND;
}
