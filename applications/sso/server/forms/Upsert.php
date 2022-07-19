<?PHP

namespace applications\sso\server\forms;

use Entity\Validation;

use ArangoDB\entity\common\Arango;

use applications\sso\server\forms\Matrioska;

class Upsert extends Matrioska
{
	const APPLICATION = '#name#';
	const APPLICATION_GRAB = '/api/sso/application/read';
	const APPLICATION_GRAB_IDENTITY = Arango::KEY;
    const APPLICATION_GRAB_RESPONSE = 'data';
    const APPLICATION_GRAB_RESPONSE_FIELDS = [
		'name',
		'description'
	];

	protected function initialize() : void
	{
		parent::initialize();

		$application_validator = Validation::factory('Enum');
		$application_validator_search = $application_validator->getSearch();
		$application_validator_search->setUnique(static::APPLICATION_GRAB_IDENTITY);
        $application_validator_search->setURL(static::APPLICATION_GRAB);
        $application_validator_search->setResponse(static::APPLICATION_GRAB_RESPONSE);
		$application_validator_search->setLabel(static::APPLICATION);
		$application_validator_search->pushFields(...static::APPLICATION_GRAB_RESPONSE_FIELDS);

		$this->key->setPatterns($application_validator);
		$this->key->setRequired();
	}
}
