# Zoho CRM Export — Anleitung

**Zweck:** Wir brauchen den Zoho-Export nicht für die Inhalte (Brevo ist aktueller Stand), sondern für die **Strukturanalyse**: welche Custom-Fields, Picklist-Werte, Tag-Vokabular und Listen-Definitionen waren in Zoho gepflegt? Daraus leiten wir das CRM-Schema ab.

**Zielort:** Bitte alle Exporte unter `/var/www/docs/zoho-export/` ablegen (Verzeichnis lege ich an, sobald die Dateien da sind).

## 1. Modul-Daten exportieren

Du bist im richtigen Dialog („Daten exportieren"). Die deutschen Modul-Namen entsprechen:

| Zoho (de) | Lastenheft (engl.) | Brauchen? |
|---|---|---|
| **Kontakte** | Contacts | ✓ Pflicht |
| **Firmen** | Accounts | ✓ Pflicht |
| **Leads** | Leads | ✓ Soll (falls noch gepflegt) |
| **Aufgaben** | Tasks | optional (nur falls Aktivitäten-Inhalte relevant sind) |
| **Notizen** | Notes | optional |
| Anrufe / Calendly Events / Formulare / Benutzer | — | **nicht nötig** |

Pro Modul einzeln exportieren:
- Format: **CSV**
- Felder: **alle Felder** auswählen (auch ungenutzte Custom-Fields)
- Encoding: UTF-8

Zoho schickt Dir den Export per Mail als ZIP-Link. Entpacken und in `/var/www/docs/zoho-export/` reinkopieren als:
- `kontakte.csv`
- `firmen.csv`
- `leads.csv`
- `aufgaben.csv` (optional)
- `notizen.csv` (optional)

## 2. Field-Definitions exportieren (am wichtigsten!)

**Settings** → **Customization** → **Modules and Fields** → jedes Modul (Contacts, Accounts) öffnen:
- Spalten-Liste zeigt alle Felder mit Typ, Picklist-Werten, Pflicht-Marker
- Oben rechts oder über das Drei-Punkt-Menü: **„Fields as PDF"** oder **„Field Report"** exportieren

Falls Zoho keinen Direkt-Export der Field-Definitions anbietet:
- Mache einfach **Screenshots** der Feldliste pro Modul
- Bei Picklist-Feldern: Screenshot der Picklist-Werte-Tabelle
- Ablage als `fields-contacts.png`, `fields-accounts.png`, etc.

## 3. Tags und Listen — brauchst Du NICHT separat zu exportieren

**Tags:** sind in Zoho **automatisch eine Spalte im Kontakt-CSV** (Spaltenname „Tag" oder „Tags", mehrere durch Komma/Semikolon getrennt). Eine separate Tag-Verwaltungs-Export-Funktion gibt es in Zoho meist nicht (oder nur in Enterprise+). Ich lese das Vokabular per SQL aus der `kontakte.csv` raus.

**Listen** in Zoho-Sprech sind **„Listenansichten" / „Custom Views"** — gespeicherte Filter pro Modul, keine Marketing-Listen wie in Brevo. Die echten Marketing-Listen sind in Brevo, von dort kommen die Listen-Definitionen. Zoho-Custom-Views brauchst Du nicht zu exportieren.

Falls Du trotzdem dokumentieren willst:
- Modul öffnen (Kontakte/Firmen/Leads) → oben links Dropdown „Alle Kontakte" → „Listenansichten verwalten"
- Screenshot reicht → optional in `/var/www/docs/zoho-export/listenansichten/`

## 4. Was ich daraus lese

Mit diesen Files baue ich:

| Aus Zoho-Export | Im CRM-Schema |
|---|---|
| Custom-Field-Liste (Contacts) | `crm_kontakte`-Spalten |
| Picklist-Werte | Enum/Vokabular-Tabellen |
| Field-Typen (Text/Number/Date/etc.) | MySQL-Spaltentypen |
| Tag-Liste mit Häufigkeiten | `crm_tags` Seed |
| Listen-/View-Definitionen | `crm_segmente` als Vorlagen |
| Account-Felder | `crm_firmen`-Spalten |

## 5. Was Du NICHT exportieren musst

- E-Mail-Templates, Workflow-Rules, Automations → kommt nicht ins CRM
- Activity-Streams im Detail → wir bauen die Zeitlinie neu aus Brevo-Events
- Reports / Dashboards → bauen wir selbst auf der neuen DB

## 6. Wenn etwas fehlt

Falls Du einen Field-Definitions-Report nicht hinbekommst: kein Drama, dann reicht der Daten-Export — ich lese die Struktur aus den CSV-Headers + Beispielwerten ab. Picklist-Werte sehe ich dann anhand der vorkommenden Distinct-Values.
