(function (window) {

    'use strict';

    class AssignmentAssignCheckboxException {
        static effect() {
            return 'bug';
        }
        static material() {
            return 'bug_report';
        }

        constructor(assign) {
            this.assign = assign;

            var icon = this.getAssign().getIcon();

            this.elements = {};
            this.elements.tooltip = new AssignmentTooltip(icon);
        }

        getAssign() {
            return this.assign;
        }
        getTooltip() {
            return this.elements.tooltip;
        }
        show(text) {
            var assign = this.getAssign(), icon = assign.getIcon();

            this.elements.node = icon.cloneNode(true);
            assign.setIcon(this.constructor.material());
            assign.getIcon().classList.add(this.constructor.effect());

            if (typeof text === 'string') this.getTooltip().setText(text).active();

            return this;
        }
        hide() {
            if (!this.elements.hasOwnProperty('node')
                || this.elements.node instanceof HTMLElement === false) return this;

            var assign = this.getAssign();

            assign.getIcon().classList.remove(this.constructor.effect());
            assign.enable().setIcon(this.elements.node.innerText);
            delete this.elements.node;

            return this;
        }
        status() {
            return this.getAssign().getIcon().classList.contains(this.constructor.effect());
        }
    }

    class AssignmentAssignCheckboxPreloader {
        static material() {
            return 'hourglass_top';
        }

        constructor(assign) {
            this.assign = assign;

            this.elements = {};
        }

        getAssign() {
            return this.assign;
        }
        show() {
            var assign = this.getAssign(), icon = assign.getIcon(), material = this.constructor.material();

            this.elements.node = icon.cloneNode(true);
            assign.disable().setIcon(material);

            return this;
        }
        hide() {
            this.getAssign().enable().setIcon(this.elements.node.innerText);
            delete this.elements.node;
            return this;
        }
        status() {
            var material = this.constructor.material();
            return this.getAssign().getIcon().classList.contains(material);
        }
    }

    class AssignmentAssign {
        static show() {
            return 'show';
        }
        static icon() {
            return 'smart_button';
        }
        static disabled() {
            return 'disabled';
        }
        static active() {
            return 'assign';
        }

        constructor(reference) {
            this.reference = reference;

            this.events = {};
            this.elements = {};
            this.elements.actions = {};
            this.elements.exception = new AssignmentAssignCheckboxException(this);
            this.elements.preloader = new AssignmentAssignCheckboxPreloader(this);
        }

        getReference() {
            return this.reference;
        }
        getActions() {
            return this.elements.actions;
        }
        getPreloader() {
            return this.elements.preloader;
        }
        getException() {
            return this.elements.exception;
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
            this.elements.icon = this.getReference().getIcon(this.constructor.icon());
            this.elements.icon.classList.add('assign');
            this.elements.icon.setAttribute('data-handle-event', 'click:click');
            this.elements.icon.addEventListener('click', this, true);
            return this.elements.icon;
        }
        setIcon(text) {
            var icon = this.getIcon(), node = document.createTextNode(text);
            icon.innerText = '';
            icon.appendChild(node);
            return this;
        }
        setAction(material, enable, func) {
            var icon = this.getIcon(), main = this.getReference().getMain(), method = enable ? 'enable' : 'disable';

            this.setIcon(material);
            this.setEvent(func);
            this.show();

            this[method]();

            main.insertBefore(icon, main.firstChild);
            return this;
        }
        hide() {
            this.getIcon().classList.remove(this.constructor.show());
            this.getReference().getMain().classList.remove(this.constructor.active());
            return this;
        }
        show() {
            this.getIcon().classList.add(this.constructor.show());
            this.getReference().getMain().classList.add(this.constructor.active());
            return this;
        }
        status() {
            return this.getIcon().classList.contains(this.constructor.show());
        }
        disable() {
            this.getIcon().classList.add(this.constructor.disabled());
            return this;
        }
        enable() {
            this.getIcon().classList.remove(this.constructor.disabled());
            return this;
        }
		handleEvent(event) {
            let attribute = AssignmentLine.closestAttribute(event.target, 'data-handle-event');
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
            var func = this.getEvent(), icon = this.getIcon();
            if (typeof func !== 'function'
                || icon.classList.contains(this.constructor.disabled())) return;

            func.call(this, event);
        }
    }

    window.AssignmentAssign = AssignmentAssign;

})(window);
