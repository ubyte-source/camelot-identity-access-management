(function (window) {

    'use strict';

    class AssignmentPolicy extends AssignmentLine {

        setGhost() {
            let actions = this.getAction().getActions();
            for (let name in actions) actions[name].disable();
            this.getContainer().classList.add('ghost');
            return this;
        }
        getGhost() {
            return this.getContainer().classList.contains('ghost');
        }
    }

    window.AssignmentPolicy = AssignmentPolicy;

})(window);
