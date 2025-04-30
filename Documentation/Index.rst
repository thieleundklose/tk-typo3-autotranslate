===================
AutoTranslate
===================

:Extension key:
   autotranslate

:Package name:
   thieleundklose/autotranslate

:Language:
   de

:Rendered:
   |today|

----

Diese Dokumentation beschreibt die TYPO3-Extension "AutoTranslate".

----

**Inhaltsverzeichnis:**

.. toctree::
   :maxdepth: 2
   :titlesonly:

   Introduction/Index
   Installation/Index
   Configuration/Index
   Usage/Index

===================
Einführung
===================

Was ist AutoTranslate?
---------------------

AutoTranslate ist eine TYPO3-Extension, die automatische Übersetzungen von Seiten und Inhaltselementen über die DeepL API ermöglicht. Die Extension unterstützt TYPO3 v11.5 und höher.

Features
--------

* Automatische Übersetzung von Seiten und Inhaltselementen
* Integration mit der DeepL API
* Batch-Übersetzungsfunktion für große Mengen von Inhalten
* Unterstützung für wiederkehrende Übersetzungen
* Übersetzung von Dateireferenzen und deren Metadaten
* Benutzerfreundliche Oberfläche im TYPO3 Backend

===================
Installation
===================

Installation über Composer
-------------------------

Die empfohlene Installation erfolgt über Composer:

.. code-block:: bash

   composer require thieleundklose/autotranslate

Installation über das TYPO3 Extension Manager
-------------------------------------------

Alternativ können Sie die Extension auch über den TYPO3 Extension Manager installieren:

1. Öffnen Sie das TYPO3 Backend
2. Navigieren Sie zu "Admin Tools" > "Extension Manager"
3. Suchen Sie nach "autotranslate"
4. Klicken Sie auf "Import and Install"

===================
Konfiguration
===================

DeepL API-Schlüssel einrichten
-----------------------------

1. Registrieren Sie sich für einen DeepL API-Schlüssel unter https://www.deepl.com/pro-api
2. Öffnen Sie die TYPO3 Site-Konfiguration
3. Fügen Sie den DeepL API-Schlüssel unter dem Schlüssel `deeplAuthKey` ein

Sprachkonfiguration
------------------

1. Öffnen Sie die TYPO3 Site-Konfiguration
2. Konfigurieren Sie die Sprachen in der Site-Konfiguration
3. Für jede Sprache können Sie folgende Einstellungen vornehmen:
   * `deeplSourceLang`: Die Quellsprache für DeepL (z.B. "DE")
   * `deeplTargetLang`: Die Zielsprache für DeepL (z.B. "EN")

Übersetzbare Tabellen konfigurieren
---------------------------------

1. Öffnen Sie die TYPO3 Site-Konfiguration
2. Für jede Tabelle können Sie folgende Einstellungen vornehmen:
   * `autotranslate_[tabellenname]_enabled`: Aktiviert die automatische Übersetzung für die Tabelle
   * `autotranslate_[tabellenname]_languages`: Komma-getrennte Liste der Zielsprachen
   * `autotranslate_[tabellenname]_textfields`: Komma-getrennte Liste der zu übersetzenden Textfelder
   * `autotranslate_[tabellenname]_fileReferences`: Komma-getrennte Liste der zu übersetzenden Dateireferenzen

Zusätzliche Tabellen
-------------------

Sie können weitere Tabellen für die Übersetzung hinzufügen:

1. Öffnen Sie die Extension-Konfiguration
2. Fügen Sie die zusätzlichen Tabellen unter dem Schlüssel `additionalTables` hinzu (komma-getrennt)

===================
Verwendung
===================

Automatische Übersetzung
----------------------

Die Extension übersetzt automatisch neue und bearbeitete Inhalte, wenn:

1. Die Tabelle in der Site-Konfiguration aktiviert ist
2. Die Felder für die Übersetzung konfiguriert sind
3. Die Zielsprachen korrekt eingerichtet sind

Batch-Übersetzung
---------------

1. Öffnen Sie das Backend-Modul "AutoTranslate"
2. Wählen Sie die zu übersetzenden Elemente aus
3. Konfigurieren Sie die Übersetzungseinstellungen:
   * Zielsprache
   * Übersetzungsfrequenz (einmalig oder wiederkehrend)
   * Übersetzungszeitpunkt
4. Starten Sie die Übersetzung

Übersetzungsstatus
----------------

Im Backend-Modul können Sie:

* Den Status aller Übersetzungen einsehen
* Fehlgeschlagene Übersetzungen zurücksetzen
* Übersetzungsprotokolle einsehen

Hinweise zur Übersetzungsqualität
-------------------------------

* Die Übersetzungsqualität hängt von der DeepL API ab
* Überprüfen Sie die automatisch übersetzten Inhalte auf korrekte Fachbegriffe
* Bei Bedarf können Sie die Übersetzungen manuell anpassen

===================
Support
===================

Bei Fragen oder Problemen wenden Sie sich bitte an:

* E-Mail: typo3@thieleundklose.de
* Website: https://www.thieleundklose.de 