<?PHP

namespace applications;

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

<style>
    div#main {
    -webkit-transition: all 0.2s ease-out;
    -moz-transition: all 0.2s ease-out;
    -ms-transition: all 0.2s ease-out;
    -o-transition: all 0.2s ease-out;
    transition: all 0.2s ease-out;
    }
    div#main { position: fixed; top: 50px; right: 0; left: 250px; }
    div#main.menu-crush { left: 50px; }

    div#content { padding: 24px; }
</style>

<script type="text/javascript">
    window.page.setNavigator(<?= Output::json($navigator) ?>);
    window.page.setTranslate(<?= Output::json($translate) ?>);
    window.page.setUserPolicies(<?= Output::json($user_policies); ?>);
    window.page.user = <?= Output::json($whoami); ?>;

    let main = document.createElement('div');
    main.id = 'main';
    window.page.elements.push(main);

    let content = document.createElement('div');
    content.id = 'content';
    main.appendChild(content);

    let text = window.page.getTranslate('header.app_name');
    window.page.getWidgets().header = new Header();
    window.page.getWidgets().header.setTitle(text);

    let profile = window.page.getWidgets().header.getProfile(), menu = profile.getMenu();
    profile.setUsername(window.page.user.email);
    profile.setImage(window.page.user.picture);

    if (window.page.checkPolicy('iam/user/view/upsert') && window.page.checkPolicy('iam/user/action/update/me')) {
        let label = window.page.getTranslate('header.buttons.my_account'), account = menu.addItem(label, 'account_circle');
        account.href = '/iam/user/upsert/' + window.page.user._key;
    }

    let logout = window.page.getTranslate('header.buttons.logout');
    menu.addItem(logout, 'exit_to_app', function () {
        let xhr = new XMLHttpRequest(), 
            api = '/api/iam/user/logout' 
            + String.fromCharCode(63)
            + 'timestamp'
            + String.fromCharCode(61)
            + Date.now();
        xhr.open('GET', api, !0);
        xhr.onreadystatechange = function () {
            if (XMLHttpRequest.DONE !== this.readyState
                || 200 !== this.status) return;

            try {
                let response = JSON.parse(this.responseText);
                if (response.hasOwnProperty(window.page.redirect)) document.location.href = response[window.page.redirect];
                throw 'Return URL not assigned';
            } catch (message) {
                console.log(message);
            }
        };
        xhr.send();
    });

    window.page.elements.push(window.page.getWidgets().header.out());

    let api = '/api/iam/user/menu' 
        + String.fromCharCode(63)
        + 'timestamp'
        + String.fromCharCode(61)
        + Date.now(), navigator_path = window.page.getNavigator().join('/');
    window.page.getWidgets().menu = new Menu();
    window.page.getWidgets().menu.setNearElement(main);
    window.page.getWidgets().menu.setRequestUrl(api);
    window.page.getWidgets().menu.setNavigator(navigator_path);
    window.page.getWidgets().menu.request(function (response) {
        if (response.hasOwnProperty('header')) this.setHeader(response.header);
        if (false === response.hasOwnProperty('data')) return;
        this.pushModules(response.data);
    });

    window.page.elements.push(window.page.getWidgets().menu.out());
</script>
