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
        back = '/iam/user/read';
    widgets.nav = new Nav();
    widgets.nav.setBack(back);
    widgets.nav.setReturnButton('arrow_back');
    widgets.nav.setTitle(title);

    window.elements.main.appendChild(widgets.nav.out());

    widgets.form = new Form();
    widgets.flatpickr = {};

    for (let item = 0; item < window.page.tables.user.fields.length; item++) {
        if (window.page.tables.user.fields[item].name === 'type' && !window.page.checkPolicy('iam/privilege/user/type')) continue;
        if (window.page.tables.user.fields[item].name === 'picture') window.page.tables.user.fields[item].construct = function () {
            this.background = 'background-image';
            this.getWrapper = function () {
                if (this.elements.hasOwnProperty('wrapper')) return this.elements.wrapper;
                this.elements.wrapper = document.createElement('div');
                this.elements.wrapper.className = 'image active';
                return this.elements.wrapper;
            }
            this.setImage = function (src) {
                this.getPreloader().hide();
                if (typeof src !== 'string') return this;
                this.hideImagePlaceholder();
                this.getWrapper().style[this.background] = 'url("' + src + '")';
                return this;
            };
            this.reset = function () {
                this.getInput().value = '';
                this.showImagePlaceholder();
                this.getWrapper().style[this.background] = null;
                return this;
            };
            this.change = function () {
                this.getPreloader().showSpinner().show();
                let content = this.getInput().files[0], type = content.type.split(String.fromCharCode(47));
                if (typeof content === 'undefined'
                    || !type.hasOwnProperty(0) || type[0] !== 'image') return this;

                let src = window.URL.createObjectURL(content);
                this.setImage(src);
                return this;
            };
            this.dialog = function () {
                let preloader = this.getPreloader().status();
                if (preloader !== true) this.getInput().click();
                return this;
            };
            this.getImagePlaceholder = function () {
                if (this.elements.hasOwnProperty('placeholder')) return this.elements.placeholder;
                this.elements.placeholder = Form.getIcon('material-icons person');
                this.elements.placeholder.classList.add('image-placeholder');
                return this.elements.placeholder;
            };
            this.showImagePlaceholder = function () {
                this.getImagePlaceholder().classList.remove('hide');
                return this;
            };
            this.hideImagePlaceholder = function () {
                this.getImagePlaceholder().classList.add('hide');
                return this;
            };

            let preloader = this.getPreloader();
            preloader.setEventShow(function () {
                this.getContainer().classList.remove('active');
                return this;
            });
            preloader.setEventHide(function () {
                this.getContainer().classList.add('active');
                return this;
            });

            let input = this.getInput();

            input.setAttribute('data-handle-event', ':change');
            input.addEventListener('change', this, false);

            let wrapper = this.getWrapper(), image_placeholder = this.getImagePlaceholder();
            let wrapper_dialog = document.createElement('div'), wrapper_dialog_icon = Form.getIcon('material-icons cloud_upload');

            wrapper_dialog.className = 'dialog';
            wrapper_dialog.appendChild(wrapper_dialog_icon);
            wrapper.appendChild(image_placeholder);
            wrapper.appendChild(wrapper_dialog);
            wrapper.setAttribute('data-handle-event', ':dialog');
            wrapper.addEventListener('click', this, false);

            this.getContent().appendChild(wrapper);
        };

        let plugin = widgets.form.addInput(window.page.tables.user.fields[item]);
        if (plugin === null) continue;

        let input = plugin.getInput(), input_type = input.getAttribute('type');
        if (input_type !== 'datetime-local') continue;

        let container_pattern = !window.page.tables.user.fields[item].hasOwnProperty('patterns')
            || !window.page.tables.user.fields[item].patterns.hasOwnProperty(0)
            || !window.page.tables.user.fields[item].patterns[0].hasOwnProperty('from')
            || typeof window.page.tables.user.fields[item].patterns[0].from !== 'string'
            ? 'Y-m-d'
            : window.page.tables.user.fields[item].patterns[0].from;

        input.type = 'text';

        widgets.flatpickr[window.page.tables.user.fields[item].name] = {
            locale: window.page.user.language !== 'en' ? window.page.user.language : 'default',
            dateFormat: container_pattern
        };

        let interval = setInterval(function (plugin, input, configuration) {
            if (input.parentNode === null) return;
            plugin.getContainer().options.flatpickr = window.flatpickr(input, configuration);
            clearInterval(interval);
        }, 500, plugin, input, widgets.flatpickr[window.page.tables.user.fields[item].name]);
    }

    window.elements.content.appendChild(widgets.form.out());

    let buttons_form = document.createElement('div');
    buttons_form.className = 'buttons-form';
    window.elements.content.appendChild(buttons_form);

    let submit = new Button(), icon = window.reference.length === 0 ? 'person_add' : 'save';
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

    let create_api = '/api/iam/user/create'
        + String.fromCharCode(63)
        + 'timestamp'
        + String.fromCharCode(61)
        + Date.now();
    widgets.form.setRequestUrl(create_api);

    if (window.reference.length === 0) return;

    let update_api = '/api/iam/user/update'
        + String.fromCharCode(47)
        + encodeURIComponent(window.reference[0])
        + String.fromCharCode(63)
        + 'timestamp'
        + String.fromCharCode(61)
        + Date.now();
    widgets.form.setRequestUrl(update_api);
    widgets.form.getManager().show(true);

    let main = new WXmlHttpRequest(),
        detail_api = '/api/iam/user/detail'
            + String.fromCharCode(47)
            + encodeURIComponent(window.reference[0])
            + String.fromCharCode(63)
            + 'timestamp'
            + String.fromCharCode(61)
            + Date.now();
    main.setRequestUrl(detail_api);
    main.setCallbackSuccess(function (response) {
        for (let item in response.data) switch (item) {
            case 'picture':
                let container = widgets.form.findContainer(item);
                if (container === null) continue;

                let plugin = container.getPlugin();
                if (typeof plugin.setImage !== 'function') continue;
                plugin.setImage(response.data[item]);
                break;
            default:
                widgets.form.set(item, response.data[item]);
        }
    });
    main.request();
})(window);
