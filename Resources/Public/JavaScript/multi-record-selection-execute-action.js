import { MultiRecordSelectionSelectors } from "@typo3/backend/multi-record-selection.js";

// TODO: extend to handle reset action, optimize code
class MultiRecordSelectionExecuteAction {

    constructor() {
        this.execute = this.execute.bind(this);
        document.addEventListener("multiRecordSelection:action:execute", this.execute);
        const executeEvent = new CustomEvent("multiRecordSelection:action:execute", {
            detail: {
                identifier: 'task-group-list',
                checkboxes: document.querySelectorAll('input[type="checkbox"]:checked')
            }
        });
        document.dispatchEvent(executeEvent);


        this.delete = this.delete.bind(this);
        document.addEventListener("multiRecordSelection:action:delete", this.delete);
        const deleteEvent = new CustomEvent("multiRecordSelection:action:delete", {
            detail: {
                identifier: 'task-group-list',
                checkboxes: document.querySelectorAll('input[type="checkbox"]:checked')
            }
        });
        document.dispatchEvent(deleteEvent);
    }

    execute(event) {
        const formElement = document.querySelector('[data-multi-record-selection-form="task-group-list"]');
        const selectedUids = [];
        if (event.detail.checkboxes && event.detail.checkboxes.length > 0) {
            event.detail.checkboxes.forEach((checkbox => {
                const closestElement = checkbox.closest(MultiRecordSelectionSelectors.elementSelector);
                if (closestElement !== null && closestElement.dataset.uid) {
                    selectedUids.push(closestElement.dataset.uid);
                }
            }));
        }
        if (selectedUids.length) {
            if ("multiRecordSelection:action:execute" === event.type) {
                const input = document.createElement("input");
                input.setAttribute("type", "hidden");
                input.setAttribute("name", "execute");
                input.setAttribute("value", selectedUids.join(","));
                formElement.append(input);
            }
            formElement.submit();
        }
    }

    delete(event) {
        const formElement = document.querySelector('[data-multi-record-selection-form="task-group-list"]');
        const selectedUids = [];
        if (event.detail.checkboxes && event.detail.checkboxes.length > 0) {
            event.detail.checkboxes.forEach((checkbox => {
                const closestElement = checkbox.closest(MultiRecordSelectionSelectors.elementSelector);
                if (closestElement !== null && closestElement.dataset.uid) {
                    selectedUids.push(closestElement.dataset.uid);
                }
            }));
        }
        if (selectedUids.length) {
            if ("multiRecordSelection:action:delete" === event.type) {
                const input = document.createElement("input");
                input.setAttribute("type", "hidden");
                input.setAttribute("name", "delete");
                input.setAttribute("value", selectedUids.join(","));
                formElement.append(input);
            }
            formElement.submit();
        }
    }
}

export default new MultiRecordSelectionExecuteAction;
