<?PHP

namespace applications\iam\user\actions;

use IAM\Gateway;
use IAM\Request as IAMRequest;
use IAM\Configuration as IAMConfiguration;

use Knight\armor\Output;
use Knight\armor\Request;
use Knight\armor\Language;

use applications\iam\user\forms\Register;
use applications\iam\user\database\Vertex as User;
use applications\iam\user\database\edges\Session;
use applications\sso\oauth\database\Vertex as Oauth;

use extensions\Navigator;

const EXPIRE = 60;
const CREATE = 'iam/user/create';
const ACCOUNT = 'account';
const ACCOUNT_OWNER = 'owner';

$register = new Register();
$register->setFromAssociative((array)Request::post());
$register_email = $register->getField('email');
$register_email_value = $register_email->getValue();

if (!!$errors = $register->checkRequired()->getAllFieldsWarning()) {
    Language::dictionary(__file__);
    $notice = __namespace__ . '\\' . 'notice';
    $notice = Language::translate($notice);
    Output::concatenate('notice', $notice);
    Output::concatenate('errors', $errors);
    Output::print(false);
}

$serial = $register->getField('device_serial');
$serial_value = $serial->getValue();

$user = new User();
$user_fields = $user->getFields();
foreach ($user_fields as $field) $field->setProtected(true);

$user->getField('email')->setProtected(false)->setRequired(true)->setValue(User::ROOT);

Navigator::setImpersonate(Navigator::ENABLE);
Session::generate($user, EXPIRE);
IAMRequest::instance(Session::getAuthorization());

$device_api = 'machine/device/detail' . chr(47) . $serial_value;
$device = Gateway::callAPI('engine', $device_api);
$device = $device->{Output::APIDATA};
if (false === property_exists($device, ACCOUNT)
    || false === property_exists($device->account, ACCOUNT_OWNER)) Output::print(false);

$passphrase = 'machine/device/passphrase' . chr(47) . $serial_value;
Gateway::callAPI('engine', $passphrase, (array)$register->getAllFieldsValues(false, false));

$user = new User();
$user->setFromAssociative((array)$device->{ACCOUNT}->{ACCOUNT_OWNER});

Navigator::setImpersonate(Navigator::ENABLE);
Session::generate($user, EXPIRE);
IAMRequest::instance(Session::getAuthorization());

$user = new User();
$user->setFromAssociative($register->getAllFieldsValues(false, false));
$user->getField('type')->setValue(User::HUMAN);

$domain = substr($register_email_value, 1 + strrpos($register_email_value, chr(64)));
$domain_check = Oauth::check($domain);
if (false === empty($domain_check))
    $user->getField('type')->setValue(User::OAUTH);

$user = Gateway::callAPI(IAMConfiguration::getApplicationBasename(), CREATE,
    (array)$user->getAllFieldsValues(false, false));

$registered = new User();
$registered->setSafeMode(false)->setReadMode(true);
$registered->setFromAssociative((array)$user->{Output::APIDATA});
$registered_value = $registered->getAllFieldsValues(false, false);
Output::concatenate(Output::APIDATA, $registered_value);
Output::print(true);
