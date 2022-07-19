<?PHP

namespace applications\sso\oauth\database\vertex;

use Entity\Map as Entity;
use Entity\Field;
use Entity\Validation;

use ArangoDB\entity\Vertex;

use applications\iam\user\forms\User;

class Map extends Entity
{
	protected function initialize() : void
	{	
        $fields = new User();
		$fields->getField('picture')->setProtected(true);
		$fields_human = $fields->human();
		$fields_human = $fields_human->{Entity::FIELDS};

		$local = $this->addField('local');
		$local_validator = Validation::factory('Enum');
        foreach ($fields_human as $field) $local_validator->addAssociative($field->{Field::NAME}, (array)$field);
		$local->setPatterns($local_validator);
		$local->setRequired(true);

		$remote = $this->addField('remote');
		$remote_pattern = Validation::factory('ShowString');
		$remote->setPatterns($remote_pattern);
	}
}
