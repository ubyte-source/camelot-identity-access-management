<?PHP

namespace applications\sso\application\database\edges;

use ArangoDB\entity\Edge;

use applications\iam\user\database\edges\UserToSetting;

class SettingToUser extends UserToSetting
{
	const TARGET = 'applications\\iam\\user\\database';
	const DIRECTION = Edge::INBOUND;
}
