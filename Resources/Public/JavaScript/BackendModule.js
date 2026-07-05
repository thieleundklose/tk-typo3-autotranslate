import DocumentService from '@typo3/core/document-service.js';
import DateTimePicker from '@typo3/backend/date-time-picker.js';

class Form {
    constructor() {
        DocumentService.ready().then(() => {
            this.initializeDateTimePickers();
            this.initializeRecursiveLevelFilter();
        });
    }

    initializeDateTimePickers() {
        document.querySelectorAll('.t3js-datetimepicker')?.forEach((element) => {
            DateTimePicker.initialize(element);
        })
    }

    initializeRecursiveLevelFilter() {
        document.querySelectorAll('[data-autotranslate-level-select]')?.forEach((element) => {
            element.addEventListener('change', (event) => {
                const target = event.currentTarget;
                if (target instanceof HTMLSelectElement && target.value) {
                    window.location.href = target.value;
                }
            });
        });
    }
}

export default new Form();
