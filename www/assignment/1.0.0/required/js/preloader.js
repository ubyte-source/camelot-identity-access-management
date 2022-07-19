(function (window) {

    'use strict';

    class AssignmentPreloader {
        constructor(container) {
            this.elements = {};
            this.elements.container = container;

            this.events = {
                // opened: (function)
                // closer: (function)
            };
        }

        getContainer() {
            return this.elements.container;
        }
        setEventShow(func) {
            if (typeof func === 'function') this.events.opened = func;
            return this;
        }
        getEventShow() {
            if (this.events.hasOwnProperty('opened')) return this.events.opened;
            return null;
        }
        setEventHide(func) {
            if (typeof func === 'function') this.events.closer = func;
            return this;
        }
        getEventHide() {
            if (this.events.hasOwnProperty('closer')) return this.events.closer;
            return null;
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
            this.constructor.removeElementDOM(spinner);
            return this;
        }
        show() {
            var container = this.getContainer(), preloader = this.getPreloader();
            if (container instanceof HTMLElement) {
                container.appendChild(preloader);

                var event = this.getEventShow();
                if (typeof event === 'function') event.call(this);
            }
            return this;
        }
        hide() {
            var preloader = this.getPreloader(), event = this.getEventHide();

            this.constructor.removeElementDOM(preloader);

            if (typeof event === 'function') event.call(this);

            return this;
        }
        status() {
            return this.getPreloader().parentNode !== null;
        }
        static removeElementDOM(element) {
            let parent = element === null || typeof element === 'undefined' || typeof element.parentNode === 'undefined' ? null : element.parentNode;
            if (parent === null) return false;
            parent.removeChild(element);
            return true;
        }
    }

    window.AssignmentPreloader = AssignmentPreloader;

})(window);
