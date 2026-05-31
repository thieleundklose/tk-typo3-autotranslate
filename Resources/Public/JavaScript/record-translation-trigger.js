import DocumentService from '@typo3/core/document-service.js';
import RegularEvent from '@typo3/core/event/regular-event.js';
import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import Modal from '@typo3/backend/modal.js';
import Notification from '@typo3/backend/notification.js';
import { html } from 'lit';

class RecordTranslationTrigger {
    constructor() {
        DocumentService.ready().then(() => this.registerEvents());
    }

    registerEvents() {
        new RegularEvent('click', (event, button) => {
            event.preventDefault();
            this.trigger(button.dataset.table, parseInt(button.dataset.uid, 10), button);
        }).delegateTo(document, '.t3js-autotranslate-record-trigger');
    }

    async trigger(table, uid, button = null) {
        if (!table || !Number.isInteger(uid) || uid <= 0) {
            Notification.error('Translation could not be prepared.');
            return;
        }

        if (button && button.dataset.loading === '1') {
            return;
        }

        this.setButtonLoading(button, true);

        try {
            const response = await new AjaxRequest(TYPO3.settings.ajaxUrls.autotranslate_record_translation_languages)
                .withQueryArguments({
                    table,
                    uid,
                })
                .get();

            const payload = await response.resolve();
            if (!payload.success) {
                Notification.error(payload.message || 'Translation could not be prepared.');
                this.setButtonLoading(button, false);
                return;
            }

            this.setButtonLoading(button, false);

            const modal = Modal.advanced({
                type: Modal.types.default,
                title: payload.data.modalTitle,
                content: this.renderLanguageSelection(payload.data),
                buttons: [
                    {
                        text: payload.data.cancelLabel,
                        active: true,
                        btnClass: 'btn-default',
                        name: 'cancel',
                    },
                    {
                        text: payload.data.submitLabel,
                        btnClass: 'btn-primary',
                        name: 'translate',
                    },
                ],
                size: Modal.sizes.small,
                staticBackdrop: true,
            });

            modal.addEventListener('button.clicked', async (modalEvent) => {
                const buttonName = modalEvent.target.name;
                if (buttonName === 'cancel') {
                    this.setButtonLoading(button, false);
                    modal.hideModal();
                    return;
                }

                if (buttonName !== 'translate') {
                    return;
                }

                const languageIds = Array.from(
                    modal.querySelectorAll('input[name="autotranslate-languages[]"]:checked')
                ).map((input) => parseInt(input.value, 10));

                if (languageIds.length === 0) {
                    Notification.warning(payload.data.noLanguagesLabel || 'Please select at least one target language.');
                    return;
                }

                this.setButtonLoading(button, true);
                modalEvent.target.disabled = true;
                modalEvent.target.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ${this.escapeHtml(payload.data.loadingLabel)}`;

                try {
                    const translateResponse = await new AjaxRequest(TYPO3.settings.ajaxUrls.autotranslate_record_translation_translate)
                        .post({
                            table,
                            uid,
                            languages: languageIds,
                        });
                    const translatePayload = await translateResponse.resolve();

                    if (!translatePayload.success) {
                        Notification.error(translatePayload.message || 'Translation failed.');
                        this.setButtonLoading(button, false);
                        modal.hideModal();
                        return;
                    }

                    Notification.success(translatePayload.message || 'Translation started.');
                    this.setButtonLoading(button, false);
                    modal.hideModal();
                    window.location.reload();
                } catch (error) {
                    Notification.error(error?.message || 'Translation failed.');
                    this.setButtonLoading(button, false);
                    modal.hideModal();
                }
            });

            modal.addEventListener('typo3-modal-hidden', () => {
                if (button.dataset.loading === '1') {
                    this.setButtonLoading(button, false);
                }
            }, { once: true });
        } catch (error) {
            Notification.error(error?.message || 'Translation could not be prepared.');
            this.setButtonLoading(button, false);
        }
    }

    renderLanguageSelection(data) {
        const languages = data.languages.map((language) => {
            const status = language.translated
                ? html`<small class="text-body-secondary d-block">${payloadValue(data, 'existingTranslationLabel', 'Existing translation will be updated')}</small>`
                : '';

            return html`
                <label class="form-check mb-2">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        name="autotranslate-languages[]"
                        value="${parseInt(language.id, 10)}"
                        ?checked="${language.selected}"
                    >
                    <span class="form-check-label">
                        ${language.title}
                        ${status}
                    </span>
                </label>
            `;
        });

        return html`
            <div class="mb-3">${data.modalDescription}</div>
            <div class="t3js-autotranslate-language-list">${languages}</div>
        `;
    }

    setButtonLoading(button, isLoading) {
        if (!button) {
            return;
        }

        if (isLoading) {
            if (!button.dataset.originalHtml) {
                button.dataset.originalHtml = button.innerHTML;
            }
            button.dataset.loading = '1';
            button.disabled = true;
            button.classList.add('disabled');
            button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>';
            return;
        }

        button.dataset.loading = '0';
        button.disabled = false;
        button.classList.remove('disabled');
        if (button.dataset.originalHtml) {
            button.innerHTML = button.dataset.originalHtml;
        }
    }

    escapeHtml(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }
}

function payloadValue(object, key, fallback) {
    return Object.prototype.hasOwnProperty.call(object, key) ? object[key] : fallback;
}

export default new RecordTranslationTrigger();
