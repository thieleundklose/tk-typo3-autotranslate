import RecordTranslationTrigger from './record-translation-trigger.js';

export default {
    triggerRecordTranslation(table, uid) {
        RecordTranslationTrigger.trigger(table, parseInt(uid, 10));
    },
};
