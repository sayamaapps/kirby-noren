<?php

/**
 * German strings. Keys and placeholders as in en.php; tests/I18nTest.php
 * checks that both files have the same keys and placeholders. The wording of
 * status, table and Panel texts is the one site/plugins/betrieb printed
 * (kirby-starter) — it is the reference.
 */
return [
    // Weekdays, ISO-8601 numbering (1 = Monday).
    'day.1' => 'Montag', 'day.2' => 'Dienstag', 'day.3' => 'Mittwoch', 'day.4' => 'Donnerstag',
    'day.5' => 'Freitag', 'day.6' => 'Samstag', 'day.7' => 'Sonntag',
    'dayShort.1' => 'Mo', 'dayShort.2' => 'Di', 'dayShort.3' => 'Mi', 'dayShort.4' => 'Do',
    'dayShort.5' => 'Fr', 'dayShort.6' => 'Sa', 'dayShort.7' => 'So',

    // PHP date() formats.
    'format.date' => 'j.n.',
    'format.dateYear' => 'j.n.Y',

    // Status (Schedule::status()).
    'status.openNow' => 'Jetzt geöffnet',
    'status.closedNow' => 'Jetzt geschlossen',
    'status.closedToday' => 'Heute geschlossen',
    'status.holidayToday' => 'Heute Feiertag',
    'status.occasionClosed' => '{occasion}: geschlossen',
    'status.allHours' => 'Alle Öffnungszeiten',
    'detail.until' => 'noch bis {time} Uhr',
    'detail.untilOccasion' => 'noch bis {time} Uhr ({occasion})',
    'detail.untilTomorrow' => 'noch bis morgen um {time} Uhr',
    'detail.untilTomorrowOccasion' => 'noch bis morgen um {time} Uhr ({occasion})',
    'detail.untilWeekday' => 'noch bis {weekday} um {time} Uhr',
    'detail.untilWeekdayOccasion' => 'noch bis {weekday} um {time} Uhr ({occasion})',
    'detail.untilDate' => 'noch bis {date} um {time} Uhr',
    'detail.untilDateOccasion' => 'noch bis {date} um {time} Uhr ({occasion})',
    'detail.alwaysOpen' => 'rund um die Uhr geöffnet',
    'detail.alwaysOpenOccasion' => 'rund um die Uhr geöffnet ({occasion})',
    'detail.opensToday' => 'öffnet heute um {time} Uhr',
    'detail.opensTomorrow' => 'öffnet morgen um {time} Uhr',
    'detail.opensWeekday' => 'öffnet am {weekday} um {time} Uhr',
    'detail.opensDate' => 'öffnet am {date} um {time} Uhr',
    'detail.backOn' => 'wieder ab {date}',
    'detail.holidayTomorrow' => 'morgen Feiertag ({name})',
    'detail.separator' => ' · ',

    // Closures and special days.
    'closure.label' => 'Betriebsferien',
    'closure.singleDay' => 'Geschlossen',
    'special.label' => 'Sonderöffnung',

    // Table and notes (Presenter::table()).
    'table.closed' => 'geschlossen',
    'table.holidays' => 'Feiertage',
    'table.range' => '{from} – {to}',
    'note.holiday' => '{date} {name}: geschlossen',
    'note.special' => '{date} {occasion}: {from}–{to} Uhr',
    'note.closedDay' => '{date}: geschlossen',
    'note.closedDayOccasion' => '{date} {occasion}: geschlossen',
    'note.closure' => '{label} {from} bis {to}',
    'note.dateRange' => '{from} bis {to}',

    // Compact (Grouping::byHours()).
    'compact.hours' => '{hours} Uhr',
    'compact.and' => ' und ',

    // Public holidays (Holidays). Official German names.
    'holiday.newYear' => 'Neujahr',
    'holiday.epiphany' => 'Heilige Drei Könige',
    'holiday.womensDay' => 'Internationaler Frauentag',
    'holiday.goodFriday' => 'Karfreitag',
    'holiday.easterSunday' => 'Ostersonntag',
    'holiday.easterMonday' => 'Ostermontag',
    'holiday.labourDay' => 'Tag der Arbeit',
    'holiday.liberationDay' => 'Tag der Befreiung',
    'holiday.uprisingDay' => 'Jahrestag des Volksaufstands 1953',
    'holiday.ascension' => 'Christi Himmelfahrt',
    'holiday.whitSunday' => 'Pfingstsonntag',
    'holiday.whitMonday' => 'Pfingstmontag',
    'holiday.corpusChristi' => 'Fronleichnam',
    'holiday.augsburgPeace' => 'Augsburger Friedensfest',
    'holiday.assumption' => 'Mariä Himmelfahrt',
    'holiday.childrensDay' => 'Weltkindertag',
    'holiday.germanUnity' => 'Tag der Deutschen Einheit',
    'holiday.reformation' => 'Reformationstag',
    'holiday.allSaints' => 'Allerheiligen',
    'holiday.repentance' => 'Buß- und Bettag',
    'holiday.christmas1' => '1. Weihnachtsfeiertag',
    'holiday.christmas2' => '2. Weihnachtsfeiertag',

    // Panel (blueprints/*.yml use these as `noren.panel.*`).
    'panel.page.title' => 'Öffnungszeiten',
    'panel.tab.areas' => 'Bereiche',
    'panel.tab.hours' => 'Zeiten',

    'panel.areas.label' => 'Bereiche',
    'panel.areas.info' => "Bereiche sind die Teile des Betriebs mit **eigenen Öffnungszeiten** — etwa Laden und Café. Wer nur eine Öffnungszeit hat, lässt diese Liste leer.\n\nDer **Schlüssel** ist der interne Name (klein, ohne Umlaute, ohne Leerzeichen). Er verbindet Bereich und Zeiten. Wird er nachträglich geändert, verlieren die Zeiten ihren Bezug — dann bitte auch im Reiter „Zeiten“ nachziehen.",
    'panel.area.key.label' => 'Schlüssel',
    'panel.area.key.help' => 'Klein, ohne Umlaute, ohne Leerzeichen. Beispiel — laden, cafe, werkstatt',
    'panel.area.name.label' => 'Name',
    'panel.area.name.help' => 'So, wie es auf der Seite stehen soll. Beispiel — Hofladen',
    'panel.area.short.label' => 'Kurzname',
    'panel.area.short.help' => 'Für enge Stellen wie das Statusband. Leer = Name wird verwendet.',

    'panel.hours.label' => 'Öffnungszeiten',
    'panel.hours.info' => "**Eine Zeile je Bereich und Wochentag.** Ein Tag, an dem geschlossen ist, bekommt **keine Zeile** — nicht eine Zeile mit leeren Feldern.\n\nEs gibt zwei Blöcke je Zeile, für Betriebe mit Mittagspause. Wer durchgehend offen hat, füllt nur den ersten.\n\nEin Block zählt nur, wenn **beide** Zeiten gesetzt sind und das Ende nach dem Anfang liegt — oder beide 00:00 sind, das heißt rund um die Uhr. Halb ausgefüllte Zeilen werden ignoriert — lieber keine Auskunft als eine falsche.\n\n**Über Mitternacht** (etwa Freitag 18:00 bis 2:00): als Zeile des Freitags eintragen, mit dem Ende am frühen Morgen. Das Ende darf nur wenig nach Mitternacht liegen; wie spät höchstens, legt die Konfiguration der Website fest.",
    'panel.hours.area.label' => 'Bereich',
    'panel.hours.area.help' => 'Leer lassen, wenn der Betrieb keine getrennten Bereiche hat.',
    'panel.hours.day.label' => 'Wochentag',
    'panel.hours.from1.label' => 'von',
    'panel.hours.to1.label' => 'bis',
    'panel.hours.from2.label' => 'von (2. Block)',
    'panel.hours.to2.label' => 'bis (2. Block)',
    'panel.hours.note.label' => 'Hinweis',
    'panel.hours.note.help' => 'Kurzer Zusatz, etwa „warme Küche bis 14 Uhr“.',

    'panel.closures.label' => 'Schließungen und Sondertage',
    'panel.closures.info' => "**Gesetzliche Feiertage lassen sich automatisch berechnen** (alle Bundesländer) — dafür `noren.holidays` in der eigenen `config.php` setzen. Ohne diese Option werden Feiertage NICHT automatisch berechnet. Hier immer eintragen, was die Berechnung nicht abdeckt oder was zusätzlich zu ist: Betriebsferien, Brückentage, Heiligabend, Silvester, Faschingsdienstag o. Ä. Ruhig früh eintragen: Auf der Website erscheint der Hinweis erst kurz vor dem Termin; wie früh, legt die Konfiguration fest.",
    'panel.closure.occasion.label' => 'Anlass',
    'panel.closure.occasion.placeholder' => 'z. B. Silvester',
    'panel.closure.occasion.help' => 'Erscheint auf der Website („Silvester geschlossen“). Leer bei Betriebsferien.',
    'panel.closure.from.label' => 'von',
    'panel.closure.to.label' => 'bis',
    'panel.closure.to.help' => 'Der letzte geschlossene Tag. „Wieder ab“ rechnet die Seite selbst.',
    'panel.closure.area.label' => 'Nur dieser Bereich',
    'panel.closure.area.help' => 'Leer = betrifft alle Bereiche. Das ist der Normalfall.',
    'panel.closure.note.label' => 'Hinweis',
    'panel.closure.note.help' => 'Ersetzt das automatische „wieder ab …“.',
    'panel.closure.opens.label' => 'Nur verkürzt geöffnet von',
    'panel.closure.opens.help' => 'Leer = ganz geschlossen. Beide Zeiten ausfüllen, etwa Heiligabend 8:30–12:00.',
    'panel.closure.closes.label' => 'bis',
];
