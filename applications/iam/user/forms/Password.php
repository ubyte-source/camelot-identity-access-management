<?PHP

namespace applications\iam\user\forms;

use Entity\Validation;

use applications\iam\user\database\Vertex as User;

class Password extends User
{
	protected function initialize() : void
	{	
		$type = $this->addField('type');
		$type_validation = Validation::factory('Radio');
		$type_validation->addAssociative('auto');
		$type_validation->addAssociative('manual');
		$type->setPatterns($type_validation);
		$type->setRequired();

		$password = $this->addField('password');
		$password_validation = Validation::factory('Password');
		$password_validation->setMin(8)->setMax(64);
		$password->setPatterns($password_validation);
	}
}
