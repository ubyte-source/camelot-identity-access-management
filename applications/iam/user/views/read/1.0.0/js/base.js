
(function (window) {

    'use strict';

    window.Infinite.Plugin.Setting.Search.NotFound.text = function () {
        return window.page.getTranslate('infinite.search.not_found');
    }
    window.Infinite.Plugin.Setting.Search.placeholder = function () {
        return window.page.getTranslate('infinite.setting.placeholder');
    }

    let pathname = window.location.pathname.split(String.fromCharCode(47)), widgets = window.page.getWidgets();
    window.reference = pathname.slice(4);

    window.elements = {};
    window.elements.content = document.createElement('div');
    window.elements.content.id = 'content';
window.elements.content.className = 'widget-infinite-enable-print';

    window.elements.main = document.createElement('div');
    window.elements.main.id = 'main';
window.elements.main.className = 'widget-infinite-enable-print';
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

    widgets.nav = new Nav();
    widgets.nav.setBack(String.fromCharCode(47));
    widgets.nav.setReturnButton('arrow_back');
    widgets.nav.setTitle(window.page.getTranslate('nav.title'));

    window.elements.main.appendChild(widgets.nav.out());

    window.buttons = [];
    window.choosed = [];

    let wrapper = document.createElement('div');
    wrapper.className = 'table-wrapper';
    window.elements.content.appendChild(wrapper);

    for (let item = 0; item < window.page.tables.user.fields.length; item++) switch (window.page.tables.user.fields[item].name) {
        case 'picture':
            window.page.tables.user.fields[item][Infinite.Body.TD.handling()] = function (value) {
                let text = typeof value !== 'string'
                    || value.length === 0
                    ? Infinite.Plugin.Text.void()
                    : value;

                if (text === Infinite.Plugin.Text.void()) {
                    let node = document.createTextNode(text),
                        result = document.createElement('div');
                    result.className = 'result null';
                    result.appendChild(node);
                    return result;
                }

                let result = document.createElement('div'),
                    image = document.createElement('img');
                result.className = 'result';
                result.appendChild(image);
                image.setAttribute('src', text);

                return result;
            }
            break;
    }

    widgets.user = new Infinite();
    widgets.user.setOptionSetting(window.page.user.setting.user);
    widgets.user.setOptionStructure(window.page.tables.user.fields);
    widgets.user.setContainer(window.elements.content);
    widgets.user.setRequestUrl('/api/iam/user/read');
    widgets.user.setResponseKey('data');
    widgets.user.setResponseUnique('_key');
    widgets.user.getNotice().setTextEmpty(window.page.getTranslate('infinite.no_result'));
    widgets.user.request();
    widgets.user.addEventSelect(new Infinite.Event(Infinite.Event.always(), function () {
        window.choosed = this.getTR().getBody().getChecked();
        window.negotiateButtonStatus.call();
    }));

    wrapper.appendChild(widgets.user.out());

    if (window.page.checkPolicy('iam/user/action/create')) {
        let add = new Button();
        add.addStyle('flat');
        add.getIcon().set('person_add');
        add.setText(window.page.getTranslate('nav.buttons.add'));
        add.onClick(function () {
            window.location = '/iam/user/upsert';
        });
        window.buttons.push(add);
    }

    if (window.page.checkPolicy('iam/user/action/update')) {
        let edit = new Button();
        edit.addStyle('flat');
        edit.getIcon().set('edit');
        edit.setText(window.page.getTranslate('nav.buttons.edit'));
        edit.appendAttributes({
            'data-selected-min': 1,
            'data-selected-max': 1
        });
        edit.onClick(function () {
            window.location = '/iam/user/upsert/' + window.choosed[0];
        });
        window.buttons.push(edit);
    }

    widgets.delete = new Form();
    widgets.delete.getFormElement().setAttribute('data-form-name', 'delete');
    for (let item = 0; item < window.page.tables.user_delete.fields.length; item++) widgets.delete.addInput(window.page.tables.user_delete.fields[item]);

    widgets.modal = new Modal();

    let notice_node = document.createTextNode(window.page.getTranslate('modal.delete.notice')), notice_paragraph = document.createElement('p');
    notice_paragraph.appendChild(notice_node);
    widgets.modal.addContent(notice_paragraph);

    let instructions_node = document.createTextNode(window.page.getTranslate('modal.delete.instructions')), instructions_paragraph = document.createElement('p');
    instructions_paragraph.className = 'bolder';
    instructions_paragraph.appendChild(instructions_node);
    widgets.modal.addContent(instructions_paragraph);

    widgets.modal.addContent(widgets.delete.out());
    widgets.modal.setActionShow(function () {
        let modal_title = window.page.getTranslate('modal.delete.title'), modal_title_parsed = modal_title.replace(/\$0/, window.choosed.length);
        widgets.modal.setTitle(modal_title_parsed);
        widgets.delete.reset();
    });
    window.page.elements.push(widgets.modal.out());

    widgets.modal.buttons = {};
    widgets.modal.buttons.submit = new Button();
    widgets.modal.buttons.submit.addStyle('flat red');
    widgets.modal.buttons.submit.setText(window.page.getTranslate('buttons.delete'));
    widgets.modal.buttons.submit.onClick(function () {
        let preloader = widgets.user.getPreloader().status();
        if (preloader === true) return;

        let associative = widgets.delete.get();
        if (false === associative.hasOwnProperty('number') || associative.number != window.choosed.length) return widgets.delete.getManagerDanger().setObject({
            name: 'number',
            message: window.page.getTranslate('modal.delete.error')
        });

        this.getLoader().apply(window.page.getTranslate('buttons.loader'));

        window.requests = [];
        for (let x in window.choosed) {
            let xhr = new WXmlHttpRequest(),
                url = '/api/iam/user/delete'
                    + String.fromCharCode(47)
                    + encodeURIComponent(window.choosed[x])
                    + String.fromCharCode(63)
                    + 'timestamp'
                    + String.fromCharCode(61)
                    + Date.now();
            window.requests.push(xhr);
            xhr.setRequestUrl(url);
            xhr.setCallbackSuccess(function () {
                let parser = document.createElement('a');
                parser.href = this.getXHR().responseURL;

                let split = parser.pathname.split(String.fromCharCode(47)), key = split[split.length - 1] === 'delete' ? null : split[split.length - 1];
                widgets.user.getBody().removeTR(key);
                window.choosed = window.choosed.filter(function (element) {
                    return element != key;
                });
                window.negotiateButtonStatus.call();

            });
        }
        for (let item = 0; item < window.requests.length; item++)
            window.requests[item].request();

        delete window.requests;

        setTimeout(function (button, modal) {
            button.getLoader().remove();
            modal.hide();
        }, 2048, this, widgets.modal);
    });

    widgets.modal.buttons.cancel = new Button();
    widgets.modal.buttons.cancel.addStyle('flat');
    widgets.modal.buttons.cancel.setText(window.page.getTranslate('buttons.cancel'));
    widgets.modal.buttons.cancel.onClick(function () {
        widgets.modal.hide();
    });

    widgets.modal.container = document.createElement('div');
    widgets.modal.container.className = 'buttons-form';
    widgets.modal.container.appendChild(widgets.modal.buttons.submit.out());
    widgets.modal.container.appendChild(widgets.modal.buttons.cancel.out());

    widgets.modal.setBottom(widgets.modal.container);

    if (window.page.checkPolicy('iam/user/action/delete')) {
        let button = new Button();
        button.addStyle('flat');
        button.getIcon().set('delete');
        button.setText(window.page.getTranslate('nav.buttons.delete'));
        button.appendAttributes({
            'data-selected-min': 1
        });
        button.onClick(function () {
            widgets.modal.show();
        });
        window.buttons.push(button);
    }

    if (window.page.checkPolicy('iam/user/action/assignment') || window.page.checkPolicy('iam/user/action/policies')) {
        let assignment = new Button();
        assignment.addStyle('flat');
        assignment.getIcon().set('assignment');
        assignment.setText(window.page.getTranslate('nav.buttons.assignment'));
        assignment.appendAttributes({
            'data-selected-min': 1,
            'data-selected-max': 1
        });
        assignment.onClick(function () {
            window.location = '/iam/user/assignment/' + window.choosed[0];
        });
        window.buttons.push(assignment);
    }

    if (window.page.checkPolicy('iam/user/action/password')) {
        let reset = new Button();
        reset.addStyle('flat');
        reset.getIcon().set('sync');
        reset.setText(window.page.getTranslate('nav.buttons.password'));
        reset.appendAttributes({
            'data-selected-min': 1,
            'data-selected-max': 1
        });
        reset.onClick(function (event) {
            widgets.sidepanel.hide(event);
            widgets.sidepanel_password.toggle(event);
            let form_reset_password_request_url = '/api/iam/user/password'
                + String.fromCharCode(47)
                + encodeURIComponent(window.choosed[0])
                + String.fromCharCode(63)
                + 'timestamp'
                + String.fromCharCode(61)
                + Date.now();
            widgets.reset.setRequestUrl(form_reset_password_request_url);
        });
        window.buttons.push(reset);
    }

    for (let item = 0, action = widgets.nav.getColumn(14); item < window.buttons.length; item++)
        action.addContent(window.buttons[item].out());

    let infinite_setting = widgets.user.getSetting();
    if (infinite_setting !== null && window.page.checkPolicy('iam/user/action/save/widget/setting')) {
        let api = '/api/iam/user/save'
            + String.fromCharCode(63)
            + 'timestamp'
            + String.fromCharCode(61)
            + Date.now();
        widgets.sidepanel = new SidePanel();
        widgets.sidepanel.setTitle(window.page.getTranslate('sidepanel.title'));
        widgets.sidepanel.pushContent(infinite_setting.out());
        widgets.sidepanel.setActionShow(function () {
            window.elements.content.className = 'widget-infinite-enable-print pure-u-24-24 pure-u-lg-18-24 resize';
            window.setting.setText(window.page.getTranslate('nav.buttons.hide_settings'))
        });
        widgets.sidepanel.setActionHide(function () {
            window.elements.content.className = 'widget-infinite-enable-print pure-u-24-24 pure-u-lg-24-24 resize';
            window.setting.setText(window.page.getTranslate('nav.buttons.show_settings'))
        });

        infinite_setting.setRequestUrl(api);
        infinite_setting.setHardcode('widget', 'user');
        infinite_setting.setHardcode('application[basename]', window.page.getApplication());
        infinite_setting.setHardcode('module', window.page.getModule());
        infinite_setting.setHardcode('view', window.page.getView());

        window.elements.main.appendChild(widgets.sidepanel.out());
    }

    if (window.page.checkPolicy('iam/user/action/save/widget/setting')) {
        window.setting = new Button();
        window.setting.addStyle('flat');
        window.setting.getIcon().set('settings');
        window.setting.setText(window.page.getTranslate('nav.buttons.show_settings'));
        window.setting.onClick(function (event) {
            widgets.sidepanel_password.hide(event);
            widgets.sidepanel.toggle(event);
            window.setting.getText();
        });
        widgets.nav.getColumn(4).addContent(window.setting.out());
    }

    if (window.page.checkPolicy('iam/user/action/password')) {
        let container = document.createElement('div');
        container.id = 'wrapper-reset-password';

        widgets.reset = new Form();
        for (let item = 0; item < window.page.tables.password.fields.length; item++) widgets.reset.addInput(window.page.tables.password.fields[item]);

        for (let item = 0; item < window.page.tables.password.fields.length; item++) {
            if (window.page.tables.password.fields[item].name !== 'type') continue;

            let plugin = widgets.reset.findContainer(window.page.tables.password.fields[item].name).getPlugin(), list = plugin.getList();
            let column = widgets.reset.findContainer('password').getGrid();
            if (column instanceof HTMLElement) column.parentNode.style.display = 'none';

            for (let x in list) list[x].getInput().addEventListener('change', (function (event) {
                let id = Form.closestAttribute(event.target, 'data-form-radio-li-value');
                if (id === null) return;

                column = widgets.reset.findContainer('password').getGrid();
                if (column instanceof HTMLElement) column.parentNode.style.display = id === 'manual' ? 'block' : 'none';
            }).bind(list[x]));

            plugin.setSelected('auto');
        }

        container.appendChild(widgets.reset.out());

        window.password = new Button();
        window.password.setText(window.page.getTranslate('buttons.password'));
        window.password.getIcon().set('send');
        window.password.onClick(function () {
            let form_have_preloader = widgets.reset.getManager().status();
            if (form_have_preloader === true) return;

            this.getLoader().apply(window.page.getTranslate('buttons.loader'));

            widgets.reset.request(function () {
                window.password.getLoader().remove();
            });
        });
        container.appendChild(window.password.out());

        widgets.sidepanel_password = new SidePanel();
        widgets.sidepanel_password.setTitle('Reimposta Password');
        widgets.sidepanel_password.pushContent(container);
        widgets.sidepanel_password.setActionShow(function () {
            window.elements.content.className = 'widget-infinite-enable-print pure-u-24-24 pure-u-lg-18-24 resize';
        });
        widgets.sidepanel_password.setActionHide(function () {
            window.elements.content.className = 'widget-infinite-enable-print pure-u-24-24 pure-u-lg-24-24 resize';
        });

        window.elements.main.appendChild(widgets.sidepanel_password.out());
    }

    window.negotiateButtonStatus = function () {
        for (let item = 0; item < window.buttons.length; item++) {
            let button = window.buttons[item].out();
            let min = button.hasAttribute('data-selected-min') ? parseInt(button.getAttribute('data-selected-min')) : 0;
            let max = button.hasAttribute('data-selected-max') ? parseInt(button.getAttribute('data-selected-max')) : null;
            if (min === 0 && max === null) continue;
            if (min > window.choosed.length || max !== null && window.choosed.length > max) {
                button.setAttribute('disabled', true);
                button.classList.add('disabled');
            } else {
                button.classList.remove('disabled');
                button.removeAttribute('disabled');
            }
        }
    };

    window.negotiateButtonStatus.call();
})(window);
