(function (window) {

    'use strict';

    class AssignmentGroup extends AssignmentLine {

        static name() {
            return 'group';
        }

        constructor(structure, groups) {
            super(structure, groups);

            this.elements.policies = [];

            let name = this.constructor.name();
            this.getContainer().classList.add(name);
        }

        getPolicies() {
            return this.elements.policies;
        }
        getAllPoliciesKey() {
            let response = [],
                policies = this.getPolicies();

            for (let item = 0; item < policies.length; item++) {
                let structure = policies[item].getStructure();
                if (structure === null
                    || !structure.hasOwnProperty(AssignmentLine.key())) continue;

                response.push(structure[AssignmentLine.key()]);
            }

            return response;
        }
        getPoliciesUL() {
            if (this.elements.hasOwnProperty('ul')) return this.elements.ul;
            this.elements.ul = document.createElement('ul');
            this.elements.ul.className = 'list';
            return this.elements.ul;
        }
        getInfo() {
            if (this.elements.hasOwnProperty('info')) return this.elements.info;
            let description = this.getDescription(), ul = this.getPoliciesUL(), expand = this.getAction().addAction('toggle', this.constructor.toggle(), false, true);

            let icon = expand.show().out();
            icon.classList.add('expand');
            icon.addEventListener('click', this, false);

            this.elements.info = document.createElement('div');
            this.elements.info.className = 'info';
            this.elements.info.appendChild(description);
            this.elements.info.appendChild(ul);
            return this.elements.info;
        }
        pushPolicy(policy) {
            if (false === policy instanceof AssignmentPolicy) return this;

            let policies = this.getAllPoliciesKey(), structure = policy.getStructure();
            if (!structure.hasOwnProperty(AssignmentLine.key())
                || policies.indexOf(structure[AssignmentLine.key()]) !== -1) return this;

            this.getPoliciesUL().appendChild(policy.out());
            this.getPolicies().push(policy);

            return this;
        }
    }

    window.AssignmentGroup = AssignmentGroup;

})(window);
