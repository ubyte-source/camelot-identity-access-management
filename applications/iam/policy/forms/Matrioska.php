<?PHP

namespace applications\iam\policy\forms;

use Entity\Validation;

use ArangoDB\entity\Vertex;
use ArangoDB\entity\common\Arango;

use applications\iam\user\forms\User;
use applications\iam\policy\forms\Policy;
use applications\sso\application\forms\Application;

class Matrioska extends Policy
{
	protected $application;  // Field

	protected function initialize() : void
	{
		$owner = $this->getField(Vertex::OWNER);
		$owner_validation = new User();
		$owner_validation = Validation::factory('Matrioska', $owner_validation);
		$owner->setPatterns($owner_validation);

		parent::initialize();

		$application = $this->addField('application');
		$application_validation = new Application();
		$application_validation_fields = $application_validation->getFields();
		foreach ($application_validation_fields as $field) $field->setProtected(true);

		$this->application = $application_validation->getField(Arango::KEY)->setProtected(false)->setRequired(true);

		$application_validation = Validation::factory('Matrioska', $application_validation);
		$application->setPatterns($application_validation);
		$application->setRequired();
	}
}
