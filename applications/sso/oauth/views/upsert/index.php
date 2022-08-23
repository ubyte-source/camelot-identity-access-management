<?PHP

namespace applications\sso\oauth\views\upsert;

use configurations\Navigator as Configuration;

use Knight\armor\Output;
use Knight\armor\Composer;
use Knight\armor\Language;

use applications\iam\user\database\Vertex as User;
use applications\iam\user\database\edges\UserToPolicy;
use applications\sso\oauth\forms\Upsert;

use extensions\Navigator;

$navigator = Navigator::get();

$user_policies = UserToPolicy::getPolicies();
$user_policies = array_column($user_policies, 'route');

$oauth = new Upsert();
$oauth = $oauth->human();

$whoami = User::getWhoami(true);
$whoami = $whoami->getAllFieldsValues(true);

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

<script type="text/javascript">
    window.page.setNavigator(<?= Output::json($navigator) ?>);
    window.page.setTranslate(<?= Output::json($translate) ?>);
    window.page.setUserPolicies(<?= Output::json($user_policies); ?>);
    window.page.user = <?= Output::json($whoami); ?>;
    window.page.tables = {
        oauth: <?= Output::json($oauth); ?>
    };
</script>

<!-- JS & CSS Plugins Files -->

<link rel="stylesheet" type="text/css" href="<?= Configuration::WIDGETS; ?>form/<?= Composer::getLockVersion('widget/form'); ?>/plugins/dropdown/dropdown.css">
<script src="<?= Configuration::WIDGETS; ?>form/<?= Composer::getLockVersion('widget/form'); ?>/plugins/dropdown/dropdown.js"></script>
<script src="<?= Configuration::WIDGETS; ?>form/<?= Composer::getLockVersion('widget/form'); ?>/plugins/dropdown/search/search.js"></script>

<link rel="stylesheet" type="text/css" href="<?= Configuration::WIDGETS; ?>form/<?= Composer::getLockVersion('widget/form'); ?>/plugins/chips/chips.css">
<script src="<?= Configuration::WIDGETS; ?>form/<?= Composer::getLockVersion('widget/form'); ?>/plugins/chips/chips.js"></script>

<link rel="stylesheet" type="text/css" href="<?= Configuration::WIDGETS; ?>form/<?= Composer::getLockVersion('widget/form'); ?>/plugins/tooltip/tooltip.css">
<script src="<?= Configuration::WIDGETS; ?>form/<?= Composer::getLockVersion('widget/form'); ?>/plugins/tooltip/tooltip.js"></script>
<script src="<?= Configuration::WIDGETS; ?>form/<?= Composer::getLockVersion('widget/form'); ?>/plugins/row/action.js"></script>
<script src="<?= Configuration::WIDGETS; ?>form/<?= Composer::getLockVersion('widget/form'); ?>/plugins/matrioska.js"></script>

<!-- CSS View -->

<link rel="stylesheet" type="text/css" href="/cdn/applications/sso/oauth/views/upsert/1.0.0/css/base.css">

<!-- JS View -->

<script src="/cdn/applications/sso/oauth/views/upsert/1.0.0/js/base.js"></script>
