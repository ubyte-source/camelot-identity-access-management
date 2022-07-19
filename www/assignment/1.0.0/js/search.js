(function (window) {

    'use strict';

    class AssignmentSearchActionNotFound {

        static text() {
            return 'developer\\assignment\\action\\search\\not_found';
        }

        constructor(search) {
            this.search = search;
            this.elements = {};
        }

        getSearch() {
            return this.search;
        }
        getContent() {
            if (this.elements.hasOwnProperty('content')) return this.elements.content;
            let span = this.getText();
            this.elements.content = document.createElement('li');
            this.elements.content.className = 'item not-found';
            this.elements.content.appendChild(span);
            return this.elements.content;
        }
        getText() {
            if (this.elements.hasOwnProperty('span')) return this.elements.span;
            let node = document.createTextNode(this.constructor.text())
            this.elements.span = document.createElement('span');
            this.elements.span.appendChild(node);
            return this.elements.span;
        }
        out() {
            return this.getContent();
        }
    }

    class AssignmentSearchAction {

        static icon() {
            return 'filter_list';
        }
        static placeholder() {
            return 'developer\\assignment\\search\\action\\placeholder';
        }

        constructor(search) {
            this.search = search;

            this.elements = {};
            this.elements.notfound = new AssignmentSearchActionNotFound(this);
        }

        getSearch() {
            return this.search;
        }
        getNotFound() {
            return this.elements.notfound;
        }
        getField() {
            if (this.elements.hasOwnProperty('field')) return this.elements.field;
            let input = this.getInput();
            this.elements.field = document.createElement('div');
            this.elements.field.className = 'field';
            this.elements.field.appendChild(input);
            return this.elements.field;
        }
        getInput() {
            if (this.elements.hasOwnProperty('input')) return this.elements.input;
            this.elements.input = document.createElement('input');
            this.elements.input.type = 'text';
            this.elements.input.setAttribute('data-handle-event', ':filter');
            this.elements.input.setAttribute('placeholder', this.constructor.placeholder());
            this.elements.input.addEventListener('input', this, false);
            return this.elements.input;
        }
        getContent() {
            if (this.elements.hasOwnProperty('content')) return this.elements.content;
            let icon = AssignmentSearch.getIcon(this.constructor.icon()), field = this.getField();
            this.elements.content = document.createElement('div');
            this.elements.content.className = 'search action';
            this.elements.content.appendChild(icon);
            this.elements.content.appendChild(field);
            return this.elements.content;
        }
        out() {
            return this.getContent();
        }
        handleEvent(event) {
            let attribute = AssignmentSearch.closestAttribute(event.target, 'data-handle-event');
            if (attribute === null) return;

            let attribute_split = attribute.split(/\s+/);
            for (let item = 0; item < attribute_split.length; item++) {
                let execute = attribute_split[item].split(':');
                if (execute.length !== 2) break;
                if (execute[0] === event.type || execute[0] === '') {
                    if (typeof this[execute[1]] !== 'function') continue;

                    this[execute[1]].call(this, event);
                }
            }
        }
        filter() {
            let notfound = this.getNotFound().out(), search = this.getSearch();

            search.getPreloader().showSpinner().show();

            AssignmentSearch.removeElementDOM(notfound);

            let value = this.getInput().value.toLowerCase(), list = [].concat(search.getGroups(), search.getPolicies());

            for (let item = 0; item < list.length; item++) list[item].out().classList.remove('hide');

            let hide = 0;
            for (let item = 0; item < list.length; item++) {
                if (list[item].getName().innerText.toLowerCase().indexOf(value) !== -1) continue;

                list[item].out().classList.add('hide');
                ++hide;
            }
            if (hide === list.length) search.getList().appendChild(notfound);

            search.getPreloader().hide();
        }
    }

    class AssignmentSearch {

        static drags() {
            return 'drag_indicator';
        }
        static allow() {
            return {
                check: 'lock_open',
                blank: 'lock'
            };
        }
        static reassignment() {
            return {
                check: 'public',
                blank: 'highlight_off'
            };
        }

        constructor(dropper, sortable) {
            this.elements = {};
            this.elements.action = new AssignmentSearchAction(this);
            this.elements.groups = [];
            this.elements.policies = [];

            this.elements.dropper = dropper;

            this.xhr = {
                url: null,
                hardcode: {},
                construct: new XMLHttpRequest(),
                events: {
                    // success: (function)
                }
            };
            this.xhr.construct.onreadystatechange = this.result.bind(this);

            let out = this.out(), list = this.getList(), option = Object.assign({}, sortable, {
                reference: this
            });

            this.sortable = new Sortable(list, option);
            this.elements.preloader = new AssignmentPreloader(out);
        }

        getAction() {
            return this.elements.action;
        }
        getGroups() {
            return this.elements.groups;
        }
        getPolicies() {
            return this.elements.policies;
        }
        getDropper() {
            return this.elements.dropper;
        }
        getPreloader() {
            return this.elements.preloader;
        }
        getSuccess() {
            if (this.xhr.events.hasOwnProperty('success')) return this.xhr.events.success;
            return null;
        }
        setSuccess(func) {
            this.xhr.events.success = func;
            return this;
        }
        getXHR() {
            return this.xhr.construct;
        }
        setRequestUrl(url) {
            this.xhr.url = url;
            return this;
        }
        getRequestUrl() {
            return this.xhr.url;
        }
        setHardcode(key, value) {
            this.xhr.hardcode[key] = value;
            return this;
        }
        getHardcode() {
            return this.xhr.hardcode;
        }
        getResult() {
            if (this.elements.hasOwnProperty('container')) return this.elements.container;
            let list = this.getList();
            this.elements.container = document.createElement('div');
            this.elements.container.className = 'result';
            this.elements.container.appendChild(list);
            return this.elements.container;
        }
        getContainer() {
            if (this.elements.hasOwnProperty('container')) return this.elements.container;
            let action = this.getAction().out(), result = this.getResult();
            this.elements.container = document.createElement('div');
            this.elements.container.className = 'policies researcher';
            this.elements.container.appendChild(action);
            this.elements.container.appendChild(result);
            return this.elements.container;
        }
        getList() {
            if (this.elements.hasOwnProperty('list')) return this.elements.list;
            this.elements.list = document.createElement('ul');
            this.elements.list.className = 'list';
            return this.elements.list;
        }
        getGroup(key) {
            let groups = this.getGroups();
            for (let item = 0; item < groups.length; item++) {
                let structure = groups[item].getStructure();
                if (structure === null
                    || !structure.hasOwnProperty(AssignmentLine.key())
                    || structure[AssignmentLine.key()] != key) continue;

                return groups[item];
            }
            return null;
        }
        removeAllGroups() {
            let groups = this.getGroups();
            for (let item = 0; item < groups.length; item++) {
                let container = groups[item].getContainer();
                this.constructor.removeElementDOM(container);
            }
            this.elements.groups = [];
            return this;
        }
        getPolicy(key) {
            let policies = this.getPolicies();
            for (let item = 0; item < policies.length; item++) {
                let structure = policies[item].getStructure();
                if (structure === null
                    || !structure.hasOwnProperty(AssignmentLine.key())
                    || structure[AssignmentLine.key()] != key) continue;

                return policies[item];
            }
            return null;
        }
        removeAllPolicies() {
            let policies = this.getPolicies();
            for (let item = 0; item < policies.length; item++) {
                let container = policies[item].getContainer();
                this.constructor.removeElementDOM(container);
            }
            this.elements.groups = [];
            return this;
        }
        request() {
            let url = this.getRequestUrl();
            if (url === null) return;

            let xhr = this.getXHR();
            xhr.open('POST', url, !0);

            this.getPreloader().showSpinner().show();

            let form_data = new FormData(), hardcode = this.getHardcode();
            for (let i in hardcode) form_data.append(i, hardcode[i]);

            xhr.send(form_data);
        }
        result() {
            let xhr = this.getXHR();
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

            if (json.status !== true
                || !json.hasOwnProperty('data')) return;

            this.removeAllGroups();
            this.removeAllPolicies();

            if (json.data.hasOwnProperty('groups'))
                for (let item = 0; item < json.data.groups.length; item++)
                    this.addGroup(json.data.groups[item]);

            if (json.data.hasOwnProperty('policies'))
                for (let item = 0; item < json.data.policies.length; item++)
                    this.addPolicy(json.data.policies[item]);

            let success = this.getSuccess();
            if (typeof success === 'function') success.call(this);

            for (let item = 0, groups = this.getGroups(); item < groups.length; item++) {
                groups[item].getPath().elaborate();
                for (let i = 0, policies = groups[item].getPolicies(); i < policies.length; i++) policies[i].getPath().elaborate();
            }
        }
        addGroup(structure) {
            let groups = this.getGroups(), group = new AssignmentGroup(structure, groups), list = this.getList();
            group.getAssign().setAction(this.constructor.drags(), true);
            groups.push(group);
            list.insertBefore(group.out(), list.firstChild);
            return this;
        }
        createPolicy(structure, assign) {
            let groups = this.getGroups(), policy = new AssignmentPolicy(structure, groups), action = policy.getAction();
            if (assign === true) policy.getAssign().setAction(this.constructor.drags(), true);
            action.addAction('reassignment', this.constructor.reassignment(), structure.reassignment, false);
            action.addAction('allow', this.constructor.allow(), structure.allow, false);
            return policy;
        }
        addPolicy(structure) {
            let exists = this.getPolicy(structure[AssignmentLine.key()]);
            if (exists === null) {
                let policy = this.createPolicy(structure, true);
                this.getList().appendChild(policy.out());
                this.getPolicies().push(policy);
            }

            if (!structure.hasOwnProperty('path')
                || false === Array.isArray(structure.path)
                || structure.path.length === 0) return this;

            for (let item = 0; item < structure.path.length; item++) {
                let group = this.getGroup(structure.path[item]);
                if (group !== null) {
                    let policy = this.createPolicy(structure);
                    group.pushPolicy(policy);
                }
            }

            return this;
        }
        out() {
            return this.getContainer();
        }
        static closestAttribute(target, attribute, html) {
            if (typeof attribute === 'undefined'
                || !attribute.length) return null;

            let result = null, element = target;

            do {
                let tagname = element.tagName.toLowerCase();
                if (tagname === 'body') return null;

                result = element.getAttribute(attribute);
                if (result !== null) {
                    result = result.toString();
                    if (result.length) break;
                }

                element = element.parentNode;
            } while (element !== null
                || typeof element === 'undefined');

            if (typeof html === 'undefined'
                || html !== true) return result;

            return element;
        }
        static removeElementDOM(element) {
            let parent = element === null || typeof element === 'undefined' || typeof element.parentNode === 'undefined' ? null : element.parentNode;
            if (parent === null) return false;
            parent.removeChild(element);
            return true;
        }
        static getIcon(name) {
            let icon = document.createElement('i');
            icon.className = 'material-icons';
            icon.innerText = name;
            return icon;
        }
    }

    window.AssignmentSearch = AssignmentSearch;
    window.AssignmentSearchAction = AssignmentSearchAction;
    window.AssignmentSearchActionNotFound = AssignmentSearchActionNotFound;

})(window);
