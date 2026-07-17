# Glossary support

AutoTranslate can use glossaries from `deepltranslate_glossary` when glossary support is enabled in the site configuration.

## Requirements

- Install and configure `deepltranslate_core` and `deepltranslate_glossary`.
- Create the glossary in a sysfolder inside the same site tree as the translated page.
- Configure `deeplSourceLang` on the default site language.
- Configure `deeplTargetLang` on every target site language.
- Enable **Enable the use of the DeepL Translate glossary** in the site configuration.
- Synchronize the glossary with DeepL.

DeepL glossaries require both a source and a target language. If the default site language has no `deeplSourceLang`, AutoTranslate cannot attach a glossary to the translation request.

## Language codes

`deepltranslate_glossary` stores glossary language values as lowercase language codes. Target languages are stored without regional variants, for example `en` instead of `EN-GB` or `EN-US`.

Current AutoTranslate versions normalize the configured DeepL language codes before querying the glossary table. If a glossary is still not found, check that the local `source_lang` and `target_lang` values match the expected source and target language pair.

## Troubleshooting

If glossary terms are not applied, check the following:

- The glossary sysfolder is below the same site root as the translated record.
- The glossary was synchronized after editing the glossary entries.
- The glossary is marked as ready in DeepL.
- The local `tx_deepltranslate_glossary` row has `glossary_ready = 1`.
- The local `glossary_id` still exists in the connected DeepL account.
- The source and target language values in the database match the site language configuration after normalization.

Useful database check:

```sql
SELECT uid, pid, glossary_id, glossary_ready, source_lang, target_lang, glossary_lastsync
FROM tx_deepltranslate_glossary
ORDER BY uid DESC;
```

If `glossary_ready` is `0`, the glossary exists locally but DeepL has not marked it as usable yet. Re-synchronize the glossary and check the glossary directly in the DeepL account.

With DeepL Free/API test accounts, stale synced glossaries or account limits can interfere when testing multiple language combinations. If the local state looks correct but DeepL does not apply the expected glossary, delete stale test glossaries in the DeepL account and synchronize again.
