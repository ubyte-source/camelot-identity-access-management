(function (window) {

    'use strict';

    class AssignmentLine {
        static id() {
            return '_id';
        }
        static key() {
            return '_key';
        }
        static data() {
            return 'data-element-id';
        }
        static toggle() {
            return {
                check: 'expand_more',
                blank: 'expand_less'
            };
        }

        constructor(structure, groups) {
            this.structure = structure;

            this.elements = {};
            this.elements.path = new AssignmentPath(this);
            this.elements.groups = typeof groups === 'undefined' ? null : groups;
            this.elements.assign = new AssignmentAssign(this);
            this.elements.action = new AssignmentAction(this);

            var structure = this.getStructure();
            if (structure !== null
                && structure.hasOwnProperty('name')
                && structure.name !== null
                && structure.name.length > 0) this.setName(structure.name);

            if (structure !== null
                && structure.hasOwnProperty('description')
                && structure.description !== null
                && structure.description.length > 0) this.setDescription(structure.description);

            var out = this.out();
            this.elements.preloader = new AssignmentPreloader(out);
        }

        getStructure() {
            return this.structure;
        }
        getGroups() {
            return this.elements.groups;
        }
        getPath() {
            return this.elements.path;
        }
        getAssign() {
            return this.elements.assign;
        }
        getAction() {
            return this.elements.action;
        }
        getPreloader() {
            return this.elements.preloader;
        }
        getIcon(name) {
            var icon = document.createElement('i');
            icon.className = 'material-icons';
            icon.innerText = name;
            return icon;
        }
        setName(text) {
            var name = this.getName(), node = document.createTextNode(text);
            name.innerText = '';
            name.appendChild(node);
            return this;
        }
        getName() {
            if (this.elements.hasOwnProperty('name')) return this.elements.name;
            this.elements.name = document.createElement('span');
            this.elements.name.className = 'ellipsis';
            return this.elements.name;
        }
        setDescription(text) {
            var description = this.getDescription(), node = document.createTextNode(text);
            description.innerText = '';
            description.appendChild(node);
            return this;
        }
        getDescription() {
            if (this.elements.hasOwnProperty('description')) return this.elements.description;
            this.elements.description = document.createElement('p');
            this.elements.description.className = 'description';
            return this.elements.description;
        }
        getInfo() {
            if (this.elements.hasOwnProperty('info')) return this.elements.info;
            var description = this.getDescription(), expand = this.getAction().addAction('toggle', this.constructor.toggle(), false, true);

            var icon = expand.show().out();
            icon.classList.add('expand');
            icon.addEventListener('click', this, false);

            this.elements.info = document.createElement('div');
            this.elements.info.className = 'info';
            this.elements.info.appendChild(description);
            return this.elements.info;
        }
        getHeader() {
            if (this.elements.hasOwnProperty('header')) return this.elements.header;
            var name = this.getName();
            this.elements.header = document.createElement('div');
            this.elements.header.className = 'header';
            this.elements.header.appendChild(name);
            return this.elements.header;
        }
        getMain() {
            if (this.elements.hasOwnProperty('main')) return this.elements.main;
            var header = this.getHeader(), action = this.getAction();
            this.elements.main = document.createElement('div');
            this.elements.main.className = 'main';
            this.elements.main.appendChild(header);
            this.elements.main.appendChild(action.out());
            return this.elements.main;
        }
        getContainer() {
            if (this.elements.hasOwnProperty('container')) return this.elements.container;
            var structure = this.getStructure(), main = this.getMain(), info = this.getInfo();
            this.elements.container = document.createElement('li');
            this.elements.container.className = 'item';
            this.elements.container.appendChild(main);
            this.elements.container.appendChild(info);
            if (structure === null
                || !structure.hasOwnProperty(this.constructor.id())) return this.elements.container;

            this.elements.container.setAttribute(this.constructor.data(), structure[this.constructor.id()]);
            return this.elements.container;
        }
        findGroup(key) {
            var groups = this.getGroups();
            for (var item = 0; item < groups.length; item++) {
                var structure = groups[item].getStructure();
                if (structure === null
                    || !structure.hasOwnProperty(this.constructor.key())
                    || key != structure[this.constructor.key()]) continue;

                return groups[item];
            }
            return null;
        }
        removeElementDOM(element) {
            var parent = element === null || typeof element === 'undefined' ? null : element.parentNode;
            if (parent === null) return this;
            parent.removeChild(element);
            return this;
        }
        out() {
            return this.getContainer();
        }
		handleEvent(event) {
            let attribute = this.constructor.closestAttribute(event.target, 'data-handle-event');
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
        click() {
            this.getInfo().classList.toggle('on');
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
    }

    window.AssignmentLine = AssignmentLine;

})(window);
