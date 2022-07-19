<?PHP

namespace applications\sso\application\forms;

use Entity\Validation;

use ArangoDB\entity\Vertex;
use ArangoDB\entity\common\Arango;

use applications\iam\user\forms\User;
use applications\sso\cluster\forms\Cluster;
use applications\sso\application\forms\Application;

class Matrioska extends Application
{
	protected $cluster; // Field
	protected $share;   // Field

	protected function initialize() : void
	{
		$owner = $this->getField(Vertex::OWNER);
		$owner_validation = new User();
		$owner_validation = Validation::factory('Matrioska', $owner_validation);
		$owner->setPatterns($owner_validation);

		$cluster = $this->addField('cluster');
		$cluster_validation = new Cluster();
		$cluster_validation_fields = $cluster_validation->getFields();
		foreach ($cluster_validation_fields as $field) $field->setProtected(true);

		$this->cluster = $cluster_validation->getField(Arango::KEY)->setProtected(false)->setRequired(true);

		$cluster_validation = Validation::factory('Matrioska', $cluster_validation);
		$cluster->setPatterns($cluster_validation);
		$cluster->setRequired();

		parent::initialize();

		$share = $this->addField('share');
		$share_validation = new User();
		$share_validation_fields = $share_validation->getFields();
		foreach ($share_validation_fields as $field) $field->setProtected(true);

		$this->share = $share_validation->getField(Arango::KEY)->setProtected(false)->setRequired(true);

		$share_validation = Validation::factory('Matrioska', $share_validation);
		$share_validation->setMultiple();
		$share->setPatterns($share_validation);
	}
}
