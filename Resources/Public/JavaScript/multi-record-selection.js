import RegularEvent from "@typo3/core/event/regular-event.js";
import { MultiRecordSelectionSelectors } from "@typo3/backend/multi-record-selection.js";
// import DocumentService from "@typo3/core/document-service.js";
class TranslateMultiRecordSelection {
    constructor() {
        document.addEventListener("DOMContentLoaded", () => {
            this.initializeEvents();
        });
    }

    initializeEvents() {
        new RegularEvent("click", (e, t) => {
            e.preventDefault();
            const a = new URL(t.href, window.origin);
            a.searchParams.set("mode", t.dataset.mode);
            a.searchParams.set("bparams", t.dataset.params);
            Modal.advanced({
                type: Modal.types.iframe,
                content: a.toString(),
                size: Modal.sizes.large
            });
        }).delegateTo(document, ".t3js-element-browser");

        new RegularEvent("multiRecordSelection:action:execute", this.executeTasks.bind(this)).bindTo(document);
        new RegularEvent("multiRecordSelection:action:go", this.executeTasks.bind(this)).bindTo(document);

        console.log('initializeEvents');

        // Schritt 2: Event-Listener für die Buttons
        document.querySelectorAll('.your-action-button-selector').forEach(button => {
            button.addEventListener('click', (e) => {
                const event = new CustomEvent('multiRecordSelection:action:execute', {
                    detail: {
                        identifier: 'your-identifier',
                        checkboxes: document.querySelectorAll('.your-checkbox-selector:checked')
                    }
                });
                document.dispatchEvent(event);
            });
        });
    }

    executeTasks(e) {
        console.log('executeTasks aufgerufen', e);
        const t = document.querySelector('[data-multi-record-selection-form="' + e.detail.identifier + '"]');
        if (null === t) return;
        const a = [];
        e.detail.checkboxes.forEach((checkbox) => {
            const element = checkbox.closest(MultiRecordSelectionSelectors.elementSelector);
            if (null !== element && element.dataset.taskId) {
                a.push(element.dataset.taskId);
            }
        });

        if (a.length) {
            if ("multiRecordSelection:action:execute" === e.type) {
                console.log('if funktioniert');
            } else {
                console.log('else funktioniert');
            }
            t.submit();
        }
    }
}

// Initialisiere die Klasse
export default new TranslateMultiRecordSelection();

// Schritt 3: Event-Handler für die Auswahl
document.addEventListener('multiRecordSelection:action:execute', (e) => {
    console.log('Event ausgelöst', e);
    // Deine Logik hier
});

    // v2 ==========================

    // constructor() {
    //     DocumentService.ready().then((() => {
    //         this.initializeEvents()
    //     }))
    // }

    // initializeEvents() {

    //     new RegularEvent("click", ((e, t) => {
    //         e.preventDefault();
    //         const a = new URL(t.href, window.origin);
    //         a.searchParams.set("mode", t.dataset.mode), a.searchParams.set("bparams", t.dataset.params), Modal.advanced({
    //             type: Modal.types.iframe,
    //             content: a.toString(),
    //             size: Modal.sizes.large
    //         })
    //     })).delegateTo(document, ".t3js-element-browser"), new RegularEvent("show.bs.collapse", new RegularEvent("multiRecordSelection:action:go", this.executeTasks.bind(this)).bindTo(document), new RegularEvent("multiRecordSelection:action:execute", this.executeTasks.bind(this)).bindTo(document));

    //     console.log('initializeEvents');
    // }
    // executeTasks(e) {
    //     console.log('initializeTasks');
    //     const t = document.querySelector('[data-multi-record-selection-form="' + e.detail.identifier + '"]');
    //     if (null === t) return;
    //     const a = [];
    //     if (e.detail.checkboxes.forEach((e => {
    //             const t = e.closest(MultiRecordSelectionSelectors.elementSelector);
    //             null !== t && t.dataset.taskId && a.push(t.dataset.taskId)
    //         })), a.length) {
    //         if ("multiRecordSelection:action:execute" === e.type) {
    //             console.log('if funktioniert');
    //         } else {
    //             console.log('else funktioniert');
    //         }
    //         t.submit()
    //     }


    // document.addEventListener("multiRecordSelection:action:execute", function() {
    //     console.log("Event ausgelöst");
    // });
    // }

    // v1 ==========================
    // constructor() {
    //     this.initializeEvents();
    // }

    // initializeEvents() {
    //     new RegularEvent("multiRecordSelection:action:execute", this.executeTasks.bind(this)).bindTo(document);
    //     new RegularEvent("multiRecordSelection:action:delete", this.executeTasks.bind(this)).bindTo(document);
    // }

    // executeTasks(e) {
    //     const form = document.querySelector('[data-multi-record-selection-form="' + e.detail.identifier + '"]');
    //     if (null === form) return;
    //     const taskIds = [];
    //     e.detail.checkboxes.forEach((checkbox) => {
    //         const element = checkbox.closest(MultiRecordSelectionSelectors.elementSelector);
    //         if (null !== element && element.dataset.taskId) {
    //             taskIds.push(element.dataset.taskId);
    //         }
    //     });

    //     if (taskIds.length) {
    //         const input = document.createElement("input");
    //         input.setAttribute("type", "hidden");
    //         input.setAttribute("name", e.type.split(":").pop());
    //         input.setAttribute("value", taskIds.join(","));
    //         form.append(input);
    //         form.submit();
    //     }
    // }