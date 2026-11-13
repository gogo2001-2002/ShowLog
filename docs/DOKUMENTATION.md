# ShowLog – technische Dokumentation

## Zweck

ShowLog liest die jeweils neueste IP-Symcon-Systemlogdatei und stellt die darin gefundenen Fehlereinträge als JSON-String für die Weiterverarbeitung in einer Visualisierung bereit. Zusätzlich hält das Modul die Anzahl dieser Einträge in einer Integer-Variable. Eine Boolean-Variable zeigt dauerhaft an, ob im konfigurierten Zeitraum mindestens ein Fehler vorhanden ist.

Das Modul erzeugt kein HTML-Markup für die Fehlerausgabe. Die WebVisu kann das JSON selbst einlesen und die Darstellung frei gestalten.

## Installation und Einrichtung

1. In der IP-Symcon-Verwaltungskonsole unter **Module Control** das Repository `https://github.com/gogo2001-2002/ShowLog` hinzufügen.
2. Das Modul **ShowLog** über **Instanz hinzufügen** auswählen.
3. In der Instanzkonfiguration das Leseintervall und den Zeitraum einstellen.
4. Die Variablen **Fehler im Log** und **Anzahl der Fehlereinträge** für die eigene Visualisierung verwenden.

Die Instanz führt einen Lesevorgang beim Anwenden der Konfiguration aus. Danach löst der registrierte Timer den Lesevorgang regelmäßig aus.

## Konfiguration

| Eigenschaft | Standard | Bereich | Bedeutung |
| --- | ---: | ---: | --- |
| Leseintervall (Sekunden) | 60 | 5–3600 | Zeit zwischen zwei Lesevorgängen |
| Zeitraum (Stunden) | 24 | 1–720 | Altergrenze für Einträge in der neuesten Logdatei |

Die PHP-Implementierung begrenzt Werte zusätzlich auf die angegebenen Bereiche. Es gibt keine Variablenaktionen oder Profile, da alle Einstellungen reine Instanzparameter sind.

## Statusvariablen

| Ident | Anzeigename | Typ | Position | Wert |
| --- | --- | --- | ---: | --- |
| `Errors` | Fehler im Log | String | 1 | JSON-Objekt |
| `ErrorCount` | Anzahl der Fehlereinträge | Integer | 2 | Anzahl der Einträge in `errors` |
| `Alarm` | Fehler-Alarm | Boolean | 3 | `true`, wenn `ErrorCount` größer als 0 ist |

Die JSON-Variable ist mit einer Textpräsentation (mehrzeilig) versehen. Sie wird dadurch nicht als HTML interpretiert. Der Wert enthält lesbare Einrückungen, bleibt aber gültiges JSON. Die Boolean-Variable wird bei jedem Logabruf direkt aus derselben Fehlerliste aktualisiert. Sie ist kein zeitlich begrenzter Impuls: Sie bleibt `true`, solange mindestens ein Fehler im eingestellten Rückblickszeitraum vorhanden ist, und wird bei leerer Fehlerliste auf `false` gesetzt.

## Datenformat

Beispiel:

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
      "message": "Fehlertext"
    }
  ]
}
```

| Feld | Typ | Beschreibung |
| --- | --- | --- |
| `generatedAt` | String | Lokaler Zeitpunkt der letzten Aktualisierung in ISO 8601 |
| `periodHours` | Integer | Eingestellter Rückblickszeitraum |
| `sourceFile` | String oder `null` | Name der gewählten neuesten Logdatei |
| `count` | Integer | Anzahl der Fehlerobjekte in `errors` |
| `errors` | Array | Fehler, neuester Eintrag zuerst |
| `errors[].timestamp` | String | Zeitpunkt des Logeintrags in ISO 8601 |
| `errors[].senderId` | Integer oder `null` | Symcon-Absender-ID, soweit im Logformat vorhanden |
| `errors[].sender` | String | Absendername |
| `errors[].message` | String | Meldungstext; zusätzliche Logzeilen wie Stacktraces werden mit Zeilenumbrüchen angefügt |

Das Feld `count` im JSON und die Integer-Variable werden aus derselben deduplizierten Fehlerliste berechnet.

## Auswahl und Verarbeitung der Datei

1. Das Modul verwendet `IPS_GetLogDir()`, um den Systemlog-Ordner zu ermitteln.
2. Es betrachtet ausschließlich lesbare Dateien, deren Namen `logfile.log` oder `logfile<Ziffern>.log` entsprechen.
3. Als neueste Datei gilt die Datei mit der jüngsten Änderungszeit. Bei gleicher Änderungszeit wird der numerische Zeitstempel im Namen bevorzugt. Damit wird eine gleichzeitige `logfile.log`-Kopie gegenüber der benannten aktuellen Datei nicht doppelt ausgewertet.
4. Nur diese eine Datei wird geöffnet. Ältere Logdateien werden nicht als Rückfall oder Ergänzung gelesen.
5. Die Datei wird zeilenweise gelesen. Es wird keine ganze Datei auf einmal in den Arbeitsspeicher geladen.
6. Zeilen vom Typ `ERROR` werden übernommen, wenn ihr Zeitstempel innerhalb des konfigurierten Rückblicks liegt.
7. Mehrzeilige Meldungsfortsetzungen werden der vorangehenden Logmeldung angefügt.
8. Identische Einträge (Zeitpunkt, Absender-ID, Typ, Absender und Meldung) werden zusammengeführt.
9. Die Fehler werden nach Zeitstempel absteigend sortiert und als JSON gespeichert.

### Unterstützte Logzeilen

Das aktuelle Format wird in der Reihenfolge Datum, Absender-ID, Typ, Absender und Nachricht gelesen, zum Beispiel:

```text
09.11.2026 06:52:29 | 42184 | ERROR | PHPModule | Beispielmeldung
```

Zusätzlich wird ein älteres ISO-Datumsformat verarbeitet:

```text
2026-11-09 06:52:29 | ERROR | 42184 PHPModule | Beispielmeldung
```

## Hinweise zum Zeitraum

Der Zeitraum wird auf die Einträge der neuesten Datei angewendet. Er garantiert nicht, dass alle Fehler aus den letzten X Stunden enthalten sind: Wenn Symcon während dieses Zeitraums bereits eine neue Logdatei begonnen hat, liegen ältere Einträge in vorherigen Dateien und werden gemäß der Vorgabe nicht gesucht. Das Feld `periodHours` benennt daher die eingestellte Filtergrenze, nicht die tatsächliche zeitliche Abdeckung der ausgewählten Datei.

## Dateien im Repository

```text
library.json
README.md
ShowLog/
  module.json
  module.php
  form.json
docs/
  DOKUMENTATION.md
```

Die vier Moduldateien werden unverändert aus der eigenen Symcon-Modulbibliothek übernommen. Die Dokumentation beschreibt den Stand der Implementierung und kann bei späteren Änderungen angepasst werden.
