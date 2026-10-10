# Noren: privacy notes

This is an English summary of [DATENSCHUTZ.md](DATENSCHUTZ.md), the German text
block for privacy policies, which is the authoritative version. Both describe
the implementation. They are not legal advice. Each operator has to determine
the legal basis and the wording for their own site.

## What the visitor's browser does

Nothing towards any third party. The plugin sets no cookies, uses no
`localStorage`, `sessionStorage` or IndexedDB, and loads no script, font, image
or embed from another server. The page shows the opening hours that the server
computed for that request, as plain HTML.

The plugin records nothing about the visitor. It does not read the visitor's
IP address, user agent or device clock.

## What the server computes

For each page request the server works out, from the business's own content:

- whether a unit (a location or an area) is open right now, and when it opens
  next;
- the weekly table, the compact list and the schema.org data for the site;
- the public holidays of the years in question, calculated by a formula from
  the rules of the German states, with no lookup at any service.

"Now" is the server's clock, converted into the business's time zone
(`noren.timezone`, by default Europe/Berlin). Nothing is cached between requests
inside the plugin.

## What is stored, and where

The plugin stores nothing itself. The input is the opening hours, closures and
special days that the editor enters in the Panel, together with the
configuration in the site's config file. These are business data: days, times
and short notes, such as an occasion ("Silvester") or a note ("warme Küche bis
14 Uhr").

Errors in the configuration (for example an unknown time zone or state) are
written to the server's error log as a short message. The message contains no
visitor data.

## What is shown

- The status line, the table and the compact list show the business's own hours,
  holidays and closures, with the notes entered for them.
- The schema.org arrays contain only public business data: opening hours,
  special opening hours, closures and holidays. The site decides whether to
  output them as JSON-LD.
- The plugin does not render addresses, telephone numbers or any personal data.

## What the operator should check

- Enter only business information in the occasion and note fields. A name of an
  employee on holiday is personal data and does not belong there.
- The web server's access logs are outside the plugin. They are covered by the
  site's general privacy policy.
- Holidays are off by default. When they are on, the plugin calculates them
  locally, and the operator's own list of extra days is part of the content.
