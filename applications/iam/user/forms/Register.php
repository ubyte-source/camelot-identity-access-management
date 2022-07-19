<?PHP

namespace applications\iam\user\forms;

use Entity\Field;
use Entity\Validation;

use ArangoDB\entity\Vertex;
use applications\iam\user\database\Vertex as User;

class Register extends Vertex
{
    const ENABLED = [
		'email',
		'firstname',
        'lastname',
		'language'
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

        $device_passphrase_value = $this->addField('device_passphrase_value');
		$device_passphrase_value_validation = Validation::factory('ShowString');
		$device_passphrase_value->setPatterns($device_passphrase_value_validation);
		$device_passphrase_value->setRequired(true);		

        $device_serial = $this->addField('device_serial');
		$device_serial_validation = Validation::factory('Number', 0);
		$device_serial->setPatterns($device_serial_validation);
		$device_serial->setRequired(true);
	}   
}