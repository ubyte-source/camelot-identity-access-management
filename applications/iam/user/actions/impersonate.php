<?PHP

namespace applications\iam\user\actions;

use Knight\armor\Output;
use Knight\armor\Language;

use ArangoDB\Initiator as ArangoDB;
use ArangoDB\Statement;
use ArangoDB\operations\common\Choose;
use ArangoDB\entity\common\Arango;

use applications\iam\user\database\Vertex as User;
use applications\iam\policy\database\Vertex as Policy;
use applications\iam\user\database\edges\Session;

use extensions\Navigator;

const EXPIRED = 1440;

Policy::mandatories('iam/user/action/impersonate');

$key = parse_url($_SERVER[Navigator::REQUEST_URI], PHP_URL_PATH);
$key = basename($key);

$user = new User();
$user_fields = $user->getFields();
foreach ($user_fields as $field) $field->setProtected(true);

$user->getField(Arango::KEY)->setProtected(false)->setRequired(true)->setValue($key);

if (!!$errors = $user->checkRequired()->getAllFieldsWarning()) {
    Language::dictionary(__file__);
    $notice = __namespace__ . '\\' . 'notice';
    $notice = Language::translate($notice);
    Output::concatenate('notice', $notice);
    Output::concatenate('errors', $errors);
    Output::print(false);
}

Navigator::setImpersonate(Navigator::ENABLE);
Session::generate($user, EXPIRED);

$authorization = Session::getAuthorization();
$authorization = base64_encode($authorization);
Output::concatenate('authorization', $authorization);
Output::print(true);
