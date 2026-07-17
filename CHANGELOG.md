# Changelog

## [3.2.1] - 2026-07-17

### Fixes
- Cleared translated text fields when the corresponding source field is emptied, so removing content such as `bodytext` in the default language also removes the stale value from existing localized records, thanks to format-gmbh ([Issue #118](https://github.com/thieleundklose/tk-typo3-autotranslate/issues/118)).
- Removed a PHP 8 union return type from `FlashMessageUtility` to restore PHP 7.4 compatibility in the `2.x` release line, thanks to d-salerno ([Issue #115](https://github.com/thieleundklose/tk-typo3-autotranslate/issues/115)).

### Documentation
- Added documentation for excluding individual records from automatic translation, configuring additional record versus relation tables, copying fields without translation, and troubleshooting DeepL glossary synchronization including the `glossary_ready` database state.

## [3.2.0] - 2026-07-16

### Fixes
- Skipped direct and batch translations for records that no longer exist, preventing stale queue entries or deleted batch records from triggering null record access warnings and aborting batch runs, and added functional coverage for the regression, thanks to magicsunday ([Issue #109](https://github.com/thieleundklose/tk-typo3-autotranslate/issues/109) / [Pull request #110](https://github.com/thieleundklose/tk-typo3-autotranslate/pull/110)).
- Fixed cached DeepL translation roundtrips so restored cache entries are real `TextResult` instances and are no longer serialized back to `null`, preventing cached translations from being lost, thanks to magicsunday ([Pull request #111](https://github.com/thieleundklose/tk-typo3-autotranslate/pull/111)).
- Preserved the disabled/hidden state when localized inline or file reference records are re-linked to translated parent records, so hidden source references no longer become visible in translations, thanks to magicsunday ([Pull request #121](https://github.com/thieleundklose/tk-typo3-autotranslate/pull/121)).
- Fixed a TYPO3 v13 translation error in localized reference lookup by removing an obsolete TYPO3 v11 query execution switch from the v3 code path.
- Removed an unconditional CLI status line from the batch translation command to prevent cron jobs from sending unnecessary output mails, thanks to Johannes ([Pull request #117](https://github.com/thieleundklose/tk-typo3-autotranslate/pull/117)).
- Fixed richtext field detection to also accept integer truthy `enableRichtext` TCA values, preventing translated richtext content from getting broken HTML tags, thanks to schugabe ([Pull request #116](https://github.com/thieleundklose/tk-typo3-autotranslate/pull/116)).

## [3.1.1] - 2026-07-14

### Fixes
- Fixed glossary lookup for DeepL site language codes with uppercase or regional variants such as `EN`, `EN-GB`, `EN_US`, `DE-AT`, and `DE-CH`, so synchronized `deepltranslate-glossary` entries are found reliably, thanks to Tobias Hein ([Pull request #134](https://github.com/thieleundklose/tk-typo3-autotranslate/pull/134)).
- Fixed unnecessary DeepL translation requests on automatic DataHandler updates that only change unrelated fields such as `hidden`, `starttime`, `endtime`, or a content header while leaving image/file reference fields untouched, while keeping full translation for manual triggers and newly created localizations, thanks to magicsunday ([Pull request #122](https://github.com/thieleundklose/tk-typo3-autotranslate/pull/122)).

## [3.1.0] - 2026-07-12

### Features
- Added recursive localization and translation for configured relation tables. Nested inline/reference records are now localized with their translated parent record and their configured text fields are translated as part of the same run.
- Added site configuration fields for configured relation tables only when matching translatable text fields or file reference fields exist in TCA.

### Fixes
- Fixed additional relation table handling so existing localized child records are reused, re-linked to the localized parent record, and updated with their translation source fields instead of creating duplicate or orphaned relation records.
- Fixed batch translation work detection to include nested relation tables, so parent records with translatable child content are no longer skipped when the parent itself has no text fields to translate.
- Fixed text field filtering so only supported TCA `input` and `text` values are sent to DeepL, preventing numeric and non-text values from being translated accidentally.
- Fixed site configuration defaults so configured relation tables do not require their own enable checkbox before their text and file reference fields can be used.
- Fixed file reference field detection for modern TYPO3 `type=file` TCA fields on configured relation tables, so Content Blocks collection tables can expose and translate nested file reference fields such as image relations.

### Upgrade Notes
- No configuration key was renamed. The extension setting `additionalReferenceTables` remains compatible, but its visible label now says "Additional supported relation tables" because the setting covers inline/reference child tables, not only file references.
- Existing site configurations should be reviewed if numeric fields were listed as translatable text fields; these values are now ignored intentionally.

## [3.0.2] - 2026-07-06

### Fixes
- Deferred the DeepL API key usage check until a translation is actually attempted, while still validating the key before creating localized records. This avoids unnecessary usage endpoint calls for saves without translatable content and prevents empty localized records when the configured DeepL key is invalid or exhausted.
- Fixed the CLI batch translation summary to report failed translations based on the number of processed queue items instead of the requested run limit, thanks to xerc ([Pull request #105](https://github.com/thieleundklose/tk-typo3-autotranslate/pull/105)).

## [3.0.1] - 2026-07-05

### Fixes
- Fixed recursive queue filtering in the batch translation backend module so the selected recursion scope is applied reliably again in the TYPO3 v12, v13, and v14 module variants.
- Fixed creation of batch translation queue items in the backend module across TYPO3 v12, v13, and v14, including compatible handling of the scheduled translation date field and stable submission of the selected page context.

## [3.0.0] - 2026-06-04

### Breaking Changes
- Dropped TYPO3 v11 support. The extension now supports TYPO3 v12 and v13 only.
- Raised the supported PHP range to 8.1.0 - 8.4.99.

### Refactor
- Consolidated the backend context menu integration into a single `AutotranslateItemProvider` with shared JS callback handling.
- Kept a TYPO3 v12 legacy callback-module path for backward compatibility while TYPO3 v13+ uses the modern module alias.
- Added guard rails so the context menu entry only appears for source-language records, enabled tables, and sites with available target languages.

## [2.6.3] - 2026-07-06

### Fixes
- Deferred the DeepL API key usage check until a translation is actually attempted, while still validating the key before creating localized records. This avoids unnecessary usage endpoint calls for saves without translatable content and prevents empty localized records when the configured DeepL key is invalid or exhausted.
- Fixed the CLI batch translation summary to report failed translations based on the number of processed queue items instead of the requested run limit, thanks to xerc ([Pull request #105](https://github.com/thieleundklose/tk-typo3-autotranslate/pull/105)).

## [2.6.2] - 2026-07-05

### Fixes
- Fixed slug generation to respect excluded slug fields and guard missing `eval` configuration, preventing PHP warnings during localization, thanks to xerc and magicsunday ([Issue #108](https://github.com/thieleundklose/tk-typo3-autotranslate/issues/108) / [Pull request #112](https://github.com/thieleundklose/tk-typo3-autotranslate/pull/112)).
- Restored and stabilized recursive queue filtering in the batch translation module across TYPO3 v11, v12, and v13, including a dedicated recursion selector and clearer empty-state feedback when no queue entries exist for the selected page scope.

## [2.6.1] - 2026-06-14

### Fixes
- Fixed a fatal error with `deepltranslate-core` 6.x by extending the XClass configuration to implement the additional `ConfigurationInterface` methods introduced in the newer core version. This restores compatibility with `deepltranslate-glossary` 6.x and prevents backend/frontend crashes on page load, thanks to Tobias Hein ([Pull request #133](https://github.com/thieleundklose/tk-typo3-autotranslate/pull/133)).

## [2.6.0] - 2026-05-31

### Features
- Added backend triggers for autotranslating a single record, including a TYPO3 list module action with target language selection in a modal dialog and context menu entries in backend record and page menus. The context menu integration is available in TYPO3 v12 and newer; TYPO3 v11 does not support it.

### Fixes
- Fixed batch translation handling for translated MM/select relations so localized records are linked correctly instead of keeping obsolete default-language references.
- Fixed site configuration handling so disabled tables are no longer localized or remapped by accident during batch translation.
- Fixed reference synchronization for localized relations to respect whether a table is enabled in the current site configuration.
- Fixed an unnecessary flash message during page save when autotranslate is installed but no translation targets or languages are configured for the site.
- Fixed DeepL rate-limit handling so temporary `429 Too many requests` responses are no longer reported as an invalid API key.
- Cached DeepL API usage validation per request to avoid calling `getUsage()` for every translated record in a batch run.
- Improved DeepL error messaging so temporary API overload is surfaced as a warning instead of stopping the translation flow with a misleading key validation error.

### Stabilizations
- Improved relation remapping for translated records with `MM` and `MM_opposite_field` usage in TYPO3 v12/v13 setups.
- Added safer guards around translation of additional tables and their reference fields.

## [2.5.1] - 2026-02-08

### Fixes
- Fixed: The `autotranslate_languages` field was accidentally removed from the database, which prevented content elements from being translated.

## [2.5.0] - 2026-01-17

### Features
- Added comprehensive support for relation tables including ContentBlocks collections, `sys_file_reference`, and custom inline relations
- Autotranslate fields now available on all page doctypes (Standard, External URL, Shortcut, etc.)

## [2.4.1] - 2025-12-29

### Fixes
- Compatibility and security, thanks to Sebastian Mendel ([Pull request #97](https://github.com/thieleundklose/tk-typo3-autotranslate/pull/97)).
- Improved cache serialization to preserve array indices and prevent data corruption when mixing cached and fresh translations.
- Added defensive null checks in translation pipeline to prevent str_replace() errors with null values, thanks to Rainer Becker ([Issue #94](https://github.com/thieleundklose/tk-typo3-autotranslate/issues/94)).

## [2.4.0] - 2025-10-12

### Features
- Use caching to save quota and speed up translations ([Issue #55](https://github.com/thieleundklose/tk-typo3-autotranslate/issues/55)).

## [2.3.3] - 2025-10-08

### Fixes
- Corrected translation logic for handling with l10n_state fields, thanks to Rico Sonntag ([Issue #89](https://github.com/thieleundklose/tk-typo3-autotranslate/issues/89)).
- Drop duplicate assignment, thanks to Rico Sonntag ([Pull request #88](https://github.com/thieleundklose/tk-typo3-autotranslate/pull/88)).
- Determination of the API key in the hook in CLI mode corrected, tanks to Richard Krikler ([Pull request #86](https://github.com/thieleundklose/tk-typo3-autotranslate/pull/86)).

## [2.3.2] - 2025-10-05

### Fixes
- Content can be created in free mode without being automatically translated into other languages, thanks to Michael Henke and Wolfgang Wagner([Issue #69](https://github.com/thieleundklose/tk-typo3-autotranslate/issues/69)).

## [2.3.1] - 2025-07-23

### Stabilizations
- The DeepL source and target languages are now cached using the TYPO3 Cache Framework (“Autotranslate” cache), which ensures automatic cleanup when clearing the cache and no duplicate cache/data directories.

## [2.3.0] - 2025-07-18

### Features
- Log output and options for deleting logs in the backend module, log deactivation possible in the extension settings

## [2.2.5] - 2025-07-15

### Fixes
- (Un)Check all button is not working in TYPO3 v13, thanks to Rainer Becker ([Issue #57](https://github.com/thieleundklose/tk-typo3-autotranslate/issues/57))
- Missing required dependency php-http/discovery causes fatal error in non-Composer TYPO3 installation, thanks to Andrew ([Issue #72](https://github.com/thieleundklose/tk-typo3-autotranslate/issues/72))

## [2.2.4] - 2025-07-14

### Fixes
- Translation of the field title of pages and header of content elements: & becomes &amp;, thanks to Andreas Kessel ([Issue #49](https://github.com/thieleundklose/tk-typo3-autotranslate/issues/49))
- $localizedUid being null during record localization, thanks to Andreas Kessel ([Issue #39](https://github.com/thieleundklose/tk-typo3-autotranslate/issues/39))
- Optimization for determining the page ID due to php variable type check error, thanks to tot-bittner ([Issue #67](https://github.com/thieleundklose/tk-typo3-autotranslate/issues/67))

## [2.2.3] - 2025-06-19

### Stabilizations
- Added index for big page trees like 1000+ pages, thanks to Thomas Schöne ([Issue #64](https://github.com/thieleundklose/tk-typo3-autotranslate/issues/64) / [Pull request #63](https://github.com/thieleundklose/tk-typo3-autotranslate/pull/63))

## [2.2.2] - 2025-06-14

### Fixes
- TypeError on HTML Attribute detection, thanks to Wolfgang Wagner ([Issue #60](https://github.com/thieleundklose/tk-typo3-autotranslate/issues/60))

## [2.2.1] - 2025-06-09

### Fixes
- Backend authentication for CLI execution of batch translation to use the data handler to localize content elements

### Stabilizations
- API key check added to prevent site module breaks, the extension now provides feedback whether an API key is valid and whether the quota has been used up, thanks to Rainer Becker / saneinsane ([Issue #56](https://github.com/thieleundklose/tk-typo3-autotranslate/issues/56))

### Features
- Show usage and quota in backend module, thanks to Rainer Becker / saneinsane ([Issue #54](https://github.com/thieleundklose/tk-typo3-autotranslate/issues/54))

## [2.2.0] - 2025-06-04

### Features
- Added grid support for batch translations, thanks to Thomas Schöne ([Issue #42](https://github.com/thieleundklose/tk-typo3-autotranslate/issues/42) / [Pull request #41](https://github.com/thieleundklose/tk-typo3-autotranslate/pull/41))
- Preserve original values for specified fields in translated records, thanks to Thomas Schöne ([Issue #37](https://github.com/thieleundklose/tk-typo3-autotranslate/issues/37) / [Pull request #36](https://github.com/thieleundklose/tk-typo3-autotranslate/pull/36))
- Use PSR-14 event for TCA adjustments to avoid exceptions in production, thanks to Thomas Schöne ([Issue #47](https://github.com/thieleundklose/tk-typo3-autotranslate/issues/47) / [Pull request #46](https://github.com/thieleundklose/tk-typo3-autotranslate/pull/46))

## [2.1.3] - 2025-06-01

### Features
- Attribute translation for title tags of links in html content, thanks to t-bittner (Issue: https://github.com/thieleundklose/tk-typo3-autotranslate/issues/35)

### Stabilizations
- Remove deprecations
- Request processing of CLI queries for batch translations, thanks to bznovak (Issue: https://github.com/thieleundklose/tk-typo3-autotranslate/issues/40)

## [2.1.2] - 2025-04-13

### Fixes
- Stabilizations on TCA dependencies
- Support for 3rd party extensions with allowLanguageSynchronization like tt_address, thanks to Wolfgang Wagner (Issue: https://github.com/thieleundklose/tk-typo3-autotranslate/issues/26)

## [2.1.1] - 2025-03-25

### Fixes
- Keep line breaks in translations, thanks to Jahn Blechinger (Issue: https://github.com/thieleundklose/tk-typo3-autotranslate/issues/34)

## [2.1.0] - 2025-03-14

### Features
- Glossary support via compatibility with deepltranslate_glossary extension
- Possibility to define the DeepL API key globally for all sites in the extension configuration

### Fixes
- Stabilization backward compatibility
- Increased dependency on Official DeepL API version to support php 8.4

## [2.0.0] - 2025-02-27

### Added
- Editors can now also use BatchTranslation after approval via TYPO3 access rights
- PHP v8.4 support
- Update the DeepL PHP package for compatibility reasons

## [1.3.2] - 2025-02-26

### Fixes
- Add Missing BatchTranslation filename for usage in TYPO3 v13
- BatchTranslation created now with correct time offset from php / typo3 timezone settings

## [1.3.1] - 2025-02-20

### Fixes
- Handling of delete tca definition on additional tables
- Stabilizations, thanks to Rico Sonntag

## [1.3.0] - 2025-02-20

### Added
- Extension configuration to improve translation of 3rd party content (Check documentation for upgrade instructions)

## [1.2.3] - 2025-01-09

### Fixes
- Fixed: Empty database query caused SyntaxErrorException, thanks to Rico Sonntag

## [1.2.2] - 2024-10-27

### Fixes
- Stabilizations
- Fixed backend bugs when editing pages and content elements on pages without site configuration

## [1.2.1] - 2024-10-20

### Fixes
- Symfony command to perform a BatchTranslation in TYPO3 v13
- Number of translations per run limited to the maximum defined number

## [1.2.0] - 2024-10-15 (TYPO3 v13.4 LTS Release)

### Added
- Now with TYPO3 v13 support
- Compatibility of event listeners for PageTsConfig up to TYPO3 v13 established

## [1.1.1] - 2024-09-18

### Features
- Added the option to select a source language for DeepL translations in the page configuration. Otherwise, when translating individual words, the source language is often incorrectly recognized, thanks to Schorsch.

## [1.1.0] - 2024-09-09

### Features
- Added a new backend module "BatchTranslation", which enables automatic translation of pages and content elements. This feature streamlines the localization process, improving efficiency when managing multilingual content.
- Added a symfony command/scheduler task to process scheduled translations. This allows for automated handling of translations based on predefined plans, ensuring timely updates and improved workflow automation.

## [1.0.4] - 2024-05-22

### Fixes
- The news extension loaded condition did not work due to an incorrect extension key, thanks to SventB

## [1.0.3] - 2024-05-02

### Features
- Improved translations with tag_handling in DeepL

## [1.0.2] - 2024-04-21

### Fixes
- Bugfix a variable usage exception that was thrown caused by editing elements on the root page

## [1.0.1] - 2024-04-19

### Fixes
- Fixed Exception on DataHandler if no page or root page is selected

## [1.0.0] - 2024-03-22

### Fixes
- Translation mechanism restructured and errors in the translation process fixed

## [0.9.3] - 2024-02-26

### Fixes
- Fixed installation with Extension Manager from TER to use DeepL Vendor Files with TYPO3 autoloader (https://forge.typo3.org/issues/102443)
- PHP 8 bugfixes & translation stabilizations

## [0.9.2] - 2023-10-15

### Added
- TYPO3 v12 support

### Fixes
- Fixed field names in site configuration
- PHP 8 bugfixes & stabilizations
