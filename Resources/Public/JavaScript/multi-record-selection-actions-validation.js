class MultiRecordSelectionExecuteActionValidation {

    constructor() {
        this.execute = this.execute.bind(this);

        const checkboxes = document.querySelectorAll('.t3js-multi-record-selection-check');
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', this.execute);
        });        
    }

    execute(event) {
        const checkbox = event.target;

        const trElement = checkbox.closest('tr');
        const buttons = document.querySelectorAll('.panel-heading-actions button[data-multi-record-selection-action="execute"]');

        if (checkbox.checked && trElement && trElement.dataset.execute === "") {
            buttons.forEach(button => {
                button.classList.add('disabled');
            });
        } else {
            buttons.forEach(button => {
                button.classList.remove('disabled');
            });
        }
    }
}

export default new MultiRecordSelectionExecuteActionValidation;
