<?PHP

namespace applications\iam\user\views\login;

use IAM\Sso;

use configurations\Bumblebee;
use configurations\Navigator as Configuration;

use Knight\armor\Output;
use Knight\armor\Request;
use Knight\armor\Composer;
use Knight\armor\Language;

use applications\iam\user\database\Vertex as User;
use applications\iam\user\forms\Login;
use applications\iam\user\forms\Email;
use applications\iam\user\forms\Register;

use applications\iam\user\database\edges\Session;

use extensions\Navigator;

if (User::checkFromAuthorization()) {
    $whoami = User::getWhoami(true);
    $whoami_authorization = Session::getAuthorization();
    $return_url = Request::get(Navigator::RETURN_URL);
    if (null !== $whoami && $whoami_authorization !== null && null !== $return_url) {
        $url_detail = base64_decode($return_url);
        $url_detail = parse_url($url_detail);
        if (!empty($url_detail) && array_key_exists('host', $url_detail)) {
            $whoami_authorization = base64_encode($whoami_authorization);
            $url_detail = (object)$url_detail;
            $url = property_exists($url_detail, 'scheme') ? $url_detail->scheme : 'http';
            $url = $url . '://' . $url_detail->host . chr(47) . 'api' . chr(47) . Sso::AUTHORIZATION . chr(47) . $whoami_authorization . chr(63) . $_SERVER[Navigator::QUERY_STRING];

            header('HTTP/1.1 301 Moved Permanently');
            header('Location: ' . $url);

            exit;
        }
    }
}

$navigator = Navigator::get();

$password = new Login();
$password = $password->human(true);

$email = new Email();
$email = $email->human(true);

$register = new Register();
$register = $register->human(true);

Language::dictionary(__file__);
$translate = Language::getTextsNamespaceName(__namespace__);

?>

<!-- JS & CSS Layout Files -->

<link rel="stylesheet" type="text/css" href="<?= Configuration::WIDGETS; ?>button/<?= Composer::getLockVersion('widget/button'); ?>/base.css">
<script src="<?= Configuration::WIDGETS; ?>button/<?= Composer::getLockVersion('widget/button'); ?>/base.js"></script>

<link rel="stylesheet" type="text/css" href="<?= Configuration::WIDGETS; ?>nav/<?= Composer::getLockVersion('widget/nav'); ?>/base.css">
<script src="<?= Configuration::WIDGETS; ?>nav/<?= Composer::getLockVersion('widget/nav'); ?>/base.js"></script>

<link rel="stylesheet" type="text/css" href="<?= Configuration::WIDGETS; ?>header/<?= Composer::getLockVersion('widget/header'); ?>/base.css">
<script src="<?= Configuration::WIDGETS; ?>header/<?= Composer::getLockVersion('widget/header'); ?>/base.js"></script>

<link rel="stylesheet" type="text/css" href="<?= Configuration::WIDGETS; ?>menu/<?= Composer::getLockVersion('widget/menu'); ?>/base.css">
<script src="<?= Configuration::WIDGETS; ?>menu/<?= Composer::getLockVersion('widget/menu'); ?>/base.js"></script>

<link rel="stylesheet" type="text/css" href="<?= Configuration::WIDGETS; ?>form/<?= Composer::getLockVersion('widget/form'); ?>/base.css">
<script src="<?= Configuration::WIDGETS; ?>form/<?= Composer::getLockVersion('widget/form'); ?>/base.js"></script>

<link rel="stylesheet" type="text/css" href="<?= Configuration::WIDGETS; ?>tabs/<?= Composer::getLockVersion('widget/tabs'); ?>/base.css">
<script src="<?= Configuration::WIDGETS; ?>tabs/<?= Composer::getLockVersion('widget/tabs'); ?>/base.js"></script>

<link rel="stylesheet" type="text/css" href="<?= Configuration::WIDGETS; ?>bumblebee/<?= Composer::getLockVersion('widget/bumblebee'); ?>/base.css">
<script src="<?= Configuration::WIDGETS; ?>bumblebee/<?= Composer::getLockVersion('widget/bumblebee'); ?>/base.js"></script>

<link rel="stylesheet" type="text/css" href="<?= Configuration::WIDGETS; ?>modal/<?= Composer::getLockVersion('widget/modal'); ?>/base.css">
<script src="<?= Configuration::WIDGETS; ?>modal/<?= Composer::getLockVersion('widget/modal'); ?>/base.js"></script>

<script type="text/javascript">
    window.page.setNavigator(<?= Output::json($navigator) ?>);
    window.page.setTranslate(<?= Output::json($translate) ?>);
    window.page.bumblebee = {};
    window.page.bumblebee.apikey = '<?= Bumblebee::APIKEY; ?>';
    window.page.bumblebee.locale = <?= Output::json(Bumblebee::LOCALE); ?>;
    window.page.tables = {
        register: <?= Output::json($register); ?>,
        password: <?= Output::json($password); ?>,
        email: <?= Output::json($email); ?>
    };
    window.page.sso = '<?= Navigator::RETURN_URL; ?>';
    window.page.authorization = '<?= Sso::AUTHORIZATION; ?>';
</script>

<!-- JS & CSS Plugins Files -->

<link rel="stylesheet" type="text/css" href="<?= Configuration::WIDGETS; ?>asset/<?= Composer::getLockVersion('widget/asset'); ?>/css/flag-icon.min.css">

<script src="<?= Configuration::WIDGETS; ?>form/<?= Composer::getLockVersion('widget/form'); ?>/plugins/row/action.js"></script>

<link rel="stylesheet" type="text/css" href="<?= Configuration::WIDGETS; ?>form/<?= Composer::getLockVersion('widget/form'); ?>/plugins/dropdown/dropdown.css">
<script src="<?= Configuration::WIDGETS; ?>form/<?= Composer::getLockVersion('widget/form'); ?>/plugins/dropdown/dropdown.js"></script>
<script src="<?= Configuration::WIDGETS; ?>form/<?= Composer::getLockVersion('widget/form'); ?>/plugins/dropdown/search/search.js"></script>

<link rel="stylesheet" type="text/css" href="<?= Configuration::WIDGETS; ?>form/<?= Composer::getLockVersion('widget/form'); ?>/plugins/tooltip/tooltip.css">
<script src="<?= Configuration::WIDGETS; ?>form/<?= Composer::getLockVersion('widget/form'); ?>/plugins/tooltip/tooltip.js"></script>

<!-- CSS View -->

<link rel="stylesheet" type="text/css" href="/cdn/applications/iam/user/views/login/2.0.0/css/base.css">

<!-- JS View -->

<script src="/cdn/applications/iam/user/views/login/2.0.0/js/base.js"></script>
