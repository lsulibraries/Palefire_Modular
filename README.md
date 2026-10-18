# Palefire Modular Pages

Custom module for the `modular_page` content type. It turns each paragraph in
`field_scroll_section` into a scroll section with a stable anchor and
snap, height and theme classes. It also sets the page's scroll-snap mode,
provides the "On this page" table of contents (TOC) block, and builds the
variables for the bento search and events carousel paragraphs.

The module handles data and markup hooks. The Palefire theme provides the
templates, JavaScript and styles that use them.

## Origami stays unchanged

Before doing anything, each preprocess hook checks
`ModularSections::isPalefire()` and returns early unless Palefire (or a
sub-theme of Palefire) is the active theme. While Origami is the default
theme, enabling this module does not change any markup Origami renders.

There is one exception: the events carousel preprocess doesn't check the
theme. It only affects the new `events_carousel` paragraph type, which
Origami pages don't use.

## Requirements

* Drupal 10.2 or later, or 11. The block plugin uses PHP attribute
  discovery, which first shipped in 10.2.
* Paragraphs.
* Views and Search API with the `bento_discovery` view (only needed for
  the bento search section).
* `libcal_gantt_carousel` (only needed for the events carousel section).
  If that module isn't enabled, the section renders empty.
* Media and the core Responsive Image module (only needed for section
  background images). See "Background images" below.
* The Palefire theme files from this kit: the templates, the `modular`,
  `modular-toc` and `bento-search` libraries, and the `modular_sidebar`
  region.

## Install

1. Copy this folder to `web/modules/custom/palefire_modular`.
2. Run `drush en palefire_modular -y && drush cr`.
3. Create the paragraph fields and types listed below.
4. If you use section backgrounds, set up the image styles, responsive
   image style and media view mode under "Background images".
5. In Palefire, place the "Modular page: table of contents" block in the
   `modular_sidebar` region.

## Content model

### Node: `modular_page`

| Field | Type | Purpose |
| --- | --- | --- |
| `field_scroll_section` | Entity reference revisions (paragraph) | Each paragraph becomes one scroll section |
| `field_modular_type` | List (text): `page`, `guide`, `form` | Sets the TOC behaviour and adds a `<body>` class |

### Shared section fields

Add these fields to every paragraph type allowed in `field_scroll_section`,
reusing the same field storage across bundles. All of them are optional,
because the code checks `hasField()` before reading each one.

| Field | Type | Effect |
| --- | --- | --- |
| `field_section_title` | Text (plain) | Section `<h2>` and TOC label. Sections without a title are left out of the TOC. |
| `field_section_anchor` | Text (plain) | Custom URL fragment. Defaults to the title. |
| `field_section_snap` | Boolean | Adds `pf-section--snap` |
| `field_section_height` | List: `auto`, `screen` | `auto` fits the content. `screen` fills the viewport under the header. |
| `field_section_theme` | List: `light`, `dark` | Adds `pf-section--theme-{value}` |
| `field_section_toc_hide` | Boolean | Leaves a titled section out of the TOC |
| `field_section_bg` | Entity reference → Media, Image type only (or Image) | Decorative responsive image behind the section. See "Background images". |

### Section paragraph types

| Paragraph type | Optional fields | Notes |
| --- | --- | --- |
| `bento_search` | `field_bento_placeholder` | Its `<h2>` is visually hidden, because the search form labels the section |
| `events_carousel` | `field_carousel_days` (integer), `field_carousel_calendars` (list, checkboxes: one or more LibCal Gantt calendars), `field_carousel_campuses` (list, checkboxes: only these libraries), `field_carousel_calendar` (older single calendar key, text), `field_carousel_interval` (ms, `0` = no autoplay), `field_carousel_event_days` (boolean, "Show only event days") | Embeds the `libcal_gantt_day_carousel` block |
| Link Cards: Standard and any others | none | Pick up the shared section behaviour automatically |

## What it does

### Section markup

`palefire_modular_preprocess_field()` adds attributes to each item in
`field_scroll_section`. Palefire's
`field--node--field-scroll-section--modular-page.html.twig` then wraps
each item in a `<section>`:

```html
<section id="hours-and-events"
         class="pf-section pf-section--events-carousel pf-section--screen pf-section--theme-dark pf-section--snap"
         data-section-index="2"
         data-section-theme="dark"
         aria-labelledby="hours-and-events-title">
  <div class="pf-section__inner">
    <h2 id="hours-and-events-title" class="pf-section__title" tabindex="-1">Hours and events</h2>
    ...
  </div>
</section>
```

