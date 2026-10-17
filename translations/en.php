<?php

/**
 * English strings — the default and the fallback for missing keys.
 * `{name}` placeholders are filled in by Noren\I18n::t(). tests/I18nTest.php
 * checks that every language file has the same keys and placeholders as this
 * one. Keys are the contract (SPEC.md §5); never rename one without a
 * CHANGELOG entry.
 */
return [
    // Weekdays, ISO-8601 numbering (1 = Monday).
    'day.1' => 'Monday', 'day.2' => 'Tuesday', 'day.3' => 'Wednesday', 'day.4' => 'Thursday',
    'day.5' => 'Friday', 'day.6' => 'Saturday', 'day.7' => 'Sunday',
    'dayShort.1' => 'Mon', 'dayShort.2' => 'Tue', 'dayShort.3' => 'Wed', 'dayShort.4' => 'Thu',
    'dayShort.5' => 'Fri', 'dayShort.6' => 'Sat', 'dayShort.7' => 'Sun',

    // PHP date() formats.
    'format.date' => 'j M',
    'format.dateYear' => 'j M Y',

    // Status (Schedule::status()).
    'status.openNow' => 'Open now',
    'status.closedNow' => 'Closed now',
    'status.closedToday' => 'Closed today',
    'status.holidayToday' => 'Public holiday today',
    'status.occasionClosed' => '{occasion}: closed',
    'status.allHours' => 'All opening hours',
    'detail.until' => 'open until {time}',
    'detail.untilOccasion' => 'open until {time} ({occasion})',
    'detail.untilTomorrow' => 'open until {time} tomorrow',
    'detail.untilTomorrowOccasion' => 'open until {time} tomorrow ({occasion})',
    'detail.untilWeekday' => 'open until {time} on {weekday}',
    'detail.untilWeekdayOccasion' => 'open until {time} on {weekday} ({occasion})',
    'detail.untilDate' => 'open until {time} on {date}',
    'detail.untilDateOccasion' => 'open until {time} on {date} ({occasion})',
    'detail.alwaysOpen' => 'open around the clock',
    'detail.alwaysOpenOccasion' => 'open around the clock ({occasion})',
    'detail.opensToday' => 'opens today at {time}',
    'detail.opensTomorrow' => 'opens tomorrow at {time}',
    'detail.opensWeekday' => 'opens on {weekday} at {time}',
    'detail.opensDate' => 'opens on {date} at {time}',
    'detail.backOn' => 'back on {date}',
    'detail.holidayTomorrow' => 'tomorrow is a public holiday ({name})',
    'detail.separator' => ' · ',

    // Closures and special days.
    'closure.label' => 'Closed for holidays',
    'closure.singleDay' => 'Closed',
    'special.label' => 'Special opening hours',

    // Table and notes (Presenter::table()).
    'table.closed' => 'closed',
    'table.holidays' => 'Public holidays',
    'table.range' => '{from} – {to}',
    'note.holiday' => '{date} {name}: closed',
    'note.special' => '{date} {occasion}: {from}–{to}',
    'note.closedDay' => '{date}: closed',
    'note.closedDayOccasion' => '{date} {occasion}: closed',
    'note.closure' => '{label} {from} to {to}',
    'note.dateRange' => '{from} to {to}',

    // Compact (Grouping::byHours()).
    'compact.hours' => '{hours}',
    'compact.and' => ' and ',

    // Public holidays (Holidays).
    'holiday.newYear' => 'New Year’s Day',
    'holiday.epiphany' => 'Epiphany',
    'holiday.womensDay' => 'International Women’s Day',
    'holiday.goodFriday' => 'Good Friday',
    'holiday.easterSunday' => 'Easter Sunday',
    'holiday.easterMonday' => 'Easter Monday',
    'holiday.labourDay' => 'Labour Day',
    'holiday.liberationDay' => 'Liberation Day',
    'holiday.uprisingDay' => 'Anniversary of the 1953 Uprising',
    'holiday.ascension' => 'Ascension Day',
    'holiday.whitSunday' => 'Whit Sunday',
    'holiday.whitMonday' => 'Whit Monday',
    'holiday.corpusChristi' => 'Corpus Christi',
    'holiday.augsburgPeace' => 'Augsburg Peace Festival',
    'holiday.assumption' => 'Assumption Day',
    'holiday.childrensDay' => 'World Children’s Day',
    'holiday.germanUnity' => 'German Unity Day',
    'holiday.reformation' => 'Reformation Day',
    'holiday.allSaints' => 'All Saints’ Day',
    'holiday.repentance' => 'Day of Repentance and Prayer',
    'holiday.christmas1' => 'Christmas Day',
    'holiday.christmas2' => 'Second Day of Christmas',

    // Panel (blueprints/*.yml use these as `noren.panel.*`).
    'panel.page.title' => 'Opening hours',
    'panel.tab.areas' => 'Areas',
    'panel.tab.hours' => 'Hours',

    'panel.areas.label' => 'Areas',
    'panel.areas.info' => "Areas are the parts of the business with **their own opening hours** — a shop and a café, for example. With only one set of hours, leave this list empty.\n\nThe **key** is the internal name (lower case, no umlauts, no spaces). It links an area to its hours. If it is changed later, the hours lose their area — then update them in the “Hours” tab as well.",
    'panel.area.key.label' => 'Key',
    'panel.area.key.help' => 'Lower case, no umlauts, no spaces. Example — shop, cafe, workshop',
    'panel.area.name.label' => 'Name',
    'panel.area.name.help' => 'As it should appear on the site. Example — Farm shop',
    'panel.area.short.label' => 'Short name',
    'panel.area.short.help' => 'For tight spots such as the status band. Empty = the name is used.',

    'panel.hours.label' => 'Opening hours',
    'panel.hours.info' => "**One row per area and weekday.** A day on which the business is closed gets **no row** — not a row with empty fields.\n\nEach row has two blocks, for businesses with a lunch break. Open all day? Fill in only the first. Open around the clock: 00:00 to 00:00.\n\nA block counts only when **both** times are set and the end is after the start. Half-filled rows are ignored — no information is better than wrong information.\n\n**Past midnight** (Friday 18:00 to 2:00, for example): enter it as Friday’s row with the end in the early morning. The end may lie only shortly after midnight; the site’s configuration sets how late.",
    'panel.hours.area.label' => 'Area',
    'panel.hours.area.help' => 'Leave empty if the business has no separate areas.',
    'panel.hours.day.label' => 'Weekday',
    'panel.hours.from1.label' => 'from',
    'panel.hours.to1.label' => 'to',
    'panel.hours.from2.label' => 'from (2nd block)',
    'panel.hours.to2.label' => 'to (2nd block)',
    'panel.hours.note.label' => 'Note',
    'panel.hours.note.help' => 'A short addition, such as “hot food until 2 pm”.',

    'panel.closures.label' => 'Closures and special days',
    'panel.closures.info' => "**Public holidays can be calculated automatically** (all German states) — set `noren.holidays` in your `config.php`. Without that option, public holidays are NOT calculated. Always enter here what the calculation does not cover or what is closed in addition: company holidays, bridge days, Christmas Eve, New Year’s Eve, carnival and the like. Feel free to enter them early: the site shows the notice only shortly before the date; how early is set in the configuration.",
    'panel.closure.occasion.label' => 'Occasion',
    'panel.closure.occasion.placeholder' => 'e.g. New Year’s Eve',
    'panel.closure.occasion.help' => 'Shown on the site (“New Year’s Eve: closed”). Leave empty for company holidays.',
    'panel.closure.from.label' => 'from',
    'panel.closure.to.label' => 'to',
    'panel.closure.to.help' => 'The last closed day. The site works out “back on” itself.',
    'panel.closure.area.label' => 'Only this area',
    'panel.closure.area.help' => 'Empty = applies to all areas. That is the normal case.',
    'panel.closure.note.label' => 'Note',
    'panel.closure.note.help' => 'Replaces the automatic “back on …”.',
    'panel.closure.opens.label' => 'Only short hours, from',
    'panel.closure.opens.help' => 'Empty = closed all day. Fill in both times, e.g. Christmas Eve 8:30–12:00.',
    'panel.closure.closes.label' => 'to',
];
