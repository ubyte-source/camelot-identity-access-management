<?PHP

namespace applications\sso\application\database;

use Knight\armor\Language;

use Entity\Field;
use Entity\Validation;

use extensions\Multilanguage;

use ArangoDB\entity\Vertex as EntityVertex;

class Vertex extends EntityVertex
{
	const COLLECTION = 'Application';
	const ENVIRONMENT = 'environment';

	protected function initialize() : void
	{
		$name = $this->addField('name');
		$name_pattern = Validation::factory('ShowString');
		$name_pattern->setMax(64);
		$name->setPatterns($name_pattern);
		$name->setRequired();

		$icon = $this->addField('icon');
		$icon_validator = Validation::factory('Image');
		$icon_validator->setMaxSize(1024 * 24); // 24KB
		$icon_validator->setMimeTranslaction('image/svg', 'image/svg+xml');
		$icon_validator->setMimeValid('image/svg+xml');
		$icon_validator->setClosureMagic(function (Field $field) use ($icon_validator) {
			$field_readmode = $field->getReadMode();
			$field_safemode = $field->getSafeMode();
			if (true === $field_readmode || $field_safemode === false) return true;

			$field_value = $field->getValue();
			$field_value_content = file_get_contents($field_value);
			$field_value_content = base64_encode($field_value_content);
			$field_value_content_mime = $icon_validator::getMime($field_value);
			$field_value_content_mime_translaction = $icon_validator->getMimeTranslaction();
			if (array_key_exists($field_value_content_mime, $field_value_content_mime_translaction)) $field_value_content_mime = $field_value_content_mime_translaction[$field_value_content_mime];
			$field_value_content = 'data:' . $field_value_content_mime . ';base64,' . $field_value_content;
			$field->setValue($field_value_content, Field::OVERRIDE);
			return true;
		});
		$icon->setPatterns($icon_validator);

		$basename = $this->addField('basename');
		$basename_pattern = Validation::factory('Regex');
		$basename_pattern->setRegex('/^[a-z]+$/');
		$basename->setPatterns($basename_pattern);
		$basename->addUniqueness('unique');
		$basename->setRequired();

		$link = $this->addField('link');
		$link_pattern = Validation::factory('ShowString');
		$link_pattern->setMax(128);
		$link->setPatterns($link_pattern);
		$link->setRequired();

		$label = $this->addField(Multilanguage::LABEL);
		$label_validation = new Multilanguage();
		$label_validation = Validation::factory('Matrioska', $label_validation);
		$label_validation->setMultiple();
		$label->setPatterns($label_validation);
		$label->setRequired();

		$description = $this->addField('description');
		$description_validator = Validation::factory('Textarea');
		$description_validator->setMax(1024);
		$description->setPatterns($description_validator);
	}

	public static function checkLocalExists(?string $basename) : bool
    {
		if (null === $basename) return false;
        $application_path = APPLICATIONS . $basename;
		return file_exists($application_path)
			&& is_dir($application_path);
    }

	public function getLabel() : string
	{
		$label = $this->getField(Multilanguage::LABEL);
		$label_value = $this->getAllFieldsValues(false, false);
		$label_value = $label_value[$label->getName()];
		$label_value = array_column($label_value, Multilanguage::LABEL, Multilanguage::LANGUAGE);
		$exceptions = $this->getField('name')->getValue();
		if (false === is_array($label_value)) return $exceptions;

		$translated = Language::array($label_value);
		return null === $translated ? $exceptions : $translated;
	}
}
