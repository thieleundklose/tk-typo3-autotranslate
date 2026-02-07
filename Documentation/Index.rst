===================
AutoTranslate
===================

:Extension key:
   autotranslate

:Package name:
   thieleundklose/autotranslate

:Version:
   |release|

:Language:
   en

:Rendered:
   |today|

----

This documentation describes the TYPO3 extension "AutoTranslate".

----

**Table of contents:**

.. toctree::
   :maxdepth: 2
   :titlesonly:

   Introduction/Index
   Installation/Index
   Configuration/Index
   Usage/Index

===================
Introduction
===================

What is AutoTranslate?
---------------------

AutoTranslate is a TYPO3 extension that provides automatic translations of pages and content elements via the DeepL API. The extension supports TYPO3 v13.4 LTS and v14.

Features
--------

* Automatic translation of pages and content elements
* Integration with the DeepL API
* Batch translation for large amounts of content
* Support for recurring translations
* Translation of file references and their metadata
* User-friendly backend module
* Translation caching to reduce API calls and costs
* Glossary support (via deepltranslate_glossary)
* Grid Elements support
* Site-specific API keys

===================
Installation
===================

Installation via Composer
-------------------------

The recommended installation method is via Composer:

.. code-block:: bash

   composer require thieleundklose/autotranslate

===================
Configuration
===================

Setting up the DeepL API key
-----------------------------

1. Register for a DeepL API key at https://www.deepl.com/pro-api
2. Open the TYPO3 Site Configuration
3. Enter the DeepL API key in the ``deeplAuthKey`` field

Language configuration
------------------

1. Open the TYPO3 Site Configuration
2. Configure the languages in the Site Configuration
3. For each language you can set the following:
   * ``deeplSourceLang``: The source language for DeepL (e.g. "DE")
   * ``deeplTargetLang``: The target language for DeepL (e.g. "EN")

Configuring translatable tables
---------------------------------

1. Open the TYPO3 Site Configuration
2. For each table you can configure the following:
   * ``autotranslate_[tablename]_enabled``: Enable automatic translation for the table
   * ``autotranslate_[tablename]_languages``: Comma-separated list of target languages
   * ``autotranslate_[tablename]_textfields``: Comma-separated list of text fields to translate
   * ``autotranslate_[tablename]_fileReferences``: Comma-separated list of file references to translate

Additional tables
-------------------

You can add more tables for translation:

1. Open the Extension Configuration
2. Add the additional tables under the ``additionalTables`` key (comma-separated)

===================
Usage
===================

Automatic translation
----------------------

The extension automatically translates new and edited content when:

1. The table is enabled in the Site Configuration
2. The fields for translation are configured
3. The target languages are correctly set up

Batch translation
---------------

1. Open the backend module "AutoTranslate"
2. Select the elements to be translated
3. Configure the translation settings:
   * Target language
   * Translation frequency (once or recurring)
   * Translation time
4. Start the translation

CLI Usage
---------

Translate queued items via command line:

.. code-block:: bash

   # Translate 1 item (default)
   vendor/bin/typo3 autotranslate:batch:run

   # Translate 10 items
   vendor/bin/typo3 autotranslate:batch:run 10

Translation status
----------------

In the backend module you can:

* View the status of all translations
* Reset failed translations
* View translation logs
* Clear the translation cache

Notes on translation quality
-------------------------------

* Translation quality depends on the DeepL API
* Review automatically translated content for correct technical terms
* You can manually adjust translations if needed

===================
Support
===================

For questions or issues please contact:

* E-Mail: typo3@thieleundklose.de
* Website: https://www.thieleundklose.de
