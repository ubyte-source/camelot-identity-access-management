(function (window) {

    'use strict';

    class AssignmentPath {
        static text() {
            return 'developer\\text\\path';
        }
        static separator() {
            return '>>';
        }
        static paths() {
            return 'paths';
        }

        constructor(reference) {
            this.reference = reference;
            this.elements = {};
        }

        getReference() {
            return this.reference;
        }
        getText() {
            if (this.elements.hasOwnProperty('text')) return this.elements.text;
            var node = document.createTextNode(this.constructor.text());
            this.elements.text = document.createElement('h5');
            this.elements.text.className = 'title';
            this.elements.text.appendChild(node);
            return this.elements.text;
        }
        getList() {
            if (this.elements.hasOwnProperty('list')) return this.elements.list;
            this.elements.list = document.createElement('ul');
            this.elements.list.className = 'list';
            return this.elements.list;
        }
        getPath() {
            if (this.elements.hasOwnProperty('path')) return this.elements.path;
            var text = this.getText(), list = this.getList();
            this.elements.path = document.createElement('div');
            this.elements.path.className = 'path';
            this.elements.path.appendChild(text);
            this.elements.path.appendChild(list);
            return this.elements.path;
        }
        addPath(text) {
            var path = this.getPath();
            if (path.parentNode === null) {
                var info = this.getReference().getInfo();
                info.insertBefore(path, info.firstChild);
            }

            var item = document.createElement('li'), node = document.createTextNode(text);
            item.className = 'ellipsis';
            item.appendChild(node);
            this.getList().appendChild(item);
            return this;
        }
        elaborate() {
            var structure = this.getReference().getStructure();
            if (structure === null
                || !structure.hasOwnProperty(this.constructor.paths())
                || 0 === structure[this.constructor.paths()].length) return this;

            var path = [];
            for (var item = 0; item < structure[this.constructor.paths()].length; item++) {
                if (false === Array.isArray(structure[this.constructor.paths()][item])) continue;
                for (var i = 0; i < structure[this.constructor.paths()][item].length; i++) {
                    var group = this.getReference().findGroup(structure[this.constructor.paths()][item][i]), name = structure[this.constructor.paths()][item][i].toString();
                    if (group !== null) {
                        var internal = group.getStructure();
                        if (internal.hasOwnProperty('name')) name = internal.name.toString();
                    }
                    path.unshift(name);
                }
            }

            if (0 === path.length) return this;

            var string = String.fromCharCode(32) + this.constructor.separator() + String.fromCharCode(32);
            this.addPath(path.join(string));
            return this;
        }
    }

    window.AssignmentPath = AssignmentPath;

})(window);
