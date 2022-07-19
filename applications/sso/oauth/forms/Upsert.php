<?PHP

namespace applications\sso\oauth\forms;

use Entity\Field;
use Entity\Validation;

use ArangoDB\entity\common\Arango;

use applications\sso\oauth\database\Vertex;

use extensions\Navigator;

class Upsert extends Vertex
{
    const CALLBACK = 'api/sso/oauth/signin';

    protected function after() : void
	{
		$redirect_uri = $this->addField('redirect_uri');
		$redirect_uri_validator = Validation::factory('ShowString');
		$redirect_uri->setPatterns($redirect_uri_validator);
		$redirect_uri->setRequired(false);

		$key = $this->getField(Arango::KEY);
        $key_pattern = Validation::factory('Regex');
        $key_pattern->setRegex('/^\w+$/');
		$key_pattern->setClosureMagic(function (Field $field) use ($redirect_uri) {
			$field_readmode = $field->getReadMode();
			if (false === $field_readmode) return true;

			$field_value = $field->getValue();
			$field_value = trim($field_value);

            $redirect_uri_value = Navigator::getUrl();
            $redirect_uri_value = trim($redirect_uri_value, chr(47));
            $redirect_uri_value = $redirect_uri_value . chr(47) . static::CALLBACK . chr(47) . $field_value;
			$redirect_uri->setValue($redirect_uri_value, Field::OVERRIDE);

			return true;
		});
		$key->setPatterns($key_pattern);
	}
}
