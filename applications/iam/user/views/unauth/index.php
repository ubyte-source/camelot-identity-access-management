<?PHP

namespace applications\iam\user\views\unauth;

use configurations\Navigator as Configuration;

use Knight\armor\Output;
use Knight\armor\Composer;
use Knight\armor\Language;

Language::dictionary(__file__);
$translate = Language::getTextsNamespaceName(__namespace__);

?>

<!-- JS & CSS Layout Files -->

<link rel="stylesheet" type="text/css" href="<?= Configuration::WIDGETS; ?>button/<?= Composer::getLockVersion('widget/button'); ?>/base.css">
<script src="<?= Configuration::WIDGETS; ?>button/<?= Composer::getLockVersion('widget/button'); ?>/base.js"></script>

<script type="text/javascript">
    window.page.setTranslate(<?= Output::json($translate) ?>);
</script>

<script src="/cdn/applications/iam/user/views/unauth/1.0.0/js/base.js"></script>
<link rel="stylesheet" type="text/css" href="/cdn/applications/iam/user/views/unauth/1.0.0/css/base.css">
