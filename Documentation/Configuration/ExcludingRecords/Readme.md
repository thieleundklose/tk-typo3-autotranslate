# Excluding records from automatic translation

AutoTranslate adds record-level control fields to pages, content elements and configured additional record tables.

## Exclude one record

Enable **Exclude from autotranslation** on a default-language record to skip automatic translation for that record.

This field is available for:

- `pages`
- `tt_content`
- tables configured as **Additional supported record tables**

The field is only shown on source/default-language records. Existing translated records are not removed when the source record is excluded; the setting only prevents further automatic translation runs for that source record.

## Limit the target languages for one record

Use **Translate automatically to selected languages** to restrict automatic translation for a record to specific target languages.

If no record-specific language selection is stored, AutoTranslate falls back to the configured table defaults from the site configuration.

## Additional relation tables

Tables configured as **Additional supported relation tables** are inline/reference child tables. They are translated through their parent record and therefore do not get their own **Exclude from autotranslation** or **Translate automatically to selected languages** fields.

For relation tables, AutoTranslate only adds **Last execution of autotranslation**. To prevent a relation child from being translated automatically, exclude the parent record or remove the relation table or field from the AutoTranslate configuration.

## Required database fields

For TYPO3 v12 and older, custom tables need the required AutoTranslate fields in the site package SQL schema.

Additional record tables:

```sql
CREATE TABLE tx_table_name (
    autotranslate_exclude tinyint(4) DEFAULT '0' NOT NULL,
    autotranslate_languages varchar(255) DEFAULT NULL,
    autotranslate_last int(11) DEFAULT '0' NOT NULL
);
```

Additional relation tables:

```sql
CREATE TABLE tx_table_name_item (
    autotranslate_last int(11) DEFAULT '0' NOT NULL
);
```

Run the TYPO3 database compare after adding the fields.
