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
                'charactersLeft' => 0,
                'error' => null,
            ];
        }

        try {
            $translator = new Translator($apiKey);
            $usage = $translator->getUsage();
            $charactersLeft = null;
            if (is_object($usage) && isset($usage->character)) {
                $charactersLeft = $usage->character->limit - $usage->character->count;
            }
            return [
                'isValid' => true,
                'usage' => $usage,
                'charactersLeft' => $charactersLeft,
                'error' => null,
            ];
        } catch (AuthorizationException $e) {
            return [
                'isValid' => false,
                'usage' => null,
                'charactersLeft' => 0,
                'error' => $e->getMessage(),
            ];
        } catch (DeepLException $e) {
            return [
                'isValid' => false,
                'usage' => null,
                'charactersLeft' => 0,
                'error' => 'DeepL error: ' . $e->getMessage(),
            ];
        } catch (\Throwable $e) {
            return [
                'isValid' => false,
                'usage' => null,
                'charactersLeft' => 0,
                'error' => 'Unexpected error: ' . $e->getMessage(),
            ];
        }
    }
}
