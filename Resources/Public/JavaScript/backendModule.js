import DocumentService from '@typo3/core/document-service.js';
import DateTimePicker from '@typo3/backend/date-time-picker.js';

class Form {
    constructor() {
        DocumentService.ready().then(() => {
        this.initializeDateTimePickers();
        });
    }

    initializeDateTimePickers() {
        document.querySelectorAll('.t3js-datetimepicker')?.forEach((element) => {
            DateTimePicker.initialize(element);
        })
    }
}

export default new Form();
