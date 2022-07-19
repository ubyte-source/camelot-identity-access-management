<?PHP

namespace applications\sso\oauth\forms;

use applications\sso\oauth\database\Vertex;

class Oauth extends Vertex
{
    protected function initialize() : void
	{
        parent::initialize();

        $this->getField('fields_relation')->setProtected(true);
    }
}
