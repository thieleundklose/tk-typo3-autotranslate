<?php

declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ThieleUndKlose\Autotranslate\Hooks\DataHandler as AutotranslateDataHandlerHook;
use ThieleUndKlose\Autotranslate\Service\RecordTranslationConfigurationService;
use ThieleUndKlose\Autotranslate\Utility\Records;
use ThieleUndKlose\Autotranslate\Utility\Translator;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[AsController]
class RecordTranslationAjaxController
{
    public function __construct(
        private readonly RecordTranslationConfigurationService $recordTranslationConfigurationService,
    ) {}

    public function languages(ServerRequestInterface $request): ResponseInterface
    {
        [$table, $uid] = $this->resolveRecordArguments($request);
        if ($table === '' || $uid <= 0) {
            return new JsonResponse([
                'success' => false,
                'message' => $this->translateLabel('record_translation.error.invalid_record'),
            ]);
        }

        $record = Records::getRecord($table, $uid);
        if ($record === null) {
            return new JsonResponse([
                'success' => false,
                'message' => $this->translateLabel('record_translation.error.invalid_record'),
            ]);
        }

        try {
            $configuration = $this->recordTranslationConfigurationService->getConfiguration($table, $record);
        } catch (\Throwable $exception) {
            return new JsonResponse([
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
        }

        if ($configuration === null) {
            return new JsonResponse([
                'success' => false,
                'message' => $this->translateLabel('record_translation.error.not_available'),
            ]);
        }

        $recordTitle = BackendUtility::getRecordTitle($table, $record, true) ?: ('#' . $uid);

        return new JsonResponse([
            'success' => true,
            'data' => [
                'modalTitle' => sprintf(
                    $this->translateLabel('record_translation.modal.title'),
                    $recordTitle
                ),
                'modalDescription' => $this->translateLabel('record_translation.modal.description'),
                'submitLabel' => $this->translateLabel('record_translation.modal.submit'),
                'cancelLabel' => $this->translateLabel('record_translation.modal.cancel'),
                'loadingLabel' => $this->translateLabel('record_translation.modal.loading'),
                'noLanguagesLabel' => $this->translateLabel('record_translation.error.no_languages'),
                'existingTranslationLabel' => $this->translateLabel('record_translation.modal.existing_translation'),
                'languages' => $configuration['languages'],
            ],
        ]);
    }

    public function translate(ServerRequestInterface $request): ResponseInterface
    {
        [$table, $uid] = $this->resolveRecordArguments($request);
        if ($table === '' || $uid <= 0) {
            return new JsonResponse([
                'success' => false,
                'message' => $this->translateLabel('record_translation.error.invalid_record'),
            ]);
        }

        $record = Records::getRecord($table, $uid);
        if ($record === null) {
            return new JsonResponse([
                'success' => false,
                'message' => $this->translateLabel('record_translation.error.invalid_record'),
            ]);
        }

        try {
            $configuration = $this->recordTranslationConfigurationService->getConfiguration($table, $record);
        } catch (\Throwable $exception) {
            return new JsonResponse([
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
        }

        if ($configuration === null) {
            return new JsonResponse([
                'success' => false,
                'message' => $this->translateLabel('record_translation.error.not_available'),
            ]);
        }

        $body = (array)$request->getParsedBody();
        $requestedLanguages = $body['languages'] ?? [];
        $languageIds = $this->recordTranslationConfigurationService->sanitizeRequestedLanguageIds(
            $configuration['languages'],
            $requestedLanguages
        );
        if ($languageIds === []) {
            return new JsonResponse([
                'success' => false,
                'message' => $this->translateLabel('record_translation.error.no_languages'),
            ]);
        }

        try {
            $translator = GeneralUtility::makeInstance(
                Translator::class,
                $configuration['pageId']
            );
            AutotranslateDataHandlerHook::runWithSuspendedHook(static function () use ($translator, $table, $uid, $languageIds): void {
                $translator->translate($table, $uid, null, implode(',', $languageIds));
            });
        } catch (\Throwable $exception) {
            return new JsonResponse([
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
        }

        return new JsonResponse([
            'success' => true,
            'message' => $this->translateLabel('record_translation.success'),
        ]);
    }

    /**
     * @return array{0:string,1:int}
     */
    private function resolveRecordArguments(ServerRequestInterface $request): array
    {
        $queryParams = $request->getQueryParams();
        $body = (array)$request->getParsedBody();

        $table = (string)($body['table'] ?? $queryParams['table'] ?? '');
        $uid = (int)($body['uid'] ?? $queryParams['uid'] ?? 0);

        return [$table, $uid];
    }

    private function translateLabel(string $key): string
    {
        return $this->getLanguageService()->sL(
            'LLL:EXT:autotranslate/Resources/Private/Language/locallang_mod.xlf:' . $key
        ) ?: $key;
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
