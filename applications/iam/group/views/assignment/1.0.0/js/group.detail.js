(function (window) {

    'use strict';

    class GroupDetailLabel {
        static opacity() {
            return '0.3';
        }
        static initial() {
            return [];
        }
        static key() {
            return '';
        }
        static rgb() {
            return [
                'rgba(72, 61, 139, 1)',
                'rgba(34, 139, 34, 1)',
                'rgba(0, 128, 0, 1)',
                'rgba(211, 211, 211, 1)',
                'rgba(255, 99, 71, 1)',
                'rgba(112, 128, 144, 1)',
                'rgba(255, 0, 0, 1)',
                'rgba(219, 112, 147, 1)',
                'rgba(25, 25, 112, 1)',
                'rgba(255, 105, 180, 1)'
            ];
        }

        constructor(matrix) {
            this.matrix = matrix;
            this.elements = {};

            this.options = {};
            this.options.color = this.constructor.getRandomColor();

            var matrix = this.getMatrix(), array = [];

            for (var item = 0; item < this.constructor.initial().length; item++) {
                if (!matrix.hasOwnProperty(this.constructor.initial()[item])) continue;
                array.push(matrix[this.constructor.initial()[item]]);
            
            }

            var text = array.join(String.fromCharCode(32)), key = this.constructor.key();
            this.setInitials(text);

            if (matrix.hasOwnProperty(key)) {
                var result = '<' + matrix[key] + '>';
                array.push(result);
            }
            var text = array.join(String.fromCharCode(32));
            this.setText(text);
        }

        getMatrix() {
            return this.matrix;
        }
        getColor() {
            return this.options.color;
        }
        getInitials() {
            if (this.elements.hasOwnProperty('initials')) return this.elements.initials;
            var color = this.getColor();
            this.elements.initials = document.createElement('span');
            this.elements.initials.className = 'initials';
            this.elements.initials.style.backgroundColor = color;
            return this.elements.initials;
        }
        setInitials(text) {
            var initials = this.getInitials(), text = this.constructor.getInitials(text), node = document.createTextNode(text);
            initials.innerText = '';
            initials.appendChild(node);
            return this;
        }
        getText() {
            if (this.elements.hasOwnProperty('text')) return this.elements.text;
            this.elements.text = document.createElement('span');
            this.elements.text.className = 'contains';
            return this.elements.text;
        }
        setText(text) {
            var element = this.getText(), node = document.createTextNode(text);
            element.innerText = '';
            element.appendChild(node);
            return this;
        }
        getContainer() {
            if (this.elements.hasOwnProperty('container')) return this.elements.container;
            var color = this.getColor(), initials = this.getInitials(), text = this.getText(), opacity = this.constructor.opacity();
            this.elements.container = document.createElement('li');
            this.elements.container.className = 'label';
            this.elements.container.style.backgroundColor = color.replace(/(\d+)(?!.*\d)/, opacity);
            this.elements.container.appendChild(initials);
            this.elements.container.appendChild(text);
            return this.elements.container;
        }
        out() {
            return this.getContainer();
        }
        static getRandomColor() {
            return GroupDetailLabel.rgb()[Math.floor(Math.random() * GroupDetailLabel.rgb().length)];
        }
        static getInitials(text) {
            var split = text.split(String.fromCharCode(32)), string = '';
            for (var item = 0; item < split.length; item++) {
                string += split[item].charAt(0).toString();
                if (string.length === 1 && item === split.length) string += split[item].length < 2 ? String.fromCharCode(32) : split[item].charAt(1).toString();
            }
            return string;
        }
    }

    class GroupDetailLine {
        constructor(details) {
            this.details = details;
            this.elements = {};
            this.elements.labels = [];
        }

        getDetails() {
            return this.details;
        }
        getTitle() {
            if (this.elements.hasOwnProperty('title')) return this.elements.title;
            this.elements.title = document.createElement('h5');
            this.elements.title.className = 'title';
            return this.elements.title;
        }
        setTitle(text) {
            var node = document.createTextNode(text), title = this.getTitle();
            title.innerText = '';
            title.appendChild(node);
            return this;
        }
        getContainer() {
            if (this.elements.hasOwnProperty('container')) return this.elements.container;
            var title = this.getTitle(), ul = this.getUl();
            this.elements.container = document.createElement('li');
            this.elements.container.className = 'container';
            this.elements.container.appendChild(title);
            this.elements.container.appendChild(ul);
            return this.elements.container;
        }
        getUl() {
            if (this.elements.hasOwnProperty('ul')) return this.elements.ul;
            this.elements.ul = document.createElement('ul');
            this.elements.ul.className = 'list';
            return this.elements.ul;
        }
        getLabels() {
            return this.elements.labels;
        }
        addLabel(label) {
            if (false === label instanceof GroupDetailLabel) return this;
            this.getUl().appendChild(label.out());
            this.getLabels().push(label);
            return this;
        }
        out() {
            return this.getContainer();
        }
        empty() {
            var labels = this.getLabels();
            for (var item = 0; item < labels.length; item++) GroupDetail.removeElementDOM(labels[item].out());
            return this;
        }
    }

    class GroupDetailImage {

        static icon() {
            return 'group';
        }

        constructor(details) {
            this.details = details;
            this.elements = {};
        }

        getDetails() {
            return this.details;
        }
        get() {
            if (this.elements.hasOwnProperty('image')) return this.elements.image;
            var person = GroupDetail.getIcon(this.constructor.icon());
            this.elements.image = document.createElement('div');
            this.elements.image.className = 'container';
            this.elements.image.appendChild(person);
            return this.elements.image;
        }
        set(src) {
            var image = this.get(), picture = document.createElement('img');

            picture.src = src;
            picture.className = 'rounded';

            image.innerText = '';
            image.appendChild(picture);
            return this;
        }
        getContainer() {
            if (this.elements.hasOwnProperty('container')) return this.elements.container;
            var image = this.get();
            this.elements.container = document.createElement('div');
            this.elements.container.className = 'wrapper';
            this.elements.container.appendChild(image);
            return this.elements.container;
        }
        out() {
            return this.getContainer();
        }


    }

    class GroupDetailsPreloader {
        constructor(details) {
            this.details = details;
            this.elements = {};
        }

        getDetails() {
            return this.details;
        }
        getPreloader() {
            if (this.elements.hasOwnProperty('preloader')) return this.elements.preloader;
            this.elements.preloader = document.createElement('div');
            this.elements.preloader.className = 'preloader';
            return this.elements.preloader;
        }
        getSpinner() {
            if (this.elements.hasOwnProperty('spinner')) return this.elements.spinner;
            this.elements.spinner = document.createElement('div');
            this.elements.spinner.className = 'spinner';

            for (var item = 0; item < 3; item++) {
                var bounce = document.createElement('div');
                bounce.className = 'bounce-' + item;
                this.elements.spinner.appendChild(bounce);
            }

            return this.elements.spinner;
        }
        showSpinner() {
            var spinner = this.getSpinner();
            this.getPreloader().appendChild(spinner);
            return this;
        }
        hideSpinner() {
            var spinner = this.getSpinner();
            GroupDetail.removeElementDOM(spinner);
            return this;
        }
        show() {
            var preloader = this.getPreloader();
            this.getDetails().getContainer().appendChild(preloader);
            return this;
        }
        hide() {
            var preloader = this.getPreloader();
            GroupDetail.removeElementDOM(preloader);
            return this;
        }
        status() {
            return this.getPreloader().parentNode !== null;
        }
    }

    class GroupDetail {
        static space() {
            return 4;
        }
        static content() {
            return {};
        };

        constructor() {
            this.elements = {};
            this.elements.image = new GroupDetailImage(this);
            this.elements.preloader = new GroupDetailsPreloader(this);

            this.xhr = {
                url: null,
                construct: new XMLHttpRequest(),
                callback: null
            };
            this.xhr.construct.onreadystatechange = this.result.bind(this);
        }

        getImage() {
            return this.elements.image;
        }
        getPreloader() {
            return this.elements.preloader;
        }
        getXHR() {
            return this.xhr.construct;
        }
        setXHRCallback(func) {
            this.xhr.callback = func;
            return this;
        }
        getXHRCallback() {
            return this.xhr.callback;
        }
        getUrl() {
            return this.xhr.url;
        }
        setUrl(url) {
            this.xhr.url = url;
            return this;
        }
        request(callback) {
            this.getPreloader().showSpinner().show();
            this.setXHRCallback(callback);
            var xhr = this.getXHR(), url = this.getUrl();
            xhr.open('GET', url, !0);
            xhr.send();
        }
        result() {
            var xhr = this.getXHR();
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
                
            if (json.data.hasOwnProperty('name')) this.setName(json.data.name);
            if (json.data.hasOwnProperty('description') && null !== json.data.description) this.setDescription(json.data.description);

            var content = this.constructor.content();
            for (var key in content) {
                var labels = [];
                if (!json.data.hasOwnProperty(key)) continue;
                if (false === Array.isArray(json.data[key])) {
                    var label = new GroupDetailLabel(json.data[key]);
                    labels.push(label);
                } else for (var item = 0; item < json.data[key].length; item++) {
                    var label = new GroupDetailLabel(json.data[key][item]);
                    labels.push(label);
                }

                if (labels.length === 0) continue;

                var line = new GroupDetailLine(this);
                line.setTitle(content[key]);
                this.getLine().appendChild(line.out());

                for (var item = 0; item < labels.length; item++) line.addLabel(labels[item]);
            }

            var callback = this.getXHRCallback();
            if (typeof callback === 'function') callback.call(this);
        }

        getLine() {
            if (this.elements.hasOwnProperty('ul')) return this.elements.ul;
            this.elements.ul = document.createElement('ul');
            this.elements.ul.className = 'line';
            return this.elements.ul;
        }
        getInfo() {
            if (this.elements.hasOwnProperty('info')) return this.elements.info;
            var name = this.getName(), description = this.getDescription(), line = this.getLine();
            this.elements.info = document.createElement('div');
            this.elements.info.className = 'info';
            this.elements.info.appendChild(name);
            this.elements.info.appendChild(description);
            this.elements.info.appendChild(line);
            return this.elements.info;
        }
        getName() {
            if (this.elements.hasOwnProperty('name')) return this.elements.name;
            var node = document.createTextNode(String.fromCharCode(45));
            this.elements.name = document.createElement('h2');
            this.elements.name.appendChild(node);
            return this.elements.name;
        }
        setName(text) {
            if (text.length === 0) return this;
            var node = document.createTextNode(text), name = this.getName();
            name.innerText = '';
            name.appendChild(node);
            return this;
        }
        getDescription() {
            if (this.elements.hasOwnProperty('description')) return this.elements.description;
            var node = document.createTextNode(String.fromCharCode(45));
            this.elements.description = document.createElement('h4');
            this.elements.description.appendChild(node);
            return this.elements.description;
        }
        setDescription(text) {
            if (text.length === 0) return this;
            var node = document.createTextNode(text), description = this.getDescription();
            description.innerText = '';
            description.appendChild(node);
            return this;
        }
        getLeft() {
            if (this.elements.hasOwnProperty('left')) return this.elements.left;
            var image = this.getImage().out(), space = this.constructor.space().toString();
            this.elements.left = document.createElement('div');
            this.elements.left.className = 'image pure-u-' + space + '-24';
            this.elements.left.appendChild(image);
            return this.elements.left;
        }
        getRight() {
            if (this.elements.hasOwnProperty('right')) return this.elements.right;
            var info = this.getInfo(), space = 24 - this.constructor.space();
            this.elements.right = document.createElement('div');
            this.elements.right.className = 'detail pure-u-' + space.toString() + '-24';
            this.elements.right.appendChild(info);
            return this.elements.right;
        }
        getContainer() {
            if (this.elements.hasOwnProperty('container')) return this.elements.container;
            var left = this.getLeft(), right = this.getRight();
            this.elements.container = document.createElement('div');
            this.elements.container.className = 'group pure-g';
            this.elements.container.appendChild(left);
            this.elements.container.appendChild(right);
            return this.elements.container;

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
            var icon = document.createElement('i');
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

    window.GroupDetail = GroupDetail;
    window.GroupDetailLabel = GroupDetailLabel;

})(window);
