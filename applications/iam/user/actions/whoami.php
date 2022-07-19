<?PHP

namespace applications\iam\user\actions;

use Knight\armor\Output;

use applications\iam\user\database\Vertex as User;
use applications\iam\policy\database\Vertex as Policy;

User::login();

$whoami = User::getWhoami(true);
Output::concatenate(Output::APIDATA, $whoami->getAllFieldsValues(false, false));
Output::print(true);
