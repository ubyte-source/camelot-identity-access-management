(function (window) {

    'use strict';

    class DropperNotice {

        static danger() {
            return 'alert alert-danger';
        }
        static default() {
            return 'alert alert-info';
        }
        static success() {
            return 'alert alert-success';
        }

        static icon() {
            return 'info';
        }
        static done() {
            return 'done';
        }
        static error() {
            return 'error';
        }

        constructor(dropper) {
            this.dropper = dropper;
            this.elements = {};
        }

        getDropper() {
            return this.dropper;
        }
        getContent() {
            if (this.elements.hasOwnProperty('content')) return this.elements.content;
            let icon = this.getMaterial(), text = this.getText();
            this.elements.content = document.createElement('div');
            this.elements.content.appendChild(icon);
            this.elements.content.appendChild(text);

            this.setStyle(this.constructor.default());

            return this.elements.content;
        }
        setStyle(css) {
            if (typeof css !== 'string'
                || css.length === 0) return this;

            this.getContent().className = css;

            return this;
        }
        getText() {
            if (this.elements.hasOwnProperty('text')) return this.elements.text;
            this.elements.text = document.createElement('span');
            return this.elements.text;
        }
        setText(text) {
            this.getText().innerText = text;
            return this
        }
        getMaterial() {
            if (this.elements.hasOwnProperty('icon')) return this.elements.icon;
            this.elements.icon = Dropper.getIcon(this.constructor.icon);
            return this.elements.icon;
        }
        setMaterial(icon) {
            let node = document.createTextNode(icon), material = this.getMaterial();
            material.innerText = '';
            material.appendChild(node);
            return this
        }
        show() {
            let content = this.getContent(), out = this.getDropper().out();
            out.parentNode.insertBefore(content, out);
            return this;
        }
        hide() {
            let content = this.getContent();
            Dropper.removeElementDOM(content);
            return this;
        }
    }

    class Dropper {

        static text() {
            return 'developer\\assignment\\dropper\\text';
        }
        static unmanage() {
            return 'developer\\assignment\\dropper\\unmanage';
        }

        constructor(sortable) {
            this.elements = {};
            this.elements.notice = new DropperNotice(this);

            this.xhr = {
                url: null,
                hardcode: {},
                construct: new XMLHttpRequest(),
                events: {
                    // success: (function)
                }
            };
            this.xhr.construct.onreadystatechange = this.result.bind(this);

            let out = this.out(), list = this.getUL(), option = Object.assign({}, sortable, {
                reference: this
            });

            this.sortable = new Sortable(list, option);
            this.elements.preloader = new AssignmentPreloader(out);
        }

        getNotice() {
            return this.elements.notice;
        }
        getPreloader() {
            return this.elements.preloader;
        }
        getSuccess() {
            if (this.xhr.events.hasOwnProperty('success')) return this.xhr.events.success;
            return null;
        }
        setSuccess(func) {
            this.xhr.events.success = func.bind(this);
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
        setText(text) {
            let node = document.createTextNode(text), li = this.getLi();
            li.innerText = '';
            li.appendChild(node);
            return this;
        }
        getLi() {
            if (this.elements.hasOwnProperty('li')) return this.elements.li;
            this.elements.li = document.createElement('li');
            this.elements.li.className = 'text';

            this.setText(this.constructor.text());

            return this.elements.li;
        }
        getUL() {
            if (this.elements.hasOwnProperty('ul')) return this.elements.ul;
            let li = this.getLi();
            this.elements.ul = document.createElement('ul');
            this.elements.ul.className = 'list';
            this.elements.ul.appendChild(li);
            return this.elements.ul;
        }
        getContainer() {
            if (this.elements.hasOwnProperty('container')) return this.elements.container;
            let ul = this.getUL();
            this.elements.container = document.createElement('div');
            this.elements.container.className = 'dropper';
            this.elements.container.appendChild(ul);
            return this.elements.container;
        }
        request() {
            this.getNotice().hide();
            this.getPreloader().showSpinner().show();

            let xhr = this.getXHR(), url = this.getRequestUrl(), data = new FormData();
            let hardcode = this.getHardcode();
            for (let item in hardcode) data.append(item, hardcode[item]);
            xhr.open('POST', url, !0);
            xhr.send(data);
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

            if (json.status == true) {
                let success = this.getSuccess(), notice = json.hasOwnProperty('notice') ? json.notice : 'Successful!';
                if (typeof success === 'function') success.call(this);
                return this.getNotice().setText(notice).setStyle(DropperNotice.success()).setMaterial(DropperNotice.done());
            }

            let notice = json.hasOwnProperty('notice') ? json.notice : this.constructor.unmanage();
            return this.getNotice().setText(notice).setStyle(DropperNotice.danger()).setMaterial(DropperNotice.error());
        }
        show() {
            this.getContainer().classList.add('show');
            return this;
        }
        hide() {
            this.getContainer().classList.remove('show');
            return this;
        }
        status() {
            return this.getContainer().classList.contains('show');
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
        static getIcon(name) {
            let icon = document.createElement('i');
            icon.className = 'material-icons';
            icon.innerText = name;
            return icon;
        }
        static removeElementDOM(element) {
            let parent = element === null || typeof element === 'undefined' || typeof element.parentNode === 'undefined' ? null : element.parentNode;
            if (parent === null) return false;
            parent.removeChild(element);
            return true;
        }
    }

    window.Dropper = Dropper;

})(window);