Section ids are built on the server in this order:

1. The anchor field.
2. If there's no anchor, the title.
3. If there's no title, the paragraph bundle name.

Duplicate ids get `-2`, `-3` and so on. The section can't take an id the
page already uses: `main`, `main-content`, `header`, `footer`, `page`,
`top` or `toc`.

Unpublished paragraphs, and paragraphs the current user can't view, are
skipped. Because of that, the TOC always matches what is rendered.

### Background images

When `field_section_bg` has an image, the module renders it and the
section template prints it first inside the section:

```html
<section id="hours-and-events" class="pf-section … pf-section--has-bg">
  <div class="pf-section__bg" aria-hidden="true" inert>
    <img srcset="…768w, …1280w, …1920w, …2560w" sizes="…" alt="…" loading="lazy">
  </div>
  <div class="pf-section__inner">…</div>
</section>
```

The image is decorative, so its wrapper is `aria-hidden` and `inert`.
`_modular.scss` lays it behind the content with `object-fit: cover` and
adds a scrim so text stays readable: a light wash on `light` sections,
a dark one on `dark` sections. Override `--pf-section-scrim` or
`--pf-section-bg-position` to tune either.

How the field is rendered depends on its type:

| Field type | Rendered with |
| --- | --- |
| Media reference | The `section_background` media view mode |
| Image | The `section_background` responsive image style, or the original image if Responsive Image or that style is missing |

Rendering goes through Drupal's normal formatters, so access is checked
and cache tags bubble. Unpublished media won't show, and replacing an
image updates the page. Anonymous users need the "View media" permission.

#### One-time setup

1. Enable Responsive Image: `drush en responsive_image -y`.
2. At Configuration → Media → Image styles, add four styles. Each one
   gets a single Scale effect with upscaling off:

   | Machine name | Width |
   | --- | --- |
   | `section_bg_768` | 768 |
   | `section_bg_1280` | 1280 |
   | `section_bg_1920` | 1920 |
   | `section_bg_2560` | 2560 |

   You can add a Convert → WebP effect to each one if the server's image
   toolkit supports WebP.
3. At Configuration → Media → Responsive image styles, add one with
   machine name `section_background`:
   * Breakpoint group: Responsive Image.
   * 1x Viewport Sizing: "Select multiple image styles and use the sizes
     attribute".
   * Sizes: `(max-aspect-ratio: 1/1) 180vh, 100vw`. Full-screen sections
     on portrait phones crop a wide image, so the browser needs a wider
     file than the viewport width.
   * Image styles: check all four `section_bg_*` styles.
   * Fallback image style: `section_bg_1280`.
4. At Structure → Display modes → View modes, add a Media view mode with
   machine name `section_background`.
5. On the Image media type, go to Manage display → Custom display
   settings and enable Section background. On that display:
   * Image field: Responsive image formatter, style
     `section_background`, no link, label hidden. Loading: lazy, unless
     the first section on a page has a background, in which case use eager.
   * Every other field: Disabled.
6. On each paragraph type, leave `field_section_bg` set to Disabled in
   Manage display. The module renders it, not the paragraph.
7. Run `drush cr`.

### Scroll-snap mode

`palefire_modular_preprocess_html()` adds a class to `<html>` based on how
many sections snap:

| Snapping sections | Class on `<html>` |
| --- | --- |
| None | no class |
| All of them | `pf-snap pf-snap-mandatory` |
| Some of them | `pf-snap pf-snap-proximity` |

The page uses `proximity` when only some sections snap, because
`mandatory` snapping jumps past any section that has no snap point.

### Mode

The `field_modular_type` value is added to `<body>` as
`modular--{value}`. If the field is empty, the value is `page`. With the
TOC block's default settings:

| Mode | TOC |
| --- | --- |
| `page` | Section links only |
| `guide` | Section links plus `<h3>` sub-links, which `modular-toc.js` adds |
| `form` | Hidden |

To add a mode, add the value to the field's allowed values. You can then
add it to the block's "Hide on these modes" or "Include h3 sub-links on
these modes" setting, and style the new `modular--{value}` body class.

### TOC block

Block plugin `palefire_modular_toc` ("Modular page: table of contents").

