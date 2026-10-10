# Noren: technische Grundlage für den Datenschutztext

(English version: [PRIVACY.md](PRIVACY.md). Optionen heißen `noren.*`; die Namen
stehen im [README](README.md#configuration).)

Dieser Baustein beschreibt die Implementierung. Rechtsgrundlage, Interessenabwägung
und die Formulierung müssen vor Veröffentlichung für den konkreten Betrieb fachlich
geprüft und ergänzt werden. Der Text ist keine Rechtsberatung.

## Öffnungszeiten — technischer Textbaustein

Die Öffnungszeiten auf unserer Website, die Angabe „geöffnet“ oder „geschlossen“
sowie die Hinweise zu Feiertagen und Betriebsferien werden von unserem Webserver
aus den von uns gepflegten Angaben berechnet und als Text ausgeliefert. Ihr Browser
baut dafür **keine Verbindung zu einem anderen Dienst** auf: Es wird kein Skript,
keine Schriftart, keine Einbettung und kein Bild von einem fremden Server
nachgeladen.

Die Angabe „geöffnet“ bezieht sich auf die Uhrzeit des Servers in der Zeitzone
unseres Betriebs (in der Vorgabe Europe/Berlin), nicht auf die Uhr Ihres Geräts. Gesetzliche
Feiertage werden auf dem Server anhand der Regeln der Bundesländer errechnet; dafür
wird kein externer Kalender abgerufen.

Das eingesetzte Modul setzt **keine Cookies**, verwendet **keinen Browser-Speicher**
(etwa Local Storage oder Session Storage) und erhebt **keine Daten über Besucherinnen
und Besucher** dieser Website. Es liest weder Ihre IP-Adresse noch Ihren Browser-Typ
aus, um die Öffnungszeiten anzuzeigen.

Die Daten selbst sind Geschäftsangaben: Öffnungszeiten, Schließtage, Sonderöffnungen
und kurze Hinweise, die wir selbst eintragen. Wenn die Website Angaben für
Suchmaschinen bereitstellt (strukturierte Daten nach schema.org), enthalten diese
ausschließlich öffentliche Geschäftsangaben wie die Öffnungszeiten.

## Hinweise für den Betreiber

- **Keine Übertragung durch das Modul.** Das Modul selbst überträgt keine Daten an
  Dritte. Hosting und die Zugriffsprotokolle des Webservers liegen außerhalb des
  Moduls und sind in der allgemeinen Datenschutzerklärung der Website zu beschreiben.
- **Keine personenbezogenen Angaben in den Feldern.** Die Felder „Anlass“ und
  „Hinweis“ sind für Angaben zum Betrieb gedacht („Silvester“, „warme Küche bis
  14 Uhr“). Namen von Mitarbeitenden, etwa bei Urlaub, gehören dort nicht hinein.
- **Fehlerprotokoll.** Konfigurationsfehler (etwa eine unbekannte Zeitzone oder ein
  unbekanntes Bundesland) werden in das Fehlerprotokoll des Servers geschrieben.
  Die Meldung enthält keine Besucherdaten.
- **Feiertage.** Gesetzliche Feiertage sind standardmäßig ausgeschaltet und werden
  nur berechnet, wenn der Betreiber das Bundesland in der Konfiguration angibt. Die
  Berechnung erfolgt lokal; zusätzliche Tage trägt der Betreiber selbst ein.
- **Schlüssel und Daten.** Es gibt keinen Zugangsschlüssel und keine Datenbank. Die
  Öffnungszeiten liegen als Inhalte in der Website, wie alle anderen Texte auch.
- **Übergabe der Website.** Wer die Website an einen Kunden übergibt, prüft,
  ob weitere Dienste (Analyse, Karten, Formulare) auf derselben Seite eingebunden
  sind; für diese gilt eine eigene Beschreibung. Dieses Modul gehört nicht dazu.
