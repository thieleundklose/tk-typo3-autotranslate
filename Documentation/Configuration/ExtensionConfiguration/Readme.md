# Extension Settings

Configure the DeepL API Key and define which 3rd party content should be supported.

**After adding tables, run the database compare in the Install Tool**

![DeepL](../../Images/ExtensionConfiguration.png)

## Example SQL Schema for Additional Tables

Except for `tx_news_domain_model_news`, you need to provide the SQL schema in your site package.

```sql
CREATE TABLE tx_table_name_item (
    autotranslate_exclude tinyint(4) DEFAULT '0' NOT NULL,
    autotranslate_languages varchar(255) DEFAULT NULL,
    autotranslate_last int(11) DEFAULT '0' NOT NULL
);
```

## Example SQL Schema for Reference Tables

Except for `sys_file_reference`, you need to provide the SQL schema in your site package.

```sql
CREATE TABLE tx_table_name_item (
    autotranslate_last int(11) DEFAULT '0' NOT NULL
);
```

> [!NOTE]
> If fields from third-party extensions that have `allowLanguageSynchronization` enabled (e.g., tt_address) need to be translated, clear the backend cache once after editing the site configuration.