The block renders the top-level links on the server, so they work without
JavaScript and show up as soon as the page loads. Palefire's
`modular-toc.js` adds scroll tracking, the moving indicator and the Guide
sub-links. Headings inside an element marked `data-toc-skip` (for example,
the bento tiles and the carousel) are ignored.

| Setting | Default |
| --- | --- |
| Heading | `On this page` |
| Hide on these modes | `form` |
| Include h3 sub-links on these modes | `guide` |

On any page that isn't a `modular_page`, the block renders nothing, so you
don't need visibility rules. The block is cached by route and by node.

### Bento search

`palefire_modular_preprocess_paragraph__bento_search()` builds one search
form and one result tile per enabled **block** display of the
`bento_discovery` view, in the order the displays are listed in Views.
Every display reads the same exposed filter, `search_api_fulltext_bento`,
from the query string. That means a plain GET submit filters every tile
without JavaScript, and `bento-search.js` upgrades it to Views AJAX.

Set these options on each block display:

* Use AJAX: Yes.
* Exposed form in block: Yes. Don't place that block.
* Exposed form style: Input required.
* Exposed filter identifier: `search_api_fulltext_bento`.

To add a result type, add another block display. The render cache varies
by `url.query_args:search_api_fulltext_bento`.

### Events carousel

`palefire_modular_preprocess_paragraph__events_carousel()` creates the
`libcal_gantt_day_carousel` block with the paragraph's optional
overrides and exposes the result as `carousel`. The overrides map to
block settings like this:

| Paragraph field | Block setting |
| --- | --- |
| `field_carousel_days` | `max_days` |
| `field_carousel_calendars` | `calendar` (ticked keys joined with commas) |
| `field_carousel_calendar` | `calendar` (only when no Calendars box is ticked) |
| `field_carousel_campuses` | `campus` (ticked campus IDs joined with commas) |
| `field_carousel_interval` | `interval` |
| `field_carousel_event_days` | `only_event_days` |

#### Calendars: one or several (2026-10-18)

`field_carousel_calendars` gives editors one checkbox per calendar tab
from **Calendars** on `/admin/config/services/libcal-gantt`, labelled
"Events (8030)" and so on. Tick one calendar for its events only, or tick
several to merge them into one carousel. If nothing is ticked, the older
`field_carousel_calendar` text key is used when it has a value, and
otherwise the first calendar.

The options come from `palefire_modular_carousel_calendar_options()`, an
`allowed_values_function`. It reads that setting each time, so adding or
renaming a calendar there updates the checkboxes. Field UI can't set an
allowed-values function, so create the field with the script, from this
module's folder on production:

```sh
git pull
drush cr
drush php:script scripts/carousel_calendars_setup.php
drush cr
```

The script adds the field, puts the checkboxes on the form next to the old
Calendar key field, and keeps it out of the display. Merging several
calendars needs `libcal_gantt` 2026-10-18 or later: its carousel endpoint
reads a comma-separated `calendar` list. Older versions only use the first
key.

Once every carousel uses the checkboxes, the old `field_carousel_calendar`
can be deleted in Field UI. The code skips it when it's gone.

#### Libraries: only some campuses (2026-10-18)

`field_carousel_campuses` is the carousel's version of the "LSU Libraries"
filter on the LibCal calendar (`cm=262` = Hill Memorial Library). There's
one checkbox per library in **Campus rows** on the LibCal Gantt settings
page, labelled "Hill Memorial Library (262)" and so on, read live by
`palefire_modular_carousel_campus_options()`.

- **Ticked:** only those libraries' events are shown, their online events
  included, as on LibCal. Each slide's building hours are limited to them.
- **Nothing ticked:** every library, as before.

It works together with Calendars: tick the Events calendar and Hill Memorial
Library to get what `cm=262` shows on LibCal. It needs `libcal_gantt`
2026-10-18 or later, whose carousel endpoint reads `?campus=`.

The same script creates it. Re-running it on a site that already has the
Calendars field only adds the new one:

```sh
git pull
drush php:script scripts/carousel_calendars_setup.php
drush cr
```

#### Show only event days (2026-10-18)

