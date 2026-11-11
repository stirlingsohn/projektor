# Projektor: Spezifikation MVP

Web-App zur Verwaltung von Musikprojekten und Kundenaufträgen.
Funktionsumfang ähnlich ClickUp, Bedienung schnell und elegant wie Todoist.

## Entscheidungen

| Thema | Entscheidung |
| --- | --- |
| Stack | Laravel (aktuelle Version) + Vue 3 + Inertia + Tailwind, MySQL |
| Hosting | cyon.ch Webhosting; JS-Build lokal, Upload per Git/SSH |
| Login | E-Mail + Passwort, Einladung per Link, Passwort vergessen |
| Dokumente | Rich-Text-Editor (Tiptap), gespeichert wird die letzte Version |
| Rechte | Owner (du), Kunde: Ansehen / Kommentieren / Bearbeiten |
| Zeiterfassung für Kunden | Pro Projekt sichtbar schaltbar, standardmässig verborgen |
| Gantt-Abhängigkeiten | Abhängige Aufgaben werden automatisch mitverschoben |
| Sprache | Deutsch, alle Texte über Übersetzungsdateien (Englisch später) |

## Datenmodell (Entwurf)

- **User**: Name, E-Mail, Passwort, Rolle (`owner` oder `client`)
- **Project**: Name, `parent_id` (Unterprojekte, beliebig tief),
  Farbe, archiviert, `client_can_see_time`
- **TaskList**: gehört zu Projekt, Name, Standardansicht
- **Status**: gehört zu Liste, Name, Farbe, Reihenfolge, `is_done`;
  Vorgabe: Offen, In Bearbeitung, In Review, Erledigt
- **Task**: gehört zu Liste, Titel, Beschreibung, Status, Start, Ende,
  Reihenfolge, erledigt am
- **TaskDependency**: Aufgabe A blockiert Aufgabe B (Ende-zu-Start)
- **TimeEntry**: Aufgabe, User, Start, Ende/Dauer, Notiz, manuell ja/nein
- **Document**: gehört zu Projekt, Titel, Inhalt (Tiptap-JSON)
- **File**: gehört zu Projekt (optional Aufgabe), Pfad, Name, Grösse, Typ
- **Comment**: gehört zu Aufgabe, User, Text
- **Membership**: User + Projekt *oder* Liste + Recht (`view`, `comment`, `edit`);
  Rechte auf ein Projekt gelten für alle Unterprojekte und Listen darin
- **Invitation**: E-Mail, Ziel (Projekt/Liste), Recht, Token, Ablaufdatum

## Meilensteine

1. **Grundgerüst**: Laravel + Vue + Inertia + Tailwind, Login, Layout mit
   linker Sidebar, Projektbaum anlegen/bearbeiten/löschen/verschieben
2. **Listen & Aufgaben (Listenansicht)**: Schnelleingabe, Status pro Liste
   konfigurierbar, Start-/Enddatum, Abhaken, Sortieren per Drag & Drop
3. **Kanban-Ansicht**: Spalten = Status, Drag & Drop
4. **Gantt-Ansicht**: Balken verschieben/verlängern, Abhängigkeiten
   ziehen, automatisches Mitverschieben, Zyklen verhindern
5. **Zeiterfassung**: Timer (immer nur einer aktiv, in der Kopfzeile
   sichtbar) und manuelle Einträge, Summen pro Aufgabe/Liste/Projekt
6. **Dokumente & Dateien**: Tiptap-Editor mit Autosave, Datei-Upload mit
   Vorschau für Bilder/Audio
7. **Einladungen, Rechte & Kommentare**: Einladungs-Mail, Laravel Policies
   für jede Aktion, Kommentare an Aufgaben
8. **Projekt-Dashboard**: Listenübersicht, nächste Aufgaben, erfasste Zeit,
   Dateianhänge
9. **Deployment auf cyon**: Document Root auf `public/`, `.env`, Migrations,
   Cronjob für `schedule:run`, SMTP über cyon

## Fertig, wenn ...

- alle Kernfunktionen aus den Meilensteinen 1 bis 8 funktionieren
- ein eingeladener Kunde nur sieht und darf, was seinem Recht entspricht
  (durch automatische Tests abgesichert)
- die App auf cyon läuft und die Daten dauerhaft gespeichert sind

## Später

- iOS-, Android- und Desktop-Version
- Englische Oberfläche
- Benachrichtigungen, Echtzeit-Zusammenarbeit, Versionsverlauf für Dokumente
