# <a href=https://codekoch.github.io/challengebuilder/>Challenge Builder 🕹️</a>

Der **Challenge Builder** ist ein Web-Tool für Lehrkräfte, um im Handumdrehen aus normalen Schulbuchaufgaben interaktive, digitale (Retro-Terminal) Challenges für den Unterricht zu erstellen. Das Tool generiert daraus eine voll funktionsfähige, eigenständige HTML-Spieldatei, die offline bei den Schülern im Browser läuft.

## ✨ Features

*   **KI-gestützte Erstellung:** Nutze eine beliebige KI (ChatGPT, Claude, etc.), um Fotos von Schulbuchaufgaben direkt in das passende JSON-Format umzuwandeln. Ein optimierter System-Prompt ist direkt im Builder integriert.
*   **Retro-Terminal Design:** Die fertigen Challenges haben einen coolen Hacker-Look mit Scanlines und VT323-Font.
*   **Stand-Alone HTML:** Das generierte Spiel ist eine einzige `.html` Datei. Keine Installation, keine Datenbank für das Grundspiel nötig. Ideal zum Teilen via AirDrop, Moodle oder Schul-Cloud.
*   **Mathematik-Support:** Volle Unterstützung für physikalische Formeln und Gleichungen durch integriertes MathJax (LaTeX).
*   **Punktesystem & Wettkampf:** 
    * Startkapital: +1000 Punkte pro Aufgabe.
    * Zeitdruck: -2 Punkte pro Sekunde Bedenkzeit.
    * Fehler: -200 Punkte für falsche numerische Eingaben, -500 Punkte für falsche Multiple-Choice Antworten.
    * Hilfesystem: Progressive Tipp-Kosten (Tipp 1: -100 Pkt, Tipp 2: -200 Pkt, etc.).
    * *Hard-Limit: Der Punktestand fällt niemals unter -99999.*
*   **Anti-Cheat Schutz:** Die Lösungen sind im Quellcode der generierten HTML-Datei Base64-verschlüsselt und obfuskiert.
*   **Lehrer-Modus (Cheat):** Mit der Tastenkombination `Alt + S` kann sich die Lehrkraft die Lösung jederzeit im Spiel als Klartext einblenden lassen.
*   **Optionales Live-Leaderboard:** Über das mitgelieferte PHP-Skript (`leaderboard.php`) lässt sich mit wenig Aufwand ein serverloses Echtzeit-Leaderboard für die ganze Klasse aktivieren!

---

## 🚀 Anleitung: Wie erstelle ich eine Challenge?

### 1. KI füttern (JSON generieren)
1. Öffne die `index.html` (Challenge Builder) im Browser.
2. Klicke auf **"System-Prompt für KI anzeigen / kopieren"** und kopiere den Text.
3. Öffne eine KI deiner Wahl (z.B. ChatGPT).
4. Sende der KI den kopierten Prompt **zusammen mit einem abfotografierten Arbeitsblatt oder einer Schulbuchaufgabe**.
5. Die KI wird die Aufgabe formatieren und dir reinen JSON-Code zurückgeben.

### 2. Challenge "bauen"
1. Kopiere den gesamten JSON-Code, den die KI generiert hat.
2. Füge ihn in das große Textfeld im Challenge Builder (`index.html`) ein.
3. *(Optional)* Trage im Feld **"Leaderboard Server-URL"** die URL zu deinem PHP-Skript ein (siehe unten), falls du ein Live-Rating für die Klasse möchtest.
4. Klicke auf **"Spiel generieren & Herunterladen"**.
5. Der Builder "kompiliert" nun das Spiel, verschlüsselt die Lösungen und lädt die fertige HTML-Spieldatei (z. B. `halleffekt_challenge.html`) herunter.

### 3. Spielen!
Gib die heruntergeladene Datei nun einfach an deine Schüler weiter. Sobald sie diese Datei im Browser öffnen, startet die Challenge lokal auf ihrem Gerät!

---

## 🏆 Live Leaderboard einrichten (Optional)

Wenn du im Unterricht einen echten Wettkampf starten willst, bei dem die Schüler in Echtzeit sehen, auf welchem Platz sie aktuell stehen, kannst du als Lehrkraft das beiliegende PHP-Skript nutzen.

**Voraussetzung:** Du benötigst dazu einen sehr einfachen Webspace mit PHP-Unterstützung (z.B. ALL-INKL, Strato, IONOS oder einen eigenen Schulserver).

1. Lade die Datei `leaderboard.php` auf deinen Webserver hoch.
2. Trage beim Erstellen des Spiels im Challenge Builder die absolute URL zu dieser Datei in das Eingabefeld ein (z. B. `https://deine-schule.de/mathe/leaderboard.php`).
3. **Fertig!** Wenn die Schüler das Spiel öffnen, sendet das HTML-Dokument nun alle 3 Sekunden den Rekord an den Server und zeigt der Klasse live den aktuellen Platz an.

### Besonderheiten des Leaderboards:
* **Serverless Storage:** Es wird **keine MySQL Datenbank** benötigt! Alles läuft komplett über ressourcenschonende `.json` Dateien, die das Skript für jedes Thema selbstständig in seinem Ordner anlegt.
* **Auto-Garbage Collection:** Wird ein Spiel nicht mehr gespielt (kein Traffic für > 45 Sekunden), löscht das PHP-Skript die temporäre Daten-Datei eigenständig. Es bleibt kein Datenmüll auf deinem Server zurück.
* **Lehrer-Live-Dashboard:** Rufst du die URL zu deiner `leaderboard.php` einfach normal ohne weitere Parameter im Browser auf, erhältst du eine sich **automatisch aktualisierende Live-Übersicht** über alle derzeitigen Challenges, die Menge der Spieler und deren Punktestände!

## 🛠️ Technologien
* **Frontend:** Vanilla HTML5, CSS3, JavaScript (ES6)
* **Mathematik-Rendering:** MathJax 3 (wird per CDN im Spiel geladen)
* **Backend (für Leaderboard):** PHP 7.4 oder höher
