<?PHP

namespace applications\sso\server\forms;

use Entity\Validation;

use ArangoDB\entity\Vertex;
use ArangoDB\entity\common\Arango;

use applications\iam\user\forms\User;
use applications\sso\application\forms\Application;
use applications\sso\server\forms\Server;

class Matrioska extends Server
{
	protected $key; // Field

	protected function initialize() : void
	{
		$owner = $this->getField(Vertex::OWNER);
		$owner_validation = new User();
		$owner_validation = Validation::factory('Matrioska', $owner_validation);
		$owner->setPatterns($owner_validation);

		$application = $this->addField('application');
		$application_validation = new Application();
		$application_validation_fields = $application_validation->getFields();
		foreach ($application_validation_fields as $field) $field->setProtected(true);

		$this->key = $application_validation->getField(Arango::KEY)->setProtected(false)->setRequired(true);

		$application_validation = Validation::factory('Matrioska', $application_validation);
		$application_validation->setMultiple(true);
		$application->setPatterns($application_validation);
		$application->setRequired(true);

		parent::initialize();
	}
}
