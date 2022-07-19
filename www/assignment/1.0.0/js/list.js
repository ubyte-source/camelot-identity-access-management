(function (window) {

    'use strict';

    class AssignmentList {

        constructor() {
            this.elements = {};
            this.elements.groups = [];
            this.elements.policies = [];

            this.xhr = {
                url: null,
                hardcode: {},
                construct: new XMLHttpRequest(),
                events: {
                    // success: (function)
                }
            };
            this.xhr.construct.onreadystatechange = this.result.bind(this);

            let out = this.out();
            this.elements.preloader = new AssignmentPreloader(out);
        }

        getGroups() {
            return this.elements.groups;
        }
        getPolicies() {
            return this.elements.policies;
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
        getContainer() {
            if (this.elements.hasOwnProperty('container')) return this.elements.container;
            let list = this.getList();
            this.elements.container = document.createElement('div');
            this.elements.container.className = 'policies';
            this.elements.container.appendChild(list);
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
                    || structure[AssignmentLine.key()] !== key) continue;

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
                    || structure[AssignmentLine.key()] !== key) continue;

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
            this.elements.policies = [];
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

            if (json.data.hasOwnProperty('groups')) {
                json.data.groups.reverse();
                for (let item = 0; item < json.data.groups.length; item++)
                    this.addGroup(json.data.groups[item]);
            }

            let ghost = [];
            if (json.data.hasOwnProperty('policies')) {
                for (let item = 0; item < json.data.policies.length; item++)
                    this.addPolicy(json.data.policies[item]);

                for (let item = 0, policies = this.getPolicies(); item < policies.length; item++) {
                    policies[item].getPath().elaborate();
                    let structure = policies[item].getStructure();
                    if (structure === null
                        || !structure.hasOwnProperty(AssignmentLine.key())) continue;

                    ghost.push(structure[AssignmentLine.key()]);
                }
            }

            let success = this.getSuccess();
            if (typeof success === 'function') success.call(this);

            let groups = this.getGroups();

            for (let item = 0; item < groups.length; item++) {
                let policies = groups[item].getPolicies(), cross = ghost;

                groups[item].getPath().elaborate();

                for (let i = item + 1; i < groups.length; i++) {
                    let keys = groups[i].getAllPoliciesKey();
                    cross = cross.concat(keys);
                }

                for (let i = 0; i < policies.length; i++) {
                    let structure = policies[i].getStructure();

                    policies[i].getPath().elaborate();

                    if (structure === null
                        || !structure.hasOwnProperty(AssignmentLine.key())) continue;

                    if (cross.indexOf(structure[AssignmentLine.key()]) !== -1) policies[i].setGhost();
                }
            }
        }
        addGroup(structure) {
            let groups = this.getGroups(),
                group = new AssignmentGroup(structure, groups),
                list = this.getList();

            groups.push(group);
            list.insertBefore(group.out(), list.firstChild);

            return this;
        }
        addPolicy(structure) {
            let groups = this.getGroups(),
                policy = new AssignmentPolicy(structure, groups);

            if (false === structure.hasOwnProperty('group') || typeof structure.group !== 'number') {
                let list = this.getList();
                list.insertBefore(policy.out(), list.firstChild);
                this.getPolicies().push(policy);
            } else {
                let group = this.getGroup(structure.group);
                if (group !== null) group.pushPolicy(policy);
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
    }

    window.AssignmentList = AssignmentList;

})(window);
