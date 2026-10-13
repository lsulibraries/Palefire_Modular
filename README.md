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
* The Palefire theme files from this kit: the templates, the `modular`,
  `modular-toc` and `bento-search` libraries, and the `modular_sidebar`
  region.

## Install

1. Copy this folder to `web/modules/custom/palefire_modular`.
2. Run `drush en palefire_modular -y && drush cr`.
3. Create the paragraph fields and types listed below.
4. In Palefire, place the "Modular page: table of contents" block in the
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
| `field_section_bg` | Image, or Media (image) | Outputs as `data-bg` for the background crossfade |

### Section paragraph types

| Paragraph type | Optional fields | Notes |
| --- | --- | --- |
| `bento_search` | `field_bento_placeholder` | Its `<h2>` is visually hidden, because the search form labels the section |
| `events_carousel` | `field_carousel_days` (integer), `field_carousel_calendar` (libcal_gantt calendar key), `field_carousel_interval` (ms, `0` = no autoplay) | Embeds the `libcal_gantt_day_carousel` block |
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
| `field_carousel_calendar` | `calendar` |
| `field_carousel_interval` | `interval` |

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
| Carousel is empty | `libcal_gantt_carousel` is enabled. Show weather and the LibCal credentials are set in libcal_gantt. |

## Status and known gaps

* This code hasn't been run on a Drupal site yet. Run
  `php -l` on each PHP file and test on dev before deploying.
* `info.yml` doesn't list `drupal:views` as a dependency, even though the
  bento preprocess calls `Views::getView()`. Add it if Views might ever be
  turned off.
* Section descriptions are cached in `drupal_static()` for the rest of
  the request, keyed by node id and revision id.