`field_carousel_event_days` (Boolean, label "Show only event days") skips
days that have no events, or only all-day events (a donation bin, an open
room). It uses the same all-day test as the slides' "All day" label.
**Days** then counts event days: with Days = 7 you get the next 7 days that
have a timed event. Days are searched only within the window the carousel
already loads (libcal_gantt's weekday count, 10 by default, plus the
weekends between them, about two weeks). A quiet fortnight therefore shows
fewer slides, and with no event days at all the section reads "No upcoming
events in the next two weeks." Weekends still follow the block's "Include
Saturday and Sunday slides" setting. Days skipped this way never get an
"On this day" fact, since those only appear on days without timed events.

Needs the matching `only_event_days` option in `libcal_gantt_carousel`
(libcal_gantt, 2026-10-18 or later). Older versions ignore the field.

Add it on production in Field UI: **Structure → Paragraph types → Events
carousel → Manage fields → Create a new field → Boolean**, with label
"Show only event days" and machine name `field_carousel_event_days`. Leave
it unchecked by default, and use the "Single on/off checkbox" widget on the
form display. Then hide it on Manage display, because the preprocess reads it.

Weather, hours and event settings come from the libcal_gantt settings page
at `/admin/config/services/libcal-gantt`.

## Changing machine names

All machine names are constants:

| Constant | Location | Default |
| --- | --- | --- |
| `ModularSections::BUNDLE` | `src/ModularSections.php` | `modular_page` |
| `ModularSections::FIELD` | `src/ModularSections.php` | `field_scroll_section` |
| `ModularSections::MODE_FIELD` | `src/ModularSections.php` | `field_modular_type` |
| `ModularSections::THEME` | `src/ModularSections.php` | `palefire` |
| `ModularSections::BG_FIELD` | `src/ModularSections.php` | `field_section_bg` |
| `ModularSections::BG_STYLE` | `src/ModularSections.php` | `section_background` (media view mode and responsive image style) |
| `PALEFIRE_MODULAR_BENTO_VIEW` | `palefire_modular.module` | `bento_discovery` |
| `PALEFIRE_MODULAR_BENTO_IDENTIFIER` | `palefire_modular.module` | `search_api_fulltext_bento` |

## Theme hook

`palefire_modular_toc` takes these variables:

* `items`: a list of `{ id, label }`.
* `heading`: the visible heading text.
* `depth`: `1` for sections only, `2` to add sub-links.
* `mode`: the node's `field_modular_type` value.

The module's template (`templates/palefire-modular-toc.html.twig`) is
plain markup. Palefire overrides it in
`templates/navigation/palefire-modular-toc.html.twig` and attaches
`palefire/modular-toc` there, which keeps all behaviour in the theme.

## Files

```
palefire_modular/
├── palefire_modular.info.yml
├── palefire_modular.module           hooks: theme, html, field, bento, carousel
├── src/
│   ├── ModularSections.php           section ids, snap mode, mode, theme check
│   └── Plugin/Block/ModularTocBlock.php
└── templates/
    └── palefire-modular-toc.html.twig
```

## Troubleshooting

| Problem | Things to check |
| --- | --- |
| TOC is empty | Sections have `field_section_title` filled in. Mode isn't `form`. The block is in `modular_sidebar`. |
| No snapping | `<html>` has a `pf-snap-*` class. `css/modular.css` is compiled and attached. |
| Sections have no ids | Palefire is the active theme. Run `drush cr` after adding the field template. |
| Bento tiles don't filter | Every display uses the identifier `search_api_fulltext_bento`. Use AJAX is on. |
| Background image missing | The media item is published. Anonymous users have "View media". `.pf-section--has-bg` is on the section. Run `drush cr`. |
| Background shows the media name or the wrong size | The Image media type has the `section_background` view mode enabled under Custom display settings, using the Responsive image formatter. Without it, Drupal falls back to the default display. |
| Background is blurry on phones | The `section_background` sizes value includes the `180vh` portrait rule and the 2560 style. |
| Carousel is empty | `libcal_gantt_carousel` is enabled. Show weather and the LibCal credentials are set in libcal_gantt. |

## Status and known gaps

* This code hasn't been run on a Drupal site yet. Run
  `php -l` on each PHP file and test on dev before deploying.
* `info.yml` doesn't list `drupal:views` as a dependency, even though the
  bento preprocess calls `Views::getView()`. Add it if Views might ever be
  turned off.
* Background rendering assumes the Image media type's source field holds
  an image, which is the standard setup. Don't allow other media types
  in `field_section_bg`.
* Section descriptions are cached in `drupal_static()` for the rest of
  the request, keyed by node id and revision id.
