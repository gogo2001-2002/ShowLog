# ShowLog

IP-Symcon-Modul zum Auslesen der neuesten Symcon-Logdatei. Das Modul stellt die Fehler im konfigurierten Zeitraum als JSON sowie die Anzahl der gefundenen Fehlereinträge als Integer-Variable bereit.

## Funktionen

- Einstellbares Leseintervall von 5 bis 3600 Sekunden (Standard: 60 Sekunden)
- Einstellbarer Zeitraum von 1 bis 720 Stunden (Standard: 24 Stunden)
- Durchsucht ausschließlich die neueste Symcon-Logdatei
- Stellt die Fehler im Zeitraum als JSON in der Variable **Fehler im Log** bereit
- Stellt die Anzahl in der Integer-Variable **Anzahl der Fehlereinträge** bereit
- Bewahrt mehrzeilige Fehlermeldungen und Stacktraces
- Liest das Logverzeichnis über `IPS_GetLogDir()`

## Installation

1. Dieses Repository in der IP-Symcon-Verwaltungskonsole unter **Module Control** als Repository hinzufügen.
2. Das Modul **ShowLog** über **Instanz hinzufügen** anlegen.
3. In der Konfiguration Leseintervall und Zeitraum einstellen.

Die Instanz liest die Logdatei beim Anwenden der Konfiguration und anschließend nach dem gewählten Timer-Intervall.

## Variablen

| Variable | Typ | Inhalt |
| --- | --- | --- |
| Fehler im Log | String | JSON-Objekt mit Metadaten und Fehlerliste |
| Anzahl der Fehlereinträge | Integer | Anzahl der in der JSON-Ausgabe enthaltenen Einträge |

Die JSON-Ausgabe ist nicht als HTML formatiert. Die Stringvariable verwendet eine Textpräsentation, damit sie in einer Visualisierung weiterverarbeitet und dort nach Bedarf gestaltet werden kann.

## JSON-Format

Die Einträge in `errors` sind absteigend nach Zeitpunkt sortiert (neueste zuerst). Mehrzeilige Meldungen bleiben im Feld `message` erhalten.

```json
{
  "generatedAt": "2026-11-09T07:39:35+01:00",
  "periodHours": 6,
  "sourceFile": "logfile1794252155.log",
  "count": 2,
  "errors": [
    {
      "timestamp": "2026-11-09T06:52:29+01:00",
      "senderId": 42184,
      "sender": "PHPModule",
      "message": "Beispiel einer Fehlermeldung"
    },
    {
      "timestamp": "2026-11-09T03:21:53+01:00",
      "senderId": 21514,
      "sender": "PHPModule",
      "message": "Beispiel mit\nmehreren Zeilen"
    }
  ]
}
```

Felder des JSON-Objekts:

- `generatedAt`: Zeitpunkt der letzten Aktualisierung als ISO-8601-Zeitstempel
- `periodHours`: eingestellter Rückblick in Stunden
- `sourceFile`: Name der aktuell neuesten Logdatei; `null`, falls keine passende Datei vorhanden ist
- `count`: Anzahl der gefundenen und deduplizierten Fehler
- `errors`: Fehlerobjekte mit Zeitstempel, Absender-ID, Absender und Meldung

## Auswahl der Logdatei und Zeitraum

Das Modul sucht im von IP-Symcon gemeldeten Logverzeichnis nach Dateien im Muster `logfile*.log`, berücksichtigt aber nur `logfile.log` und Dateien der Form `logfile<Zeitstempel>.log`. Es wählt die Datei mit der neuesten Änderungszeit; bei Gleichstand entscheidet der Zeitstempel im Dateinamen. Die Datei wird zeilenweise gelesen.

Der konfigurierte Zeitraum wird auf die Einträge in dieser einen Datei angewendet. Wenn die neueste Logdatei jünger ist als der eingestellte Zeitraum, können Fehler aus älteren Logdateien fehlen. Das ist beabsichtigt: ältere Logdateien werden nicht durchsucht.

Gleiche Einträge mit identischem Zeitstempel, Absender und Meldung werden nur einmal ausgegeben. Es werden ausschließlich Einträge vom Typ `ERROR` übernommen.

## Dateien

- `library.json`: Bibliotheksmanifest
- `ShowLog/module.json`: Modulmanifest
- `ShowLog/form.json`: Konfigurationsoberfläche
- `ShowLog/module.php`: Modulimplementierung
- `docs/DOKUMENTATION.md`: ausführliche Dokumentation

## Entwicklung

Aktuelle, geprüfte Modulquellen werden aus der eigenen IP-Symcon-Modulbibliothek übernommen. Branches `main` und `develop` starten auf demselben Stand.