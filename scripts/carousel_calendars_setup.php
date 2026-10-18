<?php

/**
 * @file
 * Adds the Events carousel paragraph's checkbox fields:
 * - Calendars (field_carousel_calendars): the calendar tabs from the LibCal
 *   Gantt "Calendars" setting. Several are merged into one carousel.
 *   Nothing ticked uses the first calendar (or the older
 *   field_carousel_calendar text field, if that has a value).
 * - Libraries (field_carousel_campuses): the libraries from "Campus rows",
 *   like LibCal's "LSU Libraries" filter (262 = Hill Memorial Library).
 *   Nothing ticked shows every library.
 *
 * The options come from palefire_modular_carousel_calendar_options() and
 * palefire_modular_carousel_campus_options(), allowed_values_functions,
 * which Field UI can't set. That's why this is a script.
 *
 * Run from this module's folder on production:
 *   drush php:script scripts/carousel_calendars_setup.php
 *   drush cr
 *
 * Safe to re-run: anything that already exists is left as it is.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\paragraphs\Entity\ParagraphsType;

if (PHP_SAPI !== 'cli') {
  return;
}

$bundle = 'events_carousel';

if (!ParagraphsType::load($bundle)) {
  echo "Paragraph type $bundle not found.\n";
  return;
}

// [field, label, description, options function], in form order.
$fields = [
  [
    'field_carousel_calendars',
    'Calendars',
    'Tick one or more. Several are merged into one carousel. Leave all unticked to use the first calendar. The list comes from "Calendars" on the LibCal Gantt settings page.',
    'palefire_modular_carousel_calendar_options',
  ],
  [
    'field_carousel_campuses',
    'Libraries',
    'Only show events from these libraries, like the "LSU Libraries" filter on the LibCal calendar. Building hours are limited to them too. Leave all unticked for every library. The list comes from "Campus rows" on the LibCal Gantt settings page.',
    'palefire_modular_carousel_campus_options',
  ],
];

$repo = \Drupal::service('entity_display.repository');
$form = $repo->getFormDisplay('paragraph', $bundle, 'default');
$view = $repo->getViewDisplay('paragraph', $bundle, 'default');
// Next to the older single-key field when it's there.
$weight = ($old = $form->getComponent('field_carousel_calendar')) ? (int) $old['weight'] : 10;

foreach ($fields as [$name, $label, $description, $function]) {
  if (!function_exists($function)) {
    echo "$function() not found: git pull palefire_modular first.\n";
    continue;
  }
  if (!FieldStorageConfig::loadByName('paragraph', $name)) {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => 'paragraph',
      'type' => 'list_string',
      'cardinality' => -1,
      'settings' => [
        'allowed_values' => [],
        'allowed_values_function' => $function,
      ],
    ])->save();
    echo "Created storage paragraph.$name\n";
  }
  if (!FieldConfig::loadByName('paragraph', $bundle, $name)) {
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => 'paragraph',
      'bundle' => $bundle,
      'label' => $label,
      'description' => $description,
      'required' => FALSE,
    ])->save();
    echo "Added $name to paragraph.$bundle\n";
  }
  if (!$form->getComponent($name)) {
    $form->setComponent($name, ['type' => 'options_buttons', 'weight' => $weight]);
    echo "Added the $label checkboxes to the form\n";
  }
  $weight++;
  // The preprocess reads the value; nothing prints on the page.
  $view->removeComponent($name);
}
$form->save();
$view->save();

echo "Done. Run drush cr.\n";
