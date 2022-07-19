(function (window) {

    'use strict';

    class AssignmentTooltip {
        static id() {
            return 'assignment-tooltip-container';
        }
        static attribute() {
            return 'data-tooltip-assignment';
        }

        constructor(icon) {
            this.elements = {};
            this.elements.icon = icon;

            this.options = {};
            this.options.width = 320;

            this.options.ready = setInterval(function (instance) {
                if (document.readyState !== 'complete') return;
                clearInterval(instance.options.ready);
                instance.getTooltip.call(instance);
            }, 100, this);
        }

        getIcon() {
            return this.elements.icon;
        }
        setText(text) {
            var icon = this.getIcon();
            if (icon instanceof HTMLElement === false
                || typeof text !== 'string') return this;

            icon.setAttribute(this.constructor.attribute(), text);
            return this;
        }
        setWidth(width) {
            this.options.width = width;
            return this;
        }
        getWidth() {
            return this.options.width;
        }
        active() {
            var icon = this.getIcon();
            if (icon instanceof HTMLElement === false) return this;

            var handler = this.constructor.getElementHandle(icon);
            icon.setAttribute('data-handle-event', handler + 'mouseenter:show mouseleave:hide');
            icon.addEventListener('mouseenter', this, true);
            icon.addEventListener('mouseleave', this, true);

            return this;
        }
        stop() {
            var icon = this.getIcon();
            if (icon instanceof HTMLElement === false) return this;

            var handler = this.constructor.getElementHandle(icon);
            icon.setAttribute('data-handle-event', handler.join(String.fromCharCode(32)));
            icon.removeEventListener('mouseenter', this, true);
            icon.removeEventListener('mouseleave', this, true);

            return this;
        }
        getTooltip() {
            var query = document.getElementById(this.constructor.id());
            if (query !== null) this.elements.tooltip = query;
            if (this.elements.hasOwnProperty('tooltip')) return this.elements.tooltip;

            this.elements.tooltip = document.createElement('div');
            this.elements.tooltip.setAttribute('id', this.constructor.id());

            document.body.appendChild(this.elements.tooltip);

            return this.elements.tooltip;
        }
        show(ev) {
            if (ev.target instanceof Element === false
                || !ev.target.hasAttribute(this.constructor.attribute())) return;

            var text = ev.target.getAttribute(this.constructor.attribute());
            if (text.length === 0) return;

            var tooltip = this.getTooltip();

            tooltip.removeAttribute('class');
            tooltip.innerText = text;
            tooltip.style.width = this.getWidth().toString() + 'px';

            if (window.innerWidth < tooltip.offsetWidth * 1.5) {
                var width = window.innerWidth / 2;
                tooltip.style.maxWidth = width.toString() + 'px';
            }

            var body_rectangle = document.body.getBoundingClientRect(), element_rectangle = ev.target.getBoundingClientRect(), offset_left = element_rectangle.left - body_rectangle.left;

            var position_left = offset_left + (element_rectangle.width / 2) - (tooltip.offsetWidth / 2);
            if (position_left < 0) {
                position_left = offset_left + element_rectangle.width / 2 - 20;
                tooltip.classList.add('left');
            }

            if (position_left + tooltip.offsetWidth > window.innerWidth) {
                position_left = offset_left - tooltip.offsetWidth + element_rectangle.width / 2 + 20;
                tooltip.classList.add('right');
            }

            var offset_top = element_rectangle.top - body_rectangle.top;
            var position_top = offset_top - tooltip.offsetHeight - 20;
            if (position_top < 0) {
                position_top = offset_top + element_rectangle.height;
                tooltip.classList.add('top');
            }

            tooltip.style.left = position_left + 'px';
            tooltip.style.top = position_top + 'px';
            tooltip.classList.add('show');
        }
        hide() {
            this.getTooltip().classList.remove('show');
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
        static getElementHandle(element) {
            var handle = element.getAttribute('data-handle-event');
            if (handle === null
                || handle.length === 0) return '';

            var filtered = handle.split(String.fromCharCode(32)).filter(function (item) {
                return item !== 'mouseenter:show'
                    && item !== 'mouseleave:hide';
            });

            return filtered.join(String.fromCharCode(32)) + String.fromCharCode(32);
        }
    }

    window.AssignmentTooltip = AssignmentTooltip;

})(window);
