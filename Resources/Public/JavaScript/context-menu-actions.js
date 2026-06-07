/**
 * Module: @thieleundklose/autotranslate/context-menu-actions
 *
 * Context menu actions for autotranslate.
 */

import RecordTranslationTrigger from './record-translation-trigger.js';

class ContextMenuActions {
    triggerRecordTranslation(table, uid) {
        RecordTranslationTrigger.trigger(table, parseInt(uid, 10));
    }
}

export default new ContextMenuActions();
