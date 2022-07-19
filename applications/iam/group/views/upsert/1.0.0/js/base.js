(function (window) {

    'use strict';

    Form.Plugin.Chips.Search.placeholder = function () {
        return window.page.getTranslate('form.chips.search.no_result');
    }

    let pathname = window.location.pathname.split(String.fromCharCode(47)), widgets = window.page.getWidgets();
    window.reference = pathname.slice(4);

    window.elements = {};
    window.elements.content = document.createElement('div');
    window.elements.content.id = 'content';

    window.elements.main = document.createElement('div');
    window.elements.main.id = 'main';
    window.page.addHTMLElement(window.elements.main);

    window.elements.wrapper = document.createElement('div');
    window.elements.wrapper.id = 'wrapper';
    window.elements.main.appendChild(window.elements.wrapper);

    window.elements.grid = document.createElement('div');
    window.elements.grid.className = 'pure-u-22-24 pure-u-lg-16-24 resize';
    window.elements.grid.appendChild(window.elements.content);

    window.elements.row = document.createElement('div');
    window.elements.row.className = 'pure-g';
    window.elements.row.appendChild(window.elements.grid);

    window.elements.wrapper.appendChild(window.elements.row);

    let app = window.page.getTranslate('header.app_name');
    widgets.header = new Header();
    widgets.header.setTitle(app);

    let profile = widgets.header.getProfile(), burger = profile.getMenu();
    profile.setUsername(window.page.user.email);
    profile.setImage(window.page.user.picture);

    if (window.page.checkPolicy('iam/user/view/upsert') && window.page.checkPolicy('iam/user/action/update/me')) {
        let account_label = window.page.getTranslate('header.buttons.my_account'), account = burger.addItem(account_label, 'account_circle');
        account.href = '/iam/user/upsert/' + window.page.user._key;
    }

    let logout = window.page.getTranslate('header.buttons.logout');
    burger.addItem(logout, 'exit_to_app', function () {
        let xhr = new WXmlHttpRequest(),
            api = '/api/iam/user/logout'
                + String.fromCharCode(63)
                + 'timestamp'
                + String.fromCharCode(61)
                + Date.now();
        xhr.setRequestUrl(api);
        xhr.setCallbackSuccess(function (response) {
            if (response.hasOwnProperty('return_url')) document.location.href = response.return_url;
        });
        xhr.request();
    });

    window.page.addHTMLElement(widgets.header.out());

    let menu = '/api/iam/user/menu'
        + String.fromCharCode(63)
        + 'timestamp'
        + String.fromCharCode(61)
        + Date.now();
    widgets.menu = new Menu();
    widgets.menu.setNearElement(window.elements.main);
    widgets.menu.setRequestUrl(menu);
    widgets.menu.setNavigator(window.page.getNavigator().join('/'));
    widgets.menu.request(function (response) {
        if (response.hasOwnProperty('header')) this.setHeader(response.header);
        if (false === response.hasOwnProperty('data')) return;
        this.pushModules(response.data);

        let pathname = window.location.pathname.split(/[\\\/]/);
        if (pathname.hasOwnProperty(2)) {
            let list = this.getList();
            for (let item = 0; item < list.length; item++) {
                let href = list[item].out().getAttribute('href');
                if (href === null) continue;

                let split = href.split(/[\\\/]/);
                if (split.hasOwnProperty(2)
                    && pathname[2] === split[2]) list[item].out().classList.add('active');
            }
        }
    });

    window.page.addHTMLElement(widgets.menu.out());

    let title = window.reference.length === 0 ? window.page.getTranslate('nav.add') : window.page.getTranslate('nav.edit'),
        back = '/iam/group/read';
    widgets.nav = new Nav();
    widgets.nav.setBack(back);
    widgets.nav.setReturnButton('arrow_back');
    widgets.nav.setTitle(title);

    window.elements.main.appendChild(widgets.nav.out());

    widgets.form = new Form();

    for (let item = 0; item < window.page.tables.group.fields.length; item++) {
        if (window.page.tables.group.fields[item].name === 'share') continue;
        widgets.form.addInput(window.page.tables.group.fields[item]);
    }

    window.elements.content.appendChild(widgets.form.out());

    let buttons_form = document.createElement('div');
    buttons_form.className = 'buttons-form';
    window.elements.content.appendChild(buttons_form);

    let submit = new Button(), icon = window.reference.length === 0 ? 'group_add' : 'save';

    submit.getIcon().set(icon);
    submit.setText(window.reference.length === 0 ? window.page.getTranslate('buttons.add') : window.page.getTranslate('buttons.save'));
    submit.onClick(function () {
        let preloader = widgets.form.getManager().status();
        if (preloader === true) return;

        this.getLoader().apply(window.page.getTranslate('buttons.loader'));

        widgets.form.request(function () {
            submit.getLoader().remove();
        });
    });

    buttons_form.appendChild(submit.out());

    let create_api = '/api/iam/group/create'
        + String.fromCharCode(63)
        + 'timestamp'
        + String.fromCharCode(61)
        + Date.now();
    widgets.form.setRequestUrl(create_api);

    window.prepareProtected = function () {
        for (let item = 0; item < window.page.tables.group.fields.length; item++) {
            if (window.page.tables.group.fields[item].name !== 'share') continue;

            widgets.form.addInput(window.page.tables.group.fields[item]);
        }
    }

    if (window.reference.length === 0) {
        window.prepareProtected();
    } else {
        let update_api = '/api/iam/group/update'
            + String.fromCharCode(47)
            + encodeURIComponent(window.reference[0])
            + String.fromCharCode(63)
            + 'timestamp'
            + String.fromCharCode(61)
            + Date.now();
        widgets.form.setRequestUrl(update_api);
        widgets.form.getManager().show(true);

        let main = new WXmlHttpRequest(),
            detail_api = '/api/iam/group/detail'
                + String.fromCharCode(47)
                + encodeURIComponent(window.reference[0])
                + String.fromCharCode(63)
                + 'timestamp'
                + String.fromCharCode(61)
                + Date.now();
        main.setRequestUrl(detail_api);
        main.setCallbackSuccess(function (response) {
            if (response.data.hasOwnProperty('owner') && response.data.owner.hasOwnProperty('_key') && response.data.owner._key === window.page.user._key) window.prepareProtected();
            for (let item in response.data) switch (item) {
                case 'users':
                case 'group':
                case 'share':
                    widgets.form.set(item, { _key: response.data[item] });
                    break;
                default:
                    widgets.form.set(item, response.data[item]);
            }
        });
        main.request();
    }

})(window);
