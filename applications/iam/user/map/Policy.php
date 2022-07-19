<?PHP

namespace applications\iam\user\map;

use stdClass;

use applications\iam\policy\database\Vertex;
use applications\iam\policy\database\edges\PolicyToUser;

class Policy extends Vertex
{
    public function after() : void
    {
        $policy_to_user = new PolicyToUser();
        $policy_to_user_fields = $policy_to_user->getFields();
        foreach ($policy_to_user_fields as $field) $this->addFieldClone($field);
    }

    public function getStructure() : stdClass
    {
        $policy_detail = $this->getAllFieldsValues(true);
        return (object)$policy_detail;
    }
}
