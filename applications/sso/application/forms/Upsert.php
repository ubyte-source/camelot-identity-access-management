<?PHP

namespace applications\sso\application\forms;

use Entity\Validation;

use ArangoDB\entity\common\Arango;

use applications\sso\application\forms\Matrioska;

class Upsert extends Matrioska
{
	const CLUSTER = '#name#';
	const CLUSTER_GRAB = '/api/sso/cluster/read';
	const CLUSTER_GRAB_IDENTITY = Arango::KEY;
    const CLUSTER_GRAB_RESPONSE = 'data';
    const CLUSTER_GRAB_RESPONSE_FIELDS = [
		'name',
		'description'
	];

	const USER = '#firstname# #lastname# <#email#>';
	const USER_GRAB = '/api/iam/user/read';
	const USER_GRAB_IDENTITY = Arango::KEY;
    const USER_GRAB_RESPONSE = 'data';
    const USER_GRAB_RESPONSE_FIELDS = [
		'firstname',
		'lastname',
		'email'
	];

	protected function after() : void
	{
		$cluster_validator = Validation::factory('Enum');
		$cluster_validator_search = $cluster_validator->getSearch();
		$cluster_validator_search->setUnique(static::CLUSTER_GRAB_IDENTITY);
        $cluster_validator_search->setURL(static::CLUSTER_GRAB);
        $cluster_validator_search->setResponse(static::CLUSTER_GRAB_RESPONSE);
		$cluster_validator_search->setLabel(static::CLUSTER);
		$cluster_validator_search->pushFields(...static::CLUSTER_GRAB_RESPONSE_FIELDS);

		$this->cluster->setPatterns($cluster_validator);
		$this->cluster->setRequired();

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
