Tragwerk ist eine Anwendung, die es ermöglicht, PHP-Anwendungen einfach zu hosten. Es kann
jeder Hosting-Anbieter genutzt werden, der VPS mit SSH-Zugang anbietet.

Die Projektkonfiguration soll möglichst einfach und intuitiv sein — über eine WebUI und eine
Konfigurationsdatei (siehe upsun) — jedoch mit XML als Konfigurationsformat.

Auf dem Zielsystem (dem VPS) läuft alles über Docker — vollautomatisiert.
Die gesamte Docker- bzw. Docker-Compose-Konfiguration wird anhand der XML-Datei generiert.
Als Application-Server in Docker kommt FrankenPHP zum Einsatz. Zusätzlich kommt Traefik als
Reverse-Proxy dazu, da es möglich sein muss, mehrere Anwendungen auf demselben VPS zu betreiben.
Offiziell unterstützen wir nur als Zielsystem auf dem VPS ausschließlich das was Docker selbst 
siehe: https://docs.docker.com/engine/install/ unterstützt.

Tech-Stack:
- PHP 8.5
- PostgreSQL
- RoadRunner
- Plates als Template-Engine
- Mezzio als Framework / Grundgerüst
- HTMX

Architekturentscheidungen (nicht aus dem Code ableitbar):
- Warum lange SSH-Arbeit als separater CLI-Prozess läuft statt direkt im Queue-Handler →
  TransactionalProcessor hält alles in einer Transaktion, uncommitted writes für andere
  Connections unsichtbar
- Warum kein SSE → RoadRunner PSR-7-Modell gibt komplette Response zurück, kein offener
  Stream möglich → htmx-Polling als Ersatz

Konventionen:
- DB-Mutationen laufen immer über Event → Listener, nie direkte Repo-Aufrufe im Handler
- Async-Arbeit: Queue-Message für Dispatch, eigener CLI-Prozess für Ausführung
- Kein Inline-JavaScript in HTML; stattdessen immer HTMX nutzen; falls nicht möglich,
  separate JS-Dateien

Entwicklungssetup:
- `make check` nach jeder PHP-Änderung pflicht
- Neue DB-Migration: `make db/migrations/new`
- Queue-Worker muss laufen für Setup-Jobs: `bin/cli worker:start default`
- Alles läuft via docker compose

Roadmap / Nächste Schritte (was kommt nach Setup?):
- XML-Konfigurationsdatei pro Projekt → Docker-Compose-Generierung (existiert teilweise bereits: GenerateDockerConfig)
- Deploy-Flow: Git-Repo klonen, Docker-Image bauen, Container starten
- Was der "Ready to deploy"-Status konkret bedeutet/freischaltet
