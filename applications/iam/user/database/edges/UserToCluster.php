<?PHP

namespace applications\iam\user\database\edges;

use ArangoDB\entity\Edge;

use applications\sso\cluster\database\edges\ClusterToUser;

class UserToCluster extends ClusterToUser
{
	const TARGET = 'applications\\sso\\cluster\\database';
	const DIRECTION = Edge::INBOUND;
}
