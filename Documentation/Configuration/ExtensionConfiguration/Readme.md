# Extension Settings

Here you can define DeepL API Key and which 3rd party content should be supported.

Use "Additional supported record tables" for regular records such as news or address records.
Use "Additional supported relation tables" for inline/reference child tables such as `sys_file_reference` or Mask item tables.
For nested Content Blocks collections, add the generated collection tables to "Additional supported relation tables".
If one of those relation tables contains file fields, the site configuration exposes a matching `FileReferences` field
where the file relation columns can be selected, for example `level_three_image`.

**After adding tables, a database compare must be performed using the install tool**

![DeepL](../../Images/ExtensionConfiguration.png)

## Additional supported record tables

Use **Additional supported record tables** for records that can be translated directly, for example `tx_news_domain_model_news`.

These tables get the record-level AutoTranslate control fields:

- `autotranslate_exclude`
- `autotranslate_languages`
- `autotranslate_last`

## Additional supported relation tables

Use **Additional supported relation tables** for inline/reference child tables, for example `sys_file_reference`, Mask item tables or generated Content Blocks collection tables.

Relation tables are translated through their parent record. They only get `autotranslate_last`; record-level exclusion and language selection stay on the parent record.

## Fields copied without translation

The extension setting **Fields to be copied into translated records** copies configured field values from the source record to the localized record without sending them to DeepL.

The default is:

```text
pi_flexform, hidden
```

Use this for fields that should stay synchronized but should not be translated, for example technical configuration fields or visibility flags.

## HTTP proxy for DeepL requests

AutoTranslate uses the TYPO3 HTTP proxy setting for DeepL API requests.

Configure the proxy in `system/additional.php`:

```php
$GLOBALS['TYPO3_CONF_VARS']['HTTP']['proxy'] = 'https://user:pass@proxy.example.org:3128';
```

If the TYPO3 proxy setting is configured as an array, AutoTranslate uses the `https` proxy first and falls back to the `http` proxy:

```php
$GLOBALS['TYPO3_CONF_VARS']['HTTP']['proxy'] = [
    'https' => 'https://user:pass@proxy.example.org:3128',
    'http' => 'http://user:pass@proxy.example.org:3128',
];
```

The proxy is used for API key validation, DeepL language loading, glossary access and translation requests.

## Global file metadata translation

AutoTranslate can translate global FAL metadata records from `sys_file_metadata`.

This is intentionally configured in the extension settings instead of the site configuration, because FAL metadata belongs to files in storages and is not reliably bound to a single page tree or site.

### Enable file metadata translation

Enable **Enable file metadata translation** to allow automatic translation of default-language `sys_file_metadata` records.

Only records with `sys_language_uid = 0` trigger automatic translation. Localized metadata records keep their own values and do not trigger another translation run.

### File metadata fields to translate

Use **File metadata fields to translate** to define which `sys_file_metadata` fields should be sent to DeepL.

Default:

```text
alternative,title,description
```

Only TCA fields of type `input` and `text` are translated. Technical fields and unsupported field types are ignored.

### File metadata DeepL language mapping

Use **File metadata DeepL language mapping** to map TYPO3 `sys_language_uid` values to DeepL language codes.

The mapping must include the default language `0`, because this is used as the DeepL source language:

```text
0=DE,1=EN-US,2=FR
```

DeepL source languages do not support regional variants. If a regional code is configured for language uid `0`, for example `0=EN-US` or `0=PT-BR`, AutoTranslate automatically sends the base code `EN` or `PT` as `source_lang`. Target language codes are kept unchanged.

In this example:

- `0=DE` means default `sys_file_metadata` records are sent to DeepL as German source content.
- `1=EN-US` means localized metadata records with `sys_language_uid = 1` are translated to English (US).
- `2=FR` means localized metadata records with `sys_language_uid = 2` are translated to French.

If a selected target language has no mapping entry, AutoTranslate skips that language and writes a warning to the log when debug logging is enabled.

### File metadata default target languages

Use **File metadata default target languages** to preselect target languages for new default-language file metadata records.

Example:

```text
1,2
```

The values are TYPO3 language UIDs. Language UID `0` is ignored as a target.

Editors can still adjust the selected target languages in the file metadata edit form.

### Database fields

The feature adds AutoTranslate control fields to `sys_file_metadata`:

```sql
CREATE TABLE sys_file_metadata (
    autotranslate_exclude tinyint(4) DEFAULT '0' NOT NULL,
    autotranslate_languages varchar(255) DEFAULT NULL,
    autotranslate_last int(11) DEFAULT '0' NOT NULL,
);
```

Run the TYPO3 database compare after enabling or updating the extension.

### How to test

1. Configure a global DeepL API key in the AutoTranslate extension settings.
2. Enable file metadata translation.
3. Configure the language mapping, for example `0=DE,1=EN-US`.
4. Configure default target languages, for example `1`.
5. Open the TYPO3 Filelist module and edit a file metadata record in the default language.
6. Fill one of the configured fields, for example `alternative`.
7. Keep the target language selected and save the default metadata record.
8. Open the target language metadata record from the language dropdown in the Filelist module.
9. Check that the configured fields have been translated and `autotranslate_last` has been updated.

### Important: TYPO3 v12 and older

In older TYPO3 versions you have to provide the required fields for the 3rd party tables in your site package via ext_tables.sql.

## Example sql schema for additional tables

Except for tx_news_domain_model_news, here we already provide the sql schema.

```
CREATE TABLE tx_table_name (
    autotranslate_exclude tinyint(4) DEFAULT '0' NOT NULL,
    autotranslate_languages varchar(255) DEFAULT NULL,
    autotranslate_last int(11) DEFAULT '0' NOT NULL
);
```

## Example sql schema for relation tables

Except for sys_file_reference, here we already provide the sql schema.

```
CREATE TABLE tx_table_name_item (
    autotranslate_last int(11) DEFAULT '0' NOT NULL
);
```

> [!NOTE] If fields from third-party extensions that have allowLanguageSynchronization enabled (tt_address) are to be translated, the backend cache must be cleared once after editing the site configuration.
