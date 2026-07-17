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
