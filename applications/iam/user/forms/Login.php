<?PHP

namespace applications\iam\user\forms;

use ArangoDB\entity\Vertex;

use applications\iam\user\database\Vertex as User;

class Login extends Vertex
{
	const ENABLED = [
		'email',
		'password'
	];

	protected function initialize() : void
	{
		$user = new User();
		$user_fields = $user->getFields();
		foreach ($user_fields as $field) {
			$field_name = $field->getName();
			if (!in_array($field_name, static::ENABLED)) continue;

			$clone = $this->addFieldClone($field);
			$clone->setProtected(false)->setRequired(true);
		}
	}
}
