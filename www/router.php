<?PHP

namespace www;

require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'boot.php';

const SSO = 'iam/user/login';
const SECURE = [
    'php'
];

use Knight\armor\Output;
use Knight\armor\Cookie;
use Knight\armor\Request;

use extensions\Navigator;

use applications\iam\user\database\Vertex as User;

$navigator_protocol = Navigator::getProtocol();

$client = Navigator::getClientIP();
$client = long2ip($client);

if (!!filter_var($client, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE)
    && Cookie::getSecure()
    && $navigator_protocol === 'http')
        $changelocation = 'https://' . $_SERVER[Navigator::HTTP_HOST] . $_SERVER[Navigator::REQUEST_URI];

$login = Request::get('login');
if (null !== $login)
    $changelocation = 'https://' . $_SERVER[Navigator::HTTP_HOST] . chr(47) . SSO . chr(63) . Navigator::RETURN_URL . chr(61) . $login;

if (isset($changelocation)) {

    Navigator::noCache();

    header('HTTP/1.1 301 Moved Permanently');
    header('Location: ' . $changelocation);

    exit;
}

$handler = parse_url($_SERVER[Navigator::REQUEST_URI], PHP_URL_PATH);
$handler_match = dirname($handler);
$handler_match_secure = implode(chr(124), SECURE);
if (preg_match('/\.(' . $handler_match_secure . ')/', $handler_match)) Output::print(false);

$handler = trim($handler, chr(47));
$handler = explode(chr(47), $handler);
$handler = array_filter($handler, 'strlen');
$handler = array_values($handler);
$handler = reset($handler);

switch ($handler) {
    case 'api':
    case 'cdn':
    case 'structure':
        include BASE_HANDLERS . $handler . chr(46). 'php';
        exit;
    default:
        include BASE_HANDLERS . 'view' . chr(46). 'php';
        exit;
}
