<?PHP

namespace applications\iam\user\views\assignment;

use configurations\Navigator as Configuration;

use Knight\armor\Output;
use Knight\armor\Composer;
use Knight\armor\Language;

use applications\iam\user\database\Vertex as User;
use applications\iam\user\database\edges\UserToPolicy;

use extensions\Navigator;

$navigator = Navigator::get();

$user_policies = UserToPolicy::getPolicies();
$user_policies = array_column($user_policies, 'route');

$user = new User();
$user = $user->human();

$whoami = User::getWhoami(true);
$whoami = $whoami->getAllFieldsValues(true);

Language::dictionary(__file__);
$translate = Language::getTextsNamespaceName(__namespace__);

?>

<!-- JS & CSS Layout Files -->
<link rel="stylesheet" type="text/css" href="<?= Configuration::WIDGETS; ?>asset/<?= Composer::getLockVersion('widget/asset'); ?>/css/flag-icon.min.css">

<link rel="stylesheet" type="text/css" href="<?= Configuration::WIDGETS; ?>button/<?= Composer::getLockVersion('widget/button'); ?>/base.css">
<script src="<?= Configuration::WIDGETS; ?>button/<?= Composer::getLockVersion('widget/button'); ?>/base.js"></script>

<link rel="stylesheet" type="text/css" href="<?= Configuration::WIDGETS; ?>nav/<?= Composer::getLockVersion('widget/nav'); ?>/base.css">
<script src="<?= Configuration::WIDGETS; ?>nav/<?= Composer::getLockVersion('widget/nav'); ?>/base.js"></script>

<link rel="stylesheet" type="text/css" href="<?= Configuration::WIDGETS; ?>header/<?= Composer::getLockVersion('widget/header'); ?>/base.css">
<script src="<?= Configuration::WIDGETS; ?>header/<?= Composer::getLockVersion('widget/header'); ?>/base.js"></script>

<link rel="stylesheet" type="text/css" href="<?= Configuration::WIDGETS; ?>menu/<?= Composer::getLockVersion('widget/menu'); ?>/base.css">
<script src="<?= Configuration::WIDGETS; ?>menu/<?= Composer::getLockVersion('widget/menu'); ?>/base.js"></script>

<link rel="stylesheet" type="text/css" href="<?= Configuration::WIDGETS; ?>sidepanel/<?= Composer::getLockVersion('widget/sidepanel'); ?>/base.css">
<script src="<?= Configuration::WIDGETS; ?>sidepanel/<?= Composer::getLockVersion('widget/sidepanel'); ?>/base.js"></script>

<link rel="stylesheet" type="text/css" href="<?= Configuration::WIDGETS; ?>info/<?= Composer::getLockVersion('widget/info'); ?>/base.css">
<script src="<?= Configuration::WIDGETS; ?>info/<?= Composer::getLockVersion('widget/info'); ?>/base.js"></script>

<link rel="stylesheet" type="text/css" href="<?= Configuration::WIDGETS; ?>form/<?= Composer::getLockVersion('widget/form'); ?>/base.css">
<script src="<?= Configuration::WIDGETS; ?>form/<?= Composer::getLockVersion('widget/form'); ?>/base.js"></script>

<script type="text/javascript">
    window.page.setNavigator(<?= Output::json($navigator) ?>);
    window.page.setTranslate(<?= Output::json($translate) ?>);
    window.page.setUserPolicies(<?= Output::json($user_policies); ?>);
    window.page.user = <?= Output::json($whoami); ?>;
    window.page.tables = {};
    window.page.tables.user = <?= Output::json($user); ?>;
</script>

<!-- JS & CSS Plugins Files -->

<link rel="stylesheet" type="text/css" href="<?= Configuration::WIDGETS; ?>form/<?= Composer::getLockVersion('widget/form'); ?>/plugins/dropdown/dropdown.css">
<script src="<?= Configuration::WIDGETS; ?>form/<?= Composer::getLockVersion('widget/form'); ?>/plugins/dropdown/dropdown.js"></script>

<!-- CSS View -->

<link rel="stylesheet" type="text/css" href="/assignment/1.0.0/required/css/preloader.css">
<link rel="stylesheet" type="text/css" href="/assignment/1.0.0/required/css/tooltip.css">
<link rel="stylesheet" type="text/css" href="/assignment/1.0.0/required/css/assign.css">
<link rel="stylesheet" type="text/css" href="/assignment/1.0.0/required/css/action.css">
<link rel="stylesheet" type="text/css" href="/assignment/1.0.0/required/css/line.css">
<link rel="stylesheet" type="text/css" href="/assignment/1.0.0/required/css/path.css">

<link rel="stylesheet" type="text/css" href="/assignment/1.0.0/css/dropper.css">

<link rel="stylesheet" type="text/css" href="/assignment/1.0.0/css/search.css">
<link rel="stylesheet" type="text/css" href="/assignment/1.0.0/css/group.css">

<link rel="stylesheet" type="text/css" href="/cdn/applications/iam/user/views/assignment/1.0.0/css/base.css">

<!-- JS View -->

<script src="/assignment/1.0.0/required/js/preloader.js"></script>
<script src="/assignment/1.0.0/required/js/sortable.js"></script>
<script src="/assignment/1.0.0/required/js/tooltip.js"></script>
<script src="/assignment/1.0.0/required/js/action.js"></script>
<script src="/assignment/1.0.0/required/js/assign.js"></script>
<script src="/assignment/1.0.0/required/js/line.js"></script>
<script src="/assignment/1.0.0/required/js/path.js"></script>

<script src="/assignment/1.0.0/js/dropper.js"></script>

<script src="/assignment/1.0.0/js/policy.js"></script>
<script src="/assignment/1.0.0/js/group.js"></script>

<script src="/assignment/1.0.0/js/search.js"></script>
<script src="/assignment/1.0.0/js/list.js"></script>

<script src="/cdn/applications/iam/user/views/assignment/1.0.0/js/base.js"></script>
