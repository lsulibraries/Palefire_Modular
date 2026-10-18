<?php

/**
 * @file
 * Adds "Calendars" (field_carousel_calendars) to the Events carousel paragraph.
 *
 * Checkboxes listing the calendar tabs from the LibCal Gantt "Calendars"
 * setting. Tick one or more: several are merged into one carousel. Nothing
 * ticked uses the first calendar (or the older field_carousel_calendar text
 * field, if that has a value).
 *
 * The options come from palefire_modular_carousel_calendar_options(), an
 * allowed_values_function, which Field UI can't set. That's why this is a
 * script.
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
$name = 'field_carousel_calendars';

if (!ParagraphsType::load($bundle)) {
  echo "Paragraph type $bundle not found.\n";
  return;
}
if (!function_exists('palefire_modular_carousel_calendar_options')) {
  echo "palefire_modular_carousel_calendar_options() not found: git pull palefire_modular and run drush cr first.\n";
  return;
}

if (!FieldStorageConfig::loadByName('paragraph', $name)) {
  FieldStorageConfig::create([
    'field_name' => $name,
    'entity_type' => 'paragraph',
    'type' => 'list_string',
    'cardinality' => -1,
    'settings' => [
      'allowed_values' => [],
      'allowed_values_function' => 'palefire_modular_carousel_calendar_options',
    ],
  ])->save();
  echo "Created storage paragraph.$name\n";
}

if (!FieldConfig::loadByName('paragraph', $bundle, $name)) {
  FieldConfig::create([
    'field_name' => $name,
    'entity_type' => 'paragraph',
    'bundle' => $bundle,
    'label' => 'Calendars',
    'description' => 'Tick one or more. Several are merged into one carousel. Leave all unticked to use the first calendar. The list comes from "Calendars" on the LibCal Gantt settings page.',
    'required' => FALSE,
  ])->save();
  echo "Added $name to paragraph.$bundle\n";
}

$repo = \Drupal::service('entity_display.repository');
$form = $repo->getFormDisplay('paragraph', $bundle, 'default');
if (!$form->getComponent($name)) {
  // Next to the older single-key field when it's there.
  $old = $form->getComponent('field_carousel_calendar');
  $form->setComponent($name, [
    'type' => 'options_buttons',
    'weight' => $old ? $old['weight'] : 10,
  ]);
  $form->save();
  echo "Added the Calendars checkboxes to the form\n";
}

// The preprocess reads the value; nothing prints on the page.
$view = $repo->getViewDisplay('paragraph', $bundle, 'default');
$view->removeComponent($name)->save();

echo "Done. Run drush cr.\n";
