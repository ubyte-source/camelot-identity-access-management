<?PHP
namespace applications\iam\user\database\edges;

use ArangoDB\entity\Edge;

class UserToUser extends Edge
{
	const TARGET = 'applications\\iam\\user\\database';
	const COLLECTION = 'UserToUser';
	const DIRECTION = Edge::OUTBOUND;
}
