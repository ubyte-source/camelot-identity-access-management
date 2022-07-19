<?PHP

namespace applications\iam\user\map\assignment;

use Entity\Map as Entity;
use Entity\Field;
use Entity\Validation;

use ArangoDB\entity\common\Arango;

use applications\iam\policy\database\Vertex;

use applications\iam\policy\database\edges\PolicyToUser;

class Policy extends Entity
{
    const COLLECTION = Vertex::COLLECTION;

    const RESPONSE_FIELDS_NAME = [
        Arango::ID,
        Arango::KEY,
        'name',
        'route',
        'description',
        'allow',
        'reassignment'
    ];

    public function initialize()
    {
        $vertex = new Vertex();
        $vertex_fields = $vertex->getFields();
        $vertex_fields = array_filter($vertex_fields, function (Field $field) {
            return in_array($field->getName(), static::RESPONSE_FIELDS_NAME);
        });
        foreach ($vertex_fields as $field) $this->addFieldClone($field)->setProtected(false)->setRequired(false);
        $vertex_fields_keys_used = $this->getAllFieldsKeys();

        $edge = new PolicyToUser();
        $edge_fields = $edge->getFields();
        $edge_fields = array_filter($edge_fields, function (Field $field) {
            return in_array($field->getName(), static::RESPONSE_FIELDS_NAME);
        });
        foreach ($edge_fields as $field) {
            $field_name = $field->getName();
            if (in_array($field_name, $vertex_fields_keys_used)) continue;
            $this->addFieldClone($field)->setProtected(false)->setRequired(false);
        }

        $manager = $this->addField('manager');
		$manager_validator = Validation::factory('ShowBool');
		$manager->setPatterns($manager_validator);

        $path = $this->addField('path');
		$path_validator = Validation::factory('ShowArray');
        $path->setPatterns($path_validator);

        $application = $this->addField('application');
		$application_validator = Validation::factory('Enum');
        $application->setPatterns($application_validator);

        $group = $this->addField('group');
		$group_validator = Validation::factory('ShowString');
        $group->setPatterns($group_validator);
    }
}
