<?PHP

namespace applications\iam\user\actions;

use Knight\armor\Output;

use applications\iam\user\database\edges\UserToPolicy;

$user_policies = UserToPolicy::getPolicies();
$user_policies = array_column($user_policies, 'route');

Output::concatenate(Output::APIDATA, $user_policies);
Output::print(true);
