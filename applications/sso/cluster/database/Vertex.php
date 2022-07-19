<?PHP

namespace applications\sso\cluster\database;

use Knight\armor\Language;

use Entity\Validation;

use extensions\Multilanguage;

use ArangoDB\entity\Vertex as EntityVertex;

class Vertex extends EntityVertex
{
	const COLLECTION = 'Cluster';

	protected function initialize() : void
	{
		$name = $this->addField('name');
		$name_pattern = Validation::factory('ShowString');
		$name_pattern->setMax(64);
		$name->setPatterns($name_pattern);
		$name->setRequired();

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
