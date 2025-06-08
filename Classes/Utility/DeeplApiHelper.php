<?php
declare(strict_types=1);

namespace ThieleUndKlose\Autotranslate\Utility;

use DeepL\Translator;
use DeepL\AuthorizationException;
use DeepL\DeepLException;

class DeeplApiHelper
{
    /**
     * Prüft, ob der DeepL API-Key gültig ist und gibt optional die Usage zurück.
     *
     * @param ?string $apiKey
     * @return array ['isValid' => bool, 'usage' => \DeepL\Usage|null, 'error' => string|null]
     */
    public static function checkApiKey(?string $apiKey): array
    {
        if (!$apiKey) {
            return [
                'isValid' => false,
                'usage' => null,
                'error' => null,
            ];
        }

        try {
            $translator = new Translator($apiKey);
            $usage = $translator->getUsage();
            return [
                'isValid' => true,
                'usage' => $usage,
                'error' => null,
            ];
        } catch (AuthorizationException $e) {
            return [
                'isValid' => false,
                'usage' => null,
                'error' => $e->getMessage(),
            ];
        } catch (DeepLException $e) {
            return [
                'isValid' => false,
                'usage' => null,
                'error' => 'DeepL error: ' . $e->getMessage(),
            ];
        } catch (\Throwable $e) {
            return [
                'isValid' => false,
                'usage' => null,
                'error' => 'Unexpected error: ' . $e->getMessage(),
            ];
        }
    }
}
