
(function (window) {

    'use strict';

    window.Infinite.Plugin.Setting.Search.placeholder = function () {
        return window.page.getTranslate('infinite.search.placeholder');
    }
    window.Infinite.Plugin.Setting.Search.NotFound.text = function () {
        return window.page.getTranslate('infinite.search.not_found');
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
        let label = window.page.getTranslate('header.buttons.my_account'), account = burger.addItem(label, 'account_circle');
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
            try {
                if (false === response.hasOwnProperty('data')) throw 'Return URL not assigned';
                document.location.href = response.data;
            } catch (message) {
                console.log(message);
            }
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

    for (let item = 0; item < window.page.tables.application.fields.length; item++) switch (window.page.tables.application.fields[item].name) {
        case 'label':
            window.page.tables.application.fields[item][Infinite.Body.TD.handling()] = function (array) {
                let mapped = {},
                    label = Infinite.Plugin.Text.void();

                for (let item = 0; item < array.length; item++) {
                    if (!array[item].hasOwnProperty('label')
                        || !array[item].hasOwnProperty('language')) continue;

                    mapped[array[item].language] = array[item].label;
                }

                let check = [window.page.user.language, 'en'];
                for (let item = 0; item < check.length; item++) if (mapped.hasOwnProperty(check[item])) {
                    label = mapped[check[item]];
                    break;
                }

                let result = document.createElement('div'),
                    node = document.createTextNode(label);
                result.className = 'result';
                result.appendChild(node);
                if (label === Infinite.Plugin.Text.void()) result.classList.add('null');

                return result;
            }
            break;
        case 'icon':
            window.page.tables.application.fields[item][Infinite.Body.TD.handling()] = function (value) {
                let icon_text = typeof value !== 'string'
                    || value.length === 0
                    ? Infinite.Plugin.Text.void()
                    : value;

                if (icon_text === Infinite.Plugin.Text.void()) {
                    let icon_node = document.createTextNode(icon_text), result = document.createElement('div');
                    result.className = 'result null';
                    result.appendChild(icon_node);
                    return result;
                }

                let result = document.createElement('div'),
                    image = document.createElement('img');
                result.className = 'result';
                result.appendChild(image);
                image.setAttribute('src', icon_text);

                return result;
            }
            break;
    }

    widgets.application = new Infinite();
    widgets.application.setOptionSetting(window.page.user.setting.application);
    widgets.application.setOptionStructure(window.page.tables.application.fields);
    widgets.application.setContainer(window.elements.content);
    widgets.application.setRequestUrl('/api/sso/application/read');
    widgets.application.setResponseKey('data');
    widgets.application.setResponseUnique('_key');
    widgets.application.getNotice().setTextEmpty(window.page.getTranslate('infinite.no_result'));
    widgets.application.request();
    widgets.application.addEventSelect(new Infinite.Event(Infinite.Event.always(), function () {
        window.choosed = this.getTR().getBody().getChecked();
        window.negotiateButtonStatus.call();
    }));

    wrapper.appendChild(widgets.application.out());

    if (window.page.checkPolicy('sso/application/action/create')) {
        let add = new Button();
        add.addStyle('flat');
        add.getIcon().set('add');
        add.setText(window.page.getTranslate('nav.buttons.add'));
        add.onClick(function () {
            window.location = '/sso/application/upsert';
        });
        window.buttons.push(add);
    }

    if (window.page.checkPolicy('sso/application/action/update')) {
        let edit = new Button();
        edit.addStyle('flat');
        edit.getIcon().set('edit');
        edit.setText(window.page.getTranslate('nav.buttons.edit'));
        edit.appendAttributes({
            'data-selected-min': 1,
            'data-selected-max': 1
        });
        edit.onClick(function () {
            window.location = '/sso/application/upsert/' + window.choosed[0];
        });
        window.buttons.push(edit);
    }

    widgets.delete = new Form();
    widgets.delete.getFormElement().setAttribute('data-form-name', 'delete');
    for (let item = 0; item < window.page.tables.application_delete.fields.length; item++) widgets.delete.addInput(window.page.tables.application_delete.fields[item]);

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
        let preloader = widgets.application.getPreloader().status();
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
                url = '/api/sso/application/delete'
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
                widgets.application.getBody().removeTR(key);
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


    if (window.page.checkPolicy('sso/application/action/delete')) {
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

    for (let item = 0, action = widgets.nav.getColumn(14); item < window.buttons.length; item++)
        action.addContent(window.buttons[item].out());

    let infinite_setting = widgets.application.getSetting();

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
        infinite_setting.setHardcode('widget', 'application');
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
            widgets.sidepanel.toggle(event);
            window.setting.getText();
        });
        widgets.nav.getColumn(4).addContent(window.setting.out());
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
