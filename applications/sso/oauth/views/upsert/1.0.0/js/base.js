(function (window) {

    'use strict';

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
            api = '/api/sso/user/gateway/iam/iam/user/logout'
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
        back = '/sso/oauth/read';
    widgets.nav = new Nav();
    widgets.nav.setBack(back);
    widgets.nav.setReturnButton('arrow_back');
    widgets.nav.setTitle(title);

    window.elements.main.appendChild(widgets.nav.out());

    widgets.form = new Form();

    for (let item = 0; item < window.page.tables.oauth.fields.length; item++) {
        if (window.page.tables.oauth.fields[item].name === 'redirect_uri') window.page.tables.oauth.fields[item][Form.Container.editable()] = false;
        widgets.form.addInput(window.page.tables.oauth.fields[item]);
    }

    widgets.tabs = new Tabs();
    widgets.tabs.name = 'data-tab-name';
    widgets.tabs.setEventShow(function (ev) {
        let name = Tabs.closestAttribute(ev.target, widgets.tabs.name);
        if (name === null) return;
    });

    window.elements.content.appendChild(widgets.tabs.out());

    let form_configuration = widgets.form.out(),
        form_configuration_name = window.page.getTranslate('tabs.config');
    widgets.tabs.addItem(form_configuration_name, form_configuration, 'material-icons admin_panel_settings').show().out();

    window.elements.content.appendChild(form_configuration);

    let form_domain_name = window.page.getTranslate('tabs.domain'),
        domain_container = document.createElement('form');

    domain_container.appendChild(widgets.form.getRow('domain').out());
    domain_container.appendChild(widgets.form.getRow('redirect_uri').out());
    widgets.tabs.addItem(form_domain_name, domain_container, 'material-icons dns').out();
    window.elements.content.appendChild(domain_container);

    let form_remote_name = window.page.getTranslate('tabs.remote'),
        remote_container = document.createElement('form');

    remote_container.appendChild(widgets.form.getRow('remote_whoami').out());
    remote_container.appendChild(widgets.form.getRow('fields_relation').out());
    widgets.tabs.addItem(form_remote_name, remote_container, 'material-icons settings_remote').out();
    window.elements.content.appendChild(remote_container);

    let buttons_form = document.createElement('div');
    buttons_form.className = 'buttons-form';
    window.elements.content.appendChild(buttons_form);

    let submit = new Button(), icon = window.reference.length === 0 ? 'add' : 'save';
    submit.getIcon().set(icon);
    submit.setText(window.reference.length === 0 ? window.page.getTranslate('buttons.add') : window.page.getTranslate('buttons.save'));
    submit.onClick(function () {
        let form_have_preloader = widgets.form.getManager().status();
        if (form_have_preloader === true) return;

        this.getLoader().apply(window.page.getTranslate('buttons.loader'));

        widgets.form.request(function () {
            submit.getLoader().remove();
        });
    });
    buttons_form.appendChild(submit.out());

    let create_api = '/api/sso/oauth/create'
        + String.fromCharCode(63)
        + 'timestamp'
        + String.fromCharCode(61)
        + Date.now();
    widgets.form.setRequestUrl(create_api);

    if (window.reference.length === 0) return;

    let update_api = '/api/sso/oauth/update'
        + String.fromCharCode(47)
        + encodeURIComponent(window.reference[0])
        + String.fromCharCode(63)
        + 'timestamp'
        + String.fromCharCode(61)
        + Date.now();
    widgets.form.setRequestUrl(update_api);
    widgets.form.getManager().show(true);

    let main = new WXmlHttpRequest(),
        detail_api = '/api/sso/oauth/detail'
            + String.fromCharCode(47)
            + encodeURIComponent(window.reference[0])
            + String.fromCharCode(63)
            + 'timestamp'
            + String.fromCharCode(61)
            + Date.now();
    main.setRequestUrl(detail_api);
    main.setCallbackSuccess(function (response) {
        for (let item in response.data) widgets.form.set(item, response.data[item]);
    });
    main.request();
})(window);
