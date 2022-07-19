<?PHP

namespace applications\iam\user\map;

use stdClass;

use ArangoDB\operations\Select;
use ArangoDB\entity\common\Arango;

use applications\iam\user\map\assignment\Group;
use applications\iam\user\map\assignment\Policy;

class Assignment
{
    protected $groups = [];
    protected $policies = [];

    public function __construct(Select $select)
    {
        $response_query = $select->run();

        foreach ($response_query as $document) {
            $policy = new Policy();

            $policy->setFromAssociative($document['policy']);

            $policy_field_path = $policy->getField('path');
            $policy_field_path->setProtected(false);
            $policy_field_path_name = $policy_field_path->getName();

            $path_value = !array_key_exists($policy_field_path_name, $document)
                || !is_array($document[$policy_field_path_name]) ? [] : array_column($document[$policy_field_path_name], Arango::KEY);

            $policy_field_path->setValue($path_value);

            $this->pushPolicies($policy);

            $policy_field_group_value = array_shift($path_value);

            if (null !== $policy_field_group_value) {
                $policy_field_group = $policy->getField('group');
                $policy_field_group->setProtected(false);
                $policy_field_group->setValue($policy_field_group_value);

                foreach ($document[$policy_field_path_name] as $i => $document_group) {
                    $group = new Group();
                    $group->setSafeMode(false);
                    $group->setFromAssociative($document_group);

                    $group_field_key = $group->getField(Arango::KEY);
                    $group_field_key_value = $group_field_key->getValue();

                    $group_find = $this->getGroup($group_field_key_value);

                    if (null === $group_find) {
                        $this->pushGroups($group);
                        $group_find = $group;
                    }

                    if ($i === 0) continue;

                    $group_find_field_paths = $group_find->getField('paths');
                    $group_find_field_paths_value = $group_find_field_paths->getValue();
                    $group_find_field_paths_value_joined = array_map(function ($item) {
                        return implode($item);
                    }, $group_find_field_paths_value);

                    $policy_field_path_value = $policy_field_path->getValue();
                    $policy_field_path_value_match = implode($policy_field_path_value);
                    if (in_array($policy_field_path_value_match, $group_find_field_paths_value_joined)) continue;

                    array_push($group_find_field_paths_value, $policy_field_path_value);

                    $group_find_field_paths->setProtected(false);
                    $group_find_field_paths->setValue($group_find_field_paths_value);
                }
            }
        }
    }

    public function getResponse() : stdClass 
    {
        $response = new stdClass();

        $response->groups = $this->getGroups();
        $response->groups = array_map(function (Group $group) {
            $paths = $group->getField('paths');
            $paths_value = $paths->getValue();

            $paths_value = array_unique($paths_value, SORT_REGULAR);
            $paths_value = array_values($paths_value);

            usort($paths_value, function (array $a, array $b) {
                return count($b) - count($a);
            });

            $paths_value_resolve = [];
            $paths_value_resolve_keys = array_map(function (array $path) {
                $path_reverse = array_reverse($path);
                return implode($path_reverse);
            }, $paths_value);

            $paths_value = array_combine($paths_value_resolve_keys, $paths_value);

            $group_field_key = $group->getField(Arango::KEY);
            $group_field_key_value = $group_field_key->getValue();

            foreach ($paths_value as $key => $value) {
                $keys = array_keys($paths_value_resolve);
                if (preg_grep('/^(' . $key . ')/', $keys)) continue;

                $found = array_search($group_field_key_value, $value, true);
                $paths_value_resolve[$key] = array_slice($value, 0, $found);
            }

            $paths_value_resolve = array_values($paths_value_resolve);
            $paths_value_resolve = array_unique($paths_value_resolve, SORT_REGULAR);
            $group->getField('paths')->setValue($paths_value_resolve);
            $group_values = $group->getAllFieldsValues();

            return $group_values;
        }, $response->groups);

        $response->groups = array_values($response->groups);

        $response->policies = $this->getPolicies();
        $response->policies = array_map(function (Policy $policy) {
            $policy_values = $policy->getAllFieldsValues();
            return $policy_values;
        }, $response->policies);

        $response->policies = array_values($response->policies);

        return $response;
    }

    protected function pushPolicies(Policy ...$policies) : int
    {
        if (empty($policies)) return count($this->policies);
        return array_push($this->policies, ...$policies);
    }

    protected function getPolicies() : array
    {
        return $this->policies;
    }

    protected function pushGroups(Group ...$groups) : int
    {
        if (empty($groups)) return count($this->groups);
        return array_push($this->groups, ...$groups);
    }

    protected function getGroups() : array
    {
        return $this->groups;
    }

    protected function getGroup(string $key) :? Group
    {
        $groups = $this->getGroups();
        foreach ($groups as $group) {
            $group_field_key = $group->getField(Arango::KEY);
            $group_field_key_value = $group_field_key->getValue();
            if ($key === $group_field_key_value) return $group;
        }
        return null;
    }
}
