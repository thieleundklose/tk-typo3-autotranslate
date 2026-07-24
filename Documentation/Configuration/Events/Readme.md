# Events

AutoTranslate dispatches PSR-14 events for project-specific customizations.

## AfterTranslateEvent

`ThieleUndKlose\Autotranslate\Event\AfterTranslateEvent` allows post-processing of translated record values before AutoTranslate persists the translated record data.

The event is dispatched after DeepL results have been mapped back to record columns and after configured copy fields have been applied. It runs before AutoTranslate adds internal fields such as `l10n_state` and `autotranslate_last`.

Typical use cases:

* Normalize special characters returned by DeepL.
* Apply project-specific replacements.
* Clean up translated HTML or text fragments.
* Adjust translated values depending on table, record type or target language.

Example listener for TYPO3 table content where DeepL may translate the ASCII pipe `|` into the full-width pipe `｜`:

```php
<?php
declare(strict_types=1);

namespace Vendor\Extension\EventListener;

use ThieleUndKlose\Autotranslate\Event\AfterTranslateEvent;

final class NormalizeTranslatedTablePipesListener
{
    public function __invoke(AfterTranslateEvent $event): void
    {
        $sourceRecord = $event->getSourceRecord();

        if ($event->getTable() !== 'tt_content' || ($sourceRecord['CType'] ?? '') !== 'table') {
            return;
        }

        foreach ($event->getTranslatedColumns() as $columnName => $value) {
            if (!is_string($value)) {
                continue;
            }

            $event->setTranslatedColumn($columnName, str_replace('｜', '|', $value));
        }
    }
}
```

Register the listener in your extension's `Configuration/Services.yaml`:

```yaml
services:
  Vendor\Extension\EventListener\NormalizeTranslatedTablePipesListener:
    tags:
      - name: event.listener
        identifier: 'vendor-extension-normalize-translated-table-pipes'
        event: ThieleUndKlose\Autotranslate\Event\AfterTranslateEvent
```

The event exposes:

* `getTranslatedColumns()` and `setTranslatedColumns()`
* `hasTranslatedColumn()`, `getTranslatedColumn()`, `setTranslatedColumn()` and `unsetTranslatedColumn()`
* `getSourceRecord()`
* `getTargetLanguageUid()`
* `getTable()`
* `getLocalizedUid()`
* `getDeeplSourceLang()`
* `getDeeplTargetLang()`
