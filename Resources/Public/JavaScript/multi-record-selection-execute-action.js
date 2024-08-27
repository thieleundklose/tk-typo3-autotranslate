import {MultiRecordSelectionSelectors} from "@typo3/backend/multi-record-selection.js";

class MultiRecordSelectionExecuteAction {

    constructor() {
        this.execute = this.execute.bind(this);

        document.addEventListener("multiRecordSelection:action:execute", this.execute);

        const event = new CustomEvent("multiRecordSelection:action:execute", {
            detail: {
                identifier: 'task-group-list',
                checkboxes: document.querySelectorAll('input[type="checkbox"]:checked')
            }
        });
        document.dispatchEvent(event);
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
}

export default new MultiRecordSelectionExecuteAction;
