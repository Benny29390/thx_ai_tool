# 07 · LAMS-Migration ins KI-Tool

> Zeitraum: 19.–20.05.2026 · Herkunft: SSH /var/www · 9 Prompts · 3 Sinnabschnitte
>
> Sinnabschnitte sind mit `## ▸` markiert. Pro Abschnitt: Anforderung gegen Ist-Stand im KI-Tool prüfen, offene/abweichende Punkte dokumentieren, dann nächster Abschnitt.

## ▸ 1. LAMS-Daten verarbeiten, Migrationsplan & Sidebar-Menüpunkt

**[P01 · 19.05. 17:20]**

Ich möchte mein bisheriges Linkaufbau-Management-System (LAM-System, liebevoll LAMS genannt) im KI-Tool integrieren. Du findest unter var/www/lams_modul_alt/lam-prototyp die bisherigen Daten, bitte verarbeiten und Plan aushecken.

---

**[P02 · 19.05. 17:42]**

Klingt gut. Benny sagt: "ja soll aml weitermachen"

---

**[P03 · 19.05. 17:52]**

Ja kannst Du mal implementieren. Wann sehen wir was? Menüpunkt "LAM-System" links in der Sidebar anlegen bitte, sobald es soweit ist

---

## ▸ 2. Optik-Abgleich alt/neu, Screenshot-Vergleich, Service-Hänger

**[P04 · 19.05. 18:16]**

Es sieht natürlich alles komplett anders aus als im bestehenden system. Machen wir erst mit allen funktionen weiter und passen dann an oder willst du erst sehen, was gravierend anders ist? Dann lade ich Dir screenshots vorher/nachher hoch

---

**[P05 · 19.05. 19:24]**

Kannst Du Bilder nicht verarbeiten? Warum dauert das so lange?

---

**[P06 · 19.05. 19:52]**

Irgendwas hängt im Service, seit LAMS aktiv ist. Kannst Du das stoppen?

---

**[P07 · 19.05. 20:31]**

benny hats repariert, können wir weitermachen mit den screenshots?

---

## ▸ 3. UI-Styleguide übernehmen (Design-Reference-Paket)

**[P08 · 19.05. 21:28]**

In meinem Home-Verzeichnis (/home/deploy/) liegt jetzt
lam-design-reference.zip. Bitte ins aktuelle Projekt nach
docs/design-reference/ verschieben und entpacken. Anschließend
docs/design-reference/lam-styleguide/README.md und
docs/design-reference/lam-styleguide/lam-ui-styleguide.md lesen.

Das ist die verbindliche UI-Spezifikation für alle LAM-Bereiche. Übernimm
Thoxan-Farbpalette aus tailwind.config.js, Frutiger-Schrift, das
dreigeteilte Header-Layout (dunkelblauer Admin-Streifen + weiße
Modul-Navigation + Page-Header) und das Filter-Chip- plus
Tabellen-Pattern. Passe die migrierten Linkprofil- und Linkquellen-Pool-
Seiten entsprechend an. Logo aus assets/ und die beiden
Frutiger-Webfonts aus fonts/ ins öffentliche Asset-Verzeichnis der
Migration kopieren.

---

**[P09 · 20.05. 12:19]**

weiter machen bitte

---
