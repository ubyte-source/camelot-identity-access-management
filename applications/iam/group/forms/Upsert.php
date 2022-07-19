<?PHP

namespace applications\iam\group\forms;

use Entity\Validation;

use applications\iam\group\forms\Matrioska;

use ArangoDB\entity\common\Arango;

class Upsert extends Matrioska
{
	const USER = '#firstname# #lastname# <#email#>';
	const USER_GRAB = '/api/iam/user/read';
	const USER_GRAB_IDENTITY = Arango::KEY;
    const USER_GRAB_RESPONSE = 'data';
    const USER_GRAB_RESPONSE_FIELDS = [
		'firstname',
		'lastname',
		'email'
	];

	const GROUP = '#name#';
	const GROUP_GRAB = '/api/iam/group/read';
	const GROUP_GRAB_IDENTITY = Arango::KEY;
    const GROUP_GRAB_RESPONSE = 'data';
    const GROUP_GRAB_RESPONSE_FIELDS = [
		'name'
	];

	protected function initialize() : void
	{
		parent::initialize();

		$users_validator = Validation::factory('Chip');
		$users_validator_search = $users_validator->getSearch();
		$users_validator_search->setUnique(static::USER_GRAB_IDENTITY);
		$users_validator_search->setURL(static::USER_GRAB);
		$users_validator_search->setResponse(static::USER_GRAB_RESPONSE);
		$users_validator_search->setLabel(static::USER);
		$users_validator_search->pushFields(...static::USER_GRAB_RESPONSE_FIELDS);

		$this->users->setPatterns($users_validator);

		$users = $this->getField('users');
		$users_patterns = $users->getPatterns();
		array_walk($users_patterns, function (Validation $validation) {
			$validation->setMultiple(false);
		});

		$group_validator = Validation::factory('Chip');
		$group_validator_search = $group_validator->getSearch();
		$group_validator_search->setUnique(static::GROUP_GRAB_IDENTITY);
		$group_validator_search->setURL(static::GROUP_GRAB);
		$group_validator_search->setResponse(static::GROUP_GRAB_RESPONSE);
		$group_validator_search->setLabel(static::GROUP);
		$group_validator_search->pushFields(...static::GROUP_GRAB_RESPONSE_FIELDS);

		$this->group->setPatterns($group_validator);

		$group = $this->getField('group');
		$group_patterns = $group->getPatterns();
		array_walk($group_patterns, function (Validation $validation) {
			$validation->setMultiple(false);
		});

		$share_validator = Validation::factory('Chip');
		$share_validator_search = $share_validator->getSearch();
		$share_validator_search->setUnique(static::USER_GRAB_IDENTITY);
		$share_validator_search->setURL(static::USER_GRAB);
		$share_validator_search->setResponse(static::USER_GRAB_RESPONSE);
		$share_validator_search->setLabel(static::USER);
		$share_validator_search->pushFields(...static::USER_GRAB_RESPONSE_FIELDS);

		$this->share->setPatterns($share_validator);

		$share = $this->getField('share');
		$share_patterns = $share->getPatterns();
		array_walk($share_patterns, function (Validation $validation) {
			$validation->setMultiple(false);
		});
	}
}
