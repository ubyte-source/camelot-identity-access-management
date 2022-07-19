(function (window) {

    'use strict';

    AssignmentSearchAction.placeholder = function () {
        return window.page.getTranslate('search.placeholder');
    }

    AssignmentSearchActionNotFound.text = function () {
        return window.page.getTranslate('action.search.not_found');
    }

    GroupDetail.content = function () {
        return {
            owner: window.page.getTranslate('detail.owner'),
            group: window.page.getTranslate('detail.group'),
            share: window.page.getTranslate('detail.admin')
        };
    }
    GroupDetailLabel.initial = function () {
        return [
            'firstname',
            'lastname',
            'name'
        ];
    }
    GroupDetailLabel.key = function () {
        return 'email';
    }
    let pathname = window.location.pathname.split(String.fromCharCode(47)), widgets = window.page.getWidgets();
    window.reference = pathname.slice(4);

    window.elements = {};
    window.elements.content = document.createElement('div');
    window.elements.content.id = 'content';

    window.elements.main = document.createElement('div');
    window.elements.main.id = 'main';
    window.page.addHTMLElement(window.elements.main);
    window.elements.main.appendChild(window.elements.content);

    window.elements.wrapper = document.createElement('div');
    window.elements.wrapper.className = 'wrapper';
    window.elements.content.appendChild(window.elements.wrapper);

    window.elements.grid = document.createElement('div');
    window.elements.grid.className = 'pure-u-22-24 pure-u-lg-24-24 resize';

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

    let back = '/iam/group/read';
    widgets.nav = new Nav();
    widgets.nav.setBack(back);
    widgets.nav.setReturnButton('arrow_back');
    widgets.nav.setTitle(window.page.getTranslate('nav.title'));

    window.elements.main.appendChild(widgets.nav.out());

    widgets.group_detail = new GroupDetail();
    let url = '/api/iam/group/detail'
        + String.fromCharCode(47)
        + encodeURIComponent(window.reference[0])
        + String.fromCharCode(63)
        + 'timestamp'
        + String.fromCharCode(61)
        + Date.now();
    widgets.group_detail.setUrl(url);
    window.elements.grid.appendChild(widgets.group_detail.out());

    if (window.page.checkPolicy('iam/group/action/assignment')) {
        let api = '/api/iam/group/assignment'
            + String.fromCharCode(47)
            + encodeURIComponent(window.reference[0])
            + String.fromCharCode(63)
            + 'timestamp'
            + String.fromCharCode(61)
            + Date.now()
            + String.fromCharCode(63)
            + 'timestamp'
            + String.fromCharCode(61)
            + Date.now();

        Dropper.text = function () {
            return window.page.getTranslate('dropper.text');
        }

        Dropper.drop = function () {
            return window.page.getTranslate('dropper.drop');
        }

        widgets.dropper = new Dropper({
            group: {
                name: 'shared'
            },
            onAdd: function (event) {
                let data = AssignmentLine.data(), id = event.item.getAttribute(data), text = Dropper.text();

                Dropper.removeElementDOM(event.item);

                this.options.reference.setText(text);
                this.options.reference.setHardcode(AssignmentLine.id(), id);
                this.options.reference.request();
            }
        });
        widgets.dropper.setRequestUrl(api);

        window.elements.grid.appendChild(widgets.dropper.out());
    }

    window.action = {};
    window.action.policy = {};
    window.action.policy.reassignment = {
        icons: AssignmentSearch.reassignment(),
        function: function (event) {
            let preloader = this.getPreloader();
            if (preloader.status()) return;

            widgets.list.getPreloader().showSpinner().show();
            this.getException().hide();
            preloader.show();

            let id = AssignmentLine.closestAttribute(event.target, AssignmentLine.data()), key = id.split(String.fromCharCode(47)).pop(), input = this.getInput();
            let xhr = new XMLHttpRequest(),
                api = '/api/iam/policy/set'
                    + String.fromCharCode(47)
                    + encodeURIComponent(key)
                    + String.fromCharCode(63)
                    + 'timestamp'
                    + String.fromCharCode(61)
                    + Date.now(), data = new FormData();

            data.append(AssignmentLine.id(), 'Group' + '/' + window.reference[0]);
            data.append('fields[reassignment]', input.checked);

            xhr.open('POST', api, !0);
            xhr.onreadystatechange = function () {
                if (XMLHttpRequest.DONE !== xhr.readyState
                    || 200 !== xhr.status) return;

                this.getPreloader().hide();
                let json;
                try {
                    json = JSON.parse(xhr.responseText);
                } catch (message) {
                    json = {
                        'status': false,
                        'notice': message
                    };
                }

                if (!json.hasOwnProperty('status') || json.status != true) {
                    let text = json.hasOwnProperty('notice') ? json.notice : window.page.getTranslate('tooltip.try_again');
                    this.getException().show(text);
                    return;
                }

                widgets.list.request();
            };

            xhr.onreadystatechange = xhr.onreadystatechange.bind(this);
            xhr.send(data);
        }
    };

    window.action.policy.allow = {
        icons: AssignmentSearch.allow(),
        function: function (event) {
            let preloader = this.getPreloader();
            if (preloader.status()) return;

            widgets.list.getPreloader().showSpinner().show();
            this.getException().hide();
            preloader.show();

            let id = AssignmentLine.closestAttribute(event.target, AssignmentLine.data()), key = id.split(String.fromCharCode(47)).pop(), input = this.getInput();
            let xhr = new XMLHttpRequest(),
                api = '/api/iam/policy/set'
                    + String.fromCharCode(47)
                    + encodeURIComponent(key)
                    + String.fromCharCode(63)
                    + 'timestamp'
                    + String.fromCharCode(61)
                    + Date.now(), data = new FormData();

            data.append(AssignmentLine.id(), 'Group' + '/' + window.reference[0]);
            data.append('fields[allow]', input.checked);

            xhr.open('POST', api, !0);
            xhr.onreadystatechange = function () {
                if (XMLHttpRequest.DONE !== xhr.readyState
                    || 200 !== xhr.status) return;

                this.getPreloader().hide();

                let json;
                try {
                    json = JSON.parse(xhr.responseText);
                } catch (message) {
                    json = {
                        'status': false,
                        'notice': message
                    };
                }

                if (!json.hasOwnProperty('status') || json.status != true) {
                    let text = json.hasOwnProperty('notice') ? json.notice : window.page.getTranslate('tooltip.try_again');
                    this.getException().show(text);
                    return;
                }

                widgets.list.request();
            };

            xhr.onreadystatechange = xhr.onreadystatechange.bind(this);
            xhr.send(data);
        }
    };

    window.action.detach = {
        icon: 'close',
        function: function (event) {
            let preloader = this.getPreloader();
            if (preloader.status()) return;

            widgets.list.getPreloader().showSpinner().show();
            this.getException().hide();
            preloader.show();

            let id = AssignmentLine.closestAttribute(event.target, AssignmentLine.data());
            let xhr = new XMLHttpRequest(),
                detach_url = '/api/iam/group/detach'
                    + String.fromCharCode(47)
                    + encodeURIComponent(window.reference[0])
                    + String.fromCharCode(63)
                    + 'timestamp'
                    + String.fromCharCode(61)
                    + Date.now(), data = new FormData();

            data.append('_id', id);

            xhr.open('POST', detach_url, !0);
            xhr.onreadystatechange = function () {
                if (XMLHttpRequest.DONE !== xhr.readyState
                    || 200 !== xhr.status) return;

                this.getPreloader().hide();

                let json;
                try {
                    json = JSON.parse(xhr.responseText);
                } catch (message) {
                    json = {
                        'status': false,
                        'notice': message
                    };
                }

                if (!json.hasOwnProperty('status') || json.status != true) {
                    let text = json.hasOwnProperty('notice') ? json.notice : window.page.getTranslate('tooltip.try_again');
                    this.getException().show(text);
                    return;
                }

                widgets.list.request();
            };

            xhr.onreadystatechange = xhr.onreadystatechange.bind(this);
            xhr.send(data);
        }
    }

    if (window.page.checkPolicy('iam/group/action/policies')) {
        let api = '/api/iam/group/policies'
            + String.fromCharCode(47)
            + encodeURIComponent(window.reference[0])
            + String.fromCharCode(63)
            + 'timestamp'
            + String.fromCharCode(61)
            + Date.now();
        widgets.list = new AssignmentList();
        widgets.list.setRequestUrl(api);
        widgets.list.setSuccess(function () {
            let policies = this.getPolicies(), groups = this.getGroups();

            for (let item = 0; item < policies.length; item++) {
                let structure = policies[item].getStructure(), manager = structure.hasOwnProperty('manager') && structure.manager === true;
                if (manager) continue;

                for (let name in window.action.policy) policies[item].getAction().addAction(name, window.action.policy[name].icons, structure[name], widgets.sidepanel.status(), window.action.policy[name].function);
                policies[item].getAssign().setAction(window.action.detach.icon, widgets.sidepanel.status(), window.action.detach.function);
            }

            for (let item = 0; item < groups.length; item++) {
                let policies = groups[item].getPolicies(), structure = groups[item].getStructure(), manager = !structure.hasOwnProperty('manager') || structure.manager !== true;
                if (manager) groups[item].getAssign().setAction(window.action.detach.icon, widgets.sidepanel.status(), window.action.detach.function);

                for (let i = 0; i < policies.length; i++) {
                    let structure = policies[i].getStructure(), manager = !structure.hasOwnProperty('manager') || structure.manager !== true;
                    if (manager) for (let name in window.action.policy) policies[i].getAction().addAction(name, window.action.policy[name].icons, structure[name], widgets.sidepanel.status(), window.action.policy[name].function);
                }
            }
        });
        window.elements.grid.appendChild(widgets.list.out());
    }

    if (window.page.checkPolicy('iam/group/action/assignment')) {
        window.edit = new Button();
        window.edit.addStyle('flat');
        window.edit.getIcon().set('edit');
        window.edit.setText(window.page.getTranslate('sidepanel.edit_on'));
        window.edit.onClick(function (event) {
            widgets.sidepanel.toggle(event);
            window.edit.getText();
            let policies = widgets.list.getPolicies(), method = !widgets.sidepanel.status() ? 'disable' : 'enable';
            for (let item = 0; item < policies.length; item++) {
                let actions = policies[item].getAction().getActions();

                policies[item].getAssign()[method](event);

                for (let name in actions) {
                    if (!window.action.policy.hasOwnProperty(name)) continue;
                    actions[name][method](event);
                }
            }

            let groups = widgets.list.getGroups();
            for (let item = 0; item < groups.length; item++) {
                let policies = groups[item].getPolicies();

                groups[item].getAssign()[method](event);

                for (let i = 0; i < policies.length; i++) {
                    let actions = policies[i].getAction().getActions(), ghost = policies[i].getGhost();
                    if (ghost && method === 'enable') continue;

                    for (let name in actions) {
                        if (!window.action.policy.hasOwnProperty(name)) continue;
                        actions[name][method](event);
                    }
                }
            }
        });

        widgets.nav.getColumn(4).addContent(window.edit.out());

        let api = '/api/iam/user/reassignment'
            + String.fromCharCode(63)
            + 'timestamp'
            + String.fromCharCode(61)
            + Date.now();
        widgets.search = new AssignmentSearch(widgets.dropper, {
            group: {
                name: 'shared',
                pull: true,
                put: false
            },
            handle: '.assign',
            sort: false,
            removeCloneOnHide: true,
            onStart() {
                let status = this.options.reference.getDropper().getPreloader().status();
                if (status === false) {
                    let text = Dropper.drop();
                    this.options.reference.getDropper().setText(text);
                }
            }
        });
        widgets.search.setRequestUrl(api);

        widgets.sidepanel = new SidePanel();
        widgets.sidepanel.setTitle(window.page.getTranslate('sidepanel.title'));
        widgets.sidepanel.pushContent(widgets.search.out());
        widgets.sidepanel.setActionShow(function (event) {
            let text = window.page.getTranslate('sidepanel.edit_off');
            window.elements.grid.className = 'pure-u-22-24 pure-u-lg-18-24 resize';
            window.edit.setText(text);
            widgets.dropper.show(event);
        });
        widgets.sidepanel.setActionHide(function (event) {
            let text = window.page.getTranslate('sidepanel.edit_on');
            window.elements.grid.className = 'pure-u-22-24 pure-u-lg-24-24 resize';
            window.edit.setText(text);
            widgets.dropper.hide(event);
        });

        window.elements.main.appendChild(widgets.sidepanel.out());
    }

    if (window.page.checkPolicy('iam/group/action/assignment')) widgets.dropper.setSuccess(function () {
        widgets.list.request();
    });

    widgets.group_detail.request();
    widgets.list.request();

    if (window.page.checkPolicy('iam/group/action/assignment')) widgets.search.request();

})(window);
