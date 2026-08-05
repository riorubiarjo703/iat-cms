# District Facilities page — remaining sections

The page at `/district-facilities` currently holds a single `scbd_page_hero`
block. This adds the four sections the *SCBD District Guide* design specifies
below the hero: Places of Interest, Location & Access, District Facilities, and
a closing call to action.

## Source

`~/Downloads/SCBD District Guide.html`, a bundled artifact. The markup lives in
the `__bundler/template` island; the animation logic is the `text/x-dc` script
at the end of it. Section ids in the design are `#top`, `#places`, `#location`,
`#facilities`.

## Content model

The design's cards carry three fields the shared models do not have: tag chips,
a stat label/value pair, and a per-facility eyebrow. These extend the existing
`DistrictPlace` and `Facility` records rather than being duplicated into
page-local repeaters, so the homepage and this page render the same content and
an editor changes it once.

```
district_places  + body        (translatable)  the design's paragraph
                 + tags        (translatable)  comma-separated per locale
                 + stat_label  (translatable)
                 + stat_value

facilities       + eyebrow     (translatable)
                 + stat_label  (translatable)
                 + stat_value
```

`caption` on `DistrictPlace` is left alone — the homepage's horizontal district
strip uses it as a short label ("Grade A office"), which is not the design's
paragraph. Both coexist.

Translatable columns are declared in each model's `TRANSLATABLE` constant, so
`HasTranslatableFields` casts them to a locale map and `t()` gives the `en`
fallback for free. `stat_value` ("18K+", "3,200+") is a bare string: a figure,
not prose.

`tags` stores one comma-separated string per locale rather than a nested JSON
array. A nested array would need its own repeater inside every locale tab; a
comma-separated line is one text field per language, split at render.

## Blocks

Four new blocks, registered explicitly in `PageBuilderServiceProvider`.

### `scbd_places` — "Places of Interest"

Reads `DistrictPlace::active()->ordered()`. Renders each place as a full-width
bordered row alternating text/image, image/text. The text side carries the
title, body, tag chips and a stat footer; the image side is a `data-reveal`
wrapper around the photograph. Section id `places`.

Block fields: `eyebrow`, `heading` (translatable).

Renders nothing when there are no active places, matching how `scbd_district`
and `scbd_facilities` already guard.

### `scbd_location` — "Location & Access"

Dark section (`#201e1d`), two columns.

Left: address, contact, and a "Getting Here" list. Address and contact default
to `SiteSetting::contact_address` / `contact_phone` so the site keeps one source
of truth, with per-block overrides for editors who want different copy here.
The list is a repeater of `{label, text}` rows ("Metro:" / "Sudirman Station
(direct connection)").

Right: a lazy-loaded Google Maps `iframe` from a `map_embed_url` field — an
embed URL needs no API key — above a small repeater of `{label, value}` fact
tiles.

Block fields: `eyebrow`, `heading`, `address_heading`, `address`,
`contact_heading`, `contact`, `access_heading` (translatable);
`access` repeater; `map_embed_url`; `facts` repeater.

The map is omitted entirely when `map_embed_url` is blank, rather than rendering
an empty frame.

### `scbd_operations` — "District Facilities"

Reads `Facility::active()->ordered()`. Same alternating row treatment as
`scbd_places`, with the facility's eyebrow above the title and the stat in the
footer. Section id `facilities`.

Block fields: `eyebrow`, `heading` (translatable).

This is a second presentation of the same records the homepage's
`scbd_facilities` block shows as a sticky card stack. Both blocks stay — they
are different layouts of one dataset, and the homepage's stack behaviour is not
wanted on an interior page.

### `scbd_cta` — "Ready to explore?"

Red section (`#ec3013`), centred. Heading, body, and one button whose label and
URL are block fields, defaulting to the Contact Us page.

Block fields: `heading`, `body`, `button_label` (translatable); `button_url`.

## Motion

No new JavaScript. The sections use hooks `reveal.js` already drives:

- `data-split` on the section headings → `initSplitHeadings`
- `data-fade` on text columns and cards → the fade/rise tween
- `data-reveal` on image wrappers → the clip-path wipe

Because these are the same hooks, the reduced-motion resting states already
written in `index.js` cover the new sections without change. The design's own
`data-facility` stagger and custom cursor are not ported: the cursor is already
global in this codebase, and `data-fade` is the established equivalent of the
stagger.

## Responsive

The alternating rows use the existing `.scbd-card-split` class, which already
collapses to a single column at 900px. New CSS covers the tag chips, the stat
footers, the location grid, and mobile row ordering — on narrow screens every
row puts its image first, so the alternation does not leave two text panels
adjacent.

## Seeding

`DistrictFacilitiesPageSeeder`, mirroring `CompanyPagesSeeder`: writes the
page's `builder_payload` and fills the new columns on the three places and four
facilities with the design's copy. Run on demand rather than from
`DatabaseSeeder`, which is where every other page seeder here sits. English
only, for the same reason the company pages are — inventing translations is
worse than falling back.

It fills only the new columns, leaving each record's existing title, caption,
body and image untouched: those are the site's own wording and rewriting them
would change the homepage as a side effect. Records are matched on image
filename, which is what ties a record to a row of the design.

The photographs the design references are already in the database
(`offices.jpg`, `hospitality.jpg`, `publicrealm.png`, `fireservice.jpg`,
`clinic.jpg`, `security.png`, `transport.png`), so the sections render with real
imagery on first run.

Re-running the seeder overwrites the page's blocks and the seeded model columns,
discarding builder edits — the same trade-off `CompanyPagesSeeder` documents.

## Unverified figures

The stat figures in the design (18K+ visitors per day, 650+ hotel rooms, 40+
annual events, 32 fire personnel, 4,500+ patients, 180+ security staff, 3,200+
parking spaces) do not appear on scbd.com and read as invented for the mockup.
They are seeded as written so the page matches the design, and every one is
editable in the admin. They should be confirmed with the client before launch.

## Out of scope

- Translations for `id` and `cn`.
- Changes to the homepage's `scbd_district` and `scbd_facilities` blocks.
- The design's bespoke cursor and Lenis setup, both already global here.
