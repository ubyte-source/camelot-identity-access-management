<?PHP

namespace applications\iam\policy\views\read;

use configurations\Navigator as Configuration;

use Knight\armor\Output;
use Knight\armor\Composer;
use Knight\armor\Language;

use applications\iam\policy\forms\Policy;
use applications\iam\policy\forms\Delete;

use applications\iam\user\database\Vertex as User;
use applications\iam\user\database\edges\UserToPolicy;

use extensions\Navigator;

const WIDGETS = [
    'policy'
];

$navigator = Navigator::get();

$user_policies = UserToPolicy::getPolicies();
$user_policies = array_column($user_policies, 'route');

$setting = array();
foreach (WIDGETS as $widget) {
    $navigator_widget = $navigator;
    if (4 === array_push($navigator_widget, $widget))
        $setting[$widget] = User::getHumanSettings(...$navigator_widget);
}

$policy = new Policy();
$policy = $policy->human();

$policy_delete = new Delete();
$policy_delete = $policy_delete->human();

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

<link rel="stylesheet" type="text/css" href="<?= Configuration::WIDGETS; ?>infinite/<?= Composer::getLockVersion('widget/infinite'); ?>/base.css">
<script src="<?= Configuration::WIDGETS; ?>infinite/<?= Composer::getLockVersion('widget/infinite'); ?>/base.js"></script>

<link rel="stylesheet" type="text/css" href="<?= Configuration::WIDGETS; ?>sidepanel/<?= Composer::getLockVersion('widget/sidepanel'); ?>/base.css">
<script src="<?= Configuration::WIDGETS; ?>sidepanel/<?= Composer::getLockVersion('widget/sidepanel'); ?>/base.js"></script>

<link rel="stylesheet" type="text/css" href="<?= Configuration::WIDGETS; ?>modal/<?= Composer::getLockVersion('widget/modal'); ?>/base.css">
<script src="<?= Configuration::WIDGETS; ?>modal/<?= Composer::getLockVersion('widget/modal'); ?>/base.js"></script>

<script type="text/javascript">
    window.page.setNavigator(<?= Output::json($navigator) ?>);
    window.page.setTranslate(<?= Output::json($translate) ?>);
    window.page.setUserPolicies(<?= Output::json($user_policies); ?>);
    window.page.user = <?= Output::json($whoami); ?>;
    window.page.user.setting = <?= Output::json($setting) ?>;
    window.page.tables = {
        policy: <?= Output::json($policy); ?>,
        policy_delete: <?= Output::json($policy_delete); ?>
    };
</script>

<!-- JS & CSS Files -->

<link rel="stylesheet" type="text/css" href="<?= Configuration::WIDGETS; ?>form/<?= Composer::getLockVersion('widget/form'); ?>/plugins/tooltip/tooltip.css">
<script src="<?= Configuration::WIDGETS; ?>form/<?= Composer::getLockVersion('widget/form'); ?>/plugins/tooltip/tooltip.js"></script>

<link rel="stylesheet" type="text/css" href="<?= Configuration::WIDGETS; ?>infinite/<?= Composer::getLockVersion('widget/infinite'); ?>/plugins/tooltip/tooltip.css">
<script src="<?= Configuration::WIDGETS; ?>infinite/<?= Composer::getLockVersion('widget/infinite'); ?>/plugins/tooltip/tooltip.js"></script>

<link rel="stylesheet" type="text/css" href="<?= Configuration::WIDGETS; ?>infinite/<?= Composer::getLockVersion('widget/infinite'); ?>/plugins/dropdown/dropdown.css">
<script src="<?= Configuration::WIDGETS; ?>infinite/<?= Composer::getLockVersion('widget/infinite'); ?>/plugins/dropdown/dropdown.js"></script>

<link rel="stylesheet" type="text/css" href="<?= Configuration::WIDGETS; ?>infinite/<?= Composer::getLockVersion('widget/infinite'); ?>/plugins/setting/setting.css">
<script src="<?= Configuration::WIDGETS; ?>infinite/<?= Composer::getLockVersion('widget/infinite'); ?>/plugins/setting/sortable.js"></script>
<script src="<?= Configuration::WIDGETS; ?>infinite/<?= Composer::getLockVersion('widget/infinite'); ?>/plugins/setting/setting.js"></script>

<!-- CSS View -->

<link rel="stylesheet" type="text/css" href="/cdn/applications/iam/policy/views/read/1.0.0/css/base.css">

<!-- JS View -->

<script src="/cdn/applications/iam/policy/views/read/1.0.0/js/base.js"></script>
