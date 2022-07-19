<?PHP

namespace applications\iam\user\map\assignment;

use Entity\Map as Entity;
use Entity\Field;
use Entity\Validation;

use ArangoDB\entity\common\Arango;

use applications\iam\group\database\Vertex;

class Group extends Entity
{
    const COLLECTION = Vertex::COLLECTION;
    const RESPONSE_FIELDS_NAME = [
        Arango::ID,
        Arango::KEY,
        'name',
        'description'
    ];

    function initialize()
    {
        $vertex = new Vertex();
        $vertex_fields = $vertex->getFields();
        $vertex_fields = array_filter($vertex_fields, function (Field $field) {
            return in_array($field->getName(), static::RESPONSE_FIELDS_NAME);
        });
        foreach ($vertex_fields as $field) $this->addFieldClone($field)->setProtected(false)->setRequired(false);

        $manager = $this->addField('manager');
		$manager_validator = Validation::factory('ShowBool');
        $manager->setPatterns($manager_validator);

        $paths = $this->addField('paths');
		$paths_validator = Validation::factory('ShowArray', array());
		$paths->setPatterns($paths_validator);
    }
}
