<?PHP

namespace applications\iam\group\forms;

use Entity\Validation;

use applications\iam\user\forms\User;
use applications\iam\group\forms\Group;

use ArangoDB\entity\Vertex;
use ArangoDB\entity\common\Arango;

class Matrioska extends Group
{
	protected $users;  // Field
	protected $group;  // Field
	protected $share;  // Field

	protected function initialize() : void
	{
		$owner = $this->getField(Vertex::OWNER);
		$owner_validation = new User();
		$owner_validation = Validation::factory('Matrioska', $owner_validation);
		$owner->setPatterns($owner_validation);

		parent::initialize();

		$users = $this->addField('users');
		$users_validation = new User();
		$users_validation_fields = $users_validation->getFields();
		foreach ($users_validation_fields as $field) $field->setProtected(true);

		$this->users = $users_validation->getField(Arango::KEY)->setProtected(false)->setRequired(true);

		$users_validation = Validation::factory('Matrioska', $users_validation);
		$users_validation->setMultiple();
		$users->setPatterns($users_validation);

		$group = $this->addField('group');
		$group_validation = new Group();
		$group_validation_fields = $group_validation->getFields();
		foreach ($group_validation_fields as $field) $field->setProtected(true);

		$this->group = $group_validation->getField(Arango::KEY)->setProtected(false)->setRequired(true);

		$group_validation = Validation::factory('Matrioska', $group_validation);
		$group_validation->setMultiple();
		$group->setPatterns($group_validation);

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
