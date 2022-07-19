(function (window) {

    'use strict';

    class AssignmentActionCheckboxException {
        static effect() {
            return 'bug';
        }
        static material() {
            return 'bug_report';
        }

        constructor(checkbox) {
            this.checkbox = checkbox;

            var icon = this.getCheckbox().getIcon();

            this.elements = {};
            this.elements.tooltip = new AssignmentTooltip(icon);
        }

        getCheckbox() {
            return this.checkbox;
        }
        getTooltip() {
            return this.elements.tooltip;
        }
        show(text) {
            var checkbox = this.getCheckbox(), input = checkbox.getInput();

            checkbox.setInput(!input.checked);

            checkbox.setIcon(this.constructor.material());
            checkbox.out().classList.add(this.constructor.effect());

            if (typeof text === 'string') this.getTooltip().setText(text).active();

            return this;
        }
        hide() {
            var checkbox = this.getCheckbox(), input = checkbox.getInput();

            checkbox.out().classList.remove(this.constructor.effect());
            checkbox.setInput(input.checked);

            return this;
        }
        status() {
            return this.getReference().out().classList.contains(this.constructor.effect());
        }
    }

    class AssignmentActionCheckboxPreloader {
        static effect() {
            return 'spin';
        }
        static material() {
            return 'autorenew';
        }

        constructor(checkbox) {
            this.checkbox = checkbox;
        }

        getCheckbox() {
            return this.checkbox;
        }
        show() {
            var checkbox = this.getCheckbox();

            checkbox.out().classList.add(this.constructor.effect());
            checkbox.setIcon(this.constructor.material());

            return this;
        }
        hide() {
            var checkbox = this.getCheckbox(), input = checkbox.getInput();

            checkbox.out().classList.remove(this.constructor.effect());
            checkbox.setInput(input.checked);

            return this;
        }
        status() {
            return this.getCheckbox().out().classList.contains(this.constructor.effect());
        }
    }

    class AssignmentActionCheckbox {
        static show() {
            return 'show';
        }
        static disabled() {
            return 'disabled';
        }
        static icons() {
            return {
                check: 'radio_button_checked',
                blank: 'radio_button_unchecked'
            };
        }

        constructor(name) {
            this.name = name;

            this.events = {};

            this.elements = {};
            this.elements.exception = new AssignmentActionCheckboxException(this);
            this.elements.preloader = new AssignmentActionCheckboxPreloader(this);

            this.icons = this.constructor.icons();
        }

        getName() {
            return this.name;
        }
        getPreloader() {
            return this.elements.preloader;
        }
        getException() {
            return this.elements.exception;
        }
        getIcons() {
            return this.icons;
        }
        setIcons(object) {
            this.icons = object;
            return this;
        }
        getEvent() {
            if (this.events.hasOwnProperty('function')) return this.events.function;
            return null;
        }
        setEvent(func) {
            this.events.function = func;
            return this;
        }
        getIcon() {
            if (this.elements.hasOwnProperty('icon')) return this.elements.icon;
            var icons = this.constructor.icons(), node = document.createTextNode(icons.blank);
            this.elements.icon = document.createElement('i');
            this.elements.icon.className = 'material-icons';
            this.elements.icon.appendChild(node);
            this.elements.icon.setAttribute('data-handle-event', 'click:click');
            this.elements.icon.addEventListener('click', this, false);
            return this.elements.icon;
        }
        setIcon(text) {
            var icon = this.getIcon(), node = document.createTextNode(text);
            icon.innerText = '';
            icon.appendChild(node);
            return this;
        }
        getInput() {
            if (this.elements.hasOwnProperty('input')) return this.elements.input;
            this.elements.input = document.createElement('input');
            this.elements.input.type = 'checkbox';
            this.elements.input.name = this.getName();
            this.elements.input.setAttribute('data-handle-event', ':change');
            this.elements.input.addEventListener('change', this, false);
            return this.elements.input;
        }
        setInput(status) {
            var input = this.getInput(), trigger = document.createEvent('HTMLEvents');
            trigger.initEvent('change', true, false);
            input.checked = status === true;
            input.dispatchEvent(trigger);
            return this;
        }
        hide() {
            var show = this.constructor.show();
            this.getIcon().classList.remove(show);
            return this;
        }
        show() {
            var show = this.constructor.show();
            this.getIcon().classList.add(show);
            return this;
        }
        status() {
            var show = this.constructor.show();
            return this.getIcon().classList.contains(show);
        }
        disable() {
            var disabled = this.constructor.disabled();
            this.getIcon().classList.add(disabled);
            return this;
        }
        enable() {
            var disabled = this.constructor.disabled();
            this.getIcon().classList.remove(disabled);
            return this;
        }
        out() {
            return this.getIcon();
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
        click(event) {
            var disabled = this.constructor.disabled();
            if (this.getIcon().classList.contains(disabled)) return;

            var input = this.getInput(), status = input.checked === false;
            this.setInput(status);

            var func = this.getEvent();
            if (typeof func !== 'function') return;

            func.call(this, event);
        }
        change() {
            var input = this.getInput(), icons = this.getIcons(), icon = input.checked === true ? icons.check : icons.blank;
            this.setIcon(icon);
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

    class AssignmentAction {
        constructor(reference) {
            this.reference = reference;

            this.elements = {};
            this.elements.actions = {};
        }

        getReference() {
            return this.reference;
        }
        getActions() {
            return this.elements.actions;
        }
        addActionClass(action) {
            if (action instanceof AssignmentActionCheckbox === false) return null;

            var actions = this.getActions(), name = action.getName();
            if (actions.hasOwnProperty(name)) return null;

            actions[name] = action;
            actions[name].show();

            var container = this.getContainer(), input = action.getInput(), icon = action.getIcon();
            container.appendChild(input);
            container.insertBefore(icon, container.firstChild);

            this.resize();

            return this;
        }
        addAction(name, icons, status, enable, event) {
            var actions = this.getActions();
            if (actions.hasOwnProperty(name)) return actions[name];

            var checkbox = new AssignmentActionCheckbox(name);

            this.addActionClass(checkbox);

            if (typeof event === 'function') checkbox.setEvent(event);
            if (typeof icons === 'object') checkbox.setIcons(icons);
            checkbox.setInput(status);

            var method = enable ? 'enable' : 'disable';

            checkbox[method]();

            return checkbox;
        }
        hide(name) {
            var actions = this.getActions();
            if (actions.hasOwnProperty(name)) actions[name].hide();
            return this;
        }
        show(name) {
            var actions = this.getActions();
            if (actions.hasOwnProperty(name)) actions[name].show();
            return this;
        }
        disable(name) {
            var actions = this.getActions();
            if (actions.hasOwnProperty(name)) actions[name].disable();
            return this;
        }
        enable(name) {
            var actions = this.getActions();
            if (actions.hasOwnProperty(name)) actions[name].enable();
            return this;
        }
        removeAction(name) {
            var actions = this.getActions();
            if (actions.hasOwnProperty(name)) {
                this.getReference().removeElementDOM(actions[name].out());
                delete actions[name];
            }
            return this;
        }
        getContainer() {
            if (this.elements.hasOwnProperty('container')) return this.elements.container;
            this.elements.container = document.createElement('div');
            this.elements.container.className = 'action';
            return this.elements.container;
        }
        resize() {
            var actions = this.getActions(), keys = Object.keys(actions), width = keys.length * 32;
            this.getReference().getHeader().style.right = width.toString() + 'px';
            return this;
        }
        out() {
            return this.getContainer();
        }
    }

    window.AssignmentAction = AssignmentAction;
    window.AssignmentActionCheckbox = AssignmentActionCheckbox;

})(window);
