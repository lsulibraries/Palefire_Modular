<?php

declare(strict_types=1);

namespace Drupal\palefire_modular\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\palefire_modular\ModularSections;

/**
 * "On this page" navigation for modular_page nodes.
 *
 * The top-level links are rendered on the server from the paragraphs, so
 * they work without JavaScript and show up on first paint. Palefire's
 * modular-toc.js then tracks the active section on scroll and, in Guide
 * mode, adds h3 sub-links.
 */
#[Block(
  id: 'palefire_modular_toc',
  admin_label: new TranslatableMarkup('Modular page: table of contents'),
)]
class ModularTocBlock extends BlockBase {

  public function defaultConfiguration(): array {
    return [
      'heading' => 'On this page',
      'hidden_modes' => ['form'],
      'deep_modes' => ['guide'],
    ] + parent::defaultConfiguration();
  }

  public function blockForm($form, FormStateInterface $form_state): array {
    $c = $this->getConfiguration();
    $form['heading'] = [
      '#type' => 'textfield', '#title' => $this->t('Heading'), '#default_value' => $c['heading'],
    ];
    $form['hidden_modes'] = [
      '#type' => 'textfield', '#title' => $this->t('Hide on these modes'),
      '#default_value' => implode(', ', $c['hidden_modes']),
      '#description' => $this->t('Comma-separated values of field_modular_type.'),
    ];
    $form['deep_modes'] = [
      '#type' => 'textfield', '#title' => $this->t('Include h3 sub-links on these modes'),
      '#default_value' => implode(', ', $c['deep_modes']),
    ];
    return $form;
  }

  public function blockSubmit($form, FormStateInterface $form_state): void {
    $split = static fn ($v): array => array_values(array_filter(array_map('trim', explode(',', (string) $v))));
    $this->configuration['heading'] = trim((string) $form_state->getValue('heading'));
    $this->configuration['hidden_modes'] = $split($form_state->getValue('hidden_modes'));
    $this->configuration['deep_modes'] = $split($form_state->getValue('deep_modes'));
  }

  public function build(): array {
    $build = [];
    $cache = (new CacheableMetadata())->addCacheContexts(['route']);

    $node = ModularSections::currentNode();
    if ($node) {
      $cache->addCacheableDependency($node);
      $mode = ModularSections::mode($node);
      $c = $this->getConfiguration();

      if (!in_array($mode, $c['hidden_modes'], TRUE)) {
        $items = [];
        foreach (ModularSections::describe($node) as $section) {
          if ($section['toc']) {
            $items[] = ['id' => $section['id'], 'label' => $section['label']];
          }
        }
        if ($items) {
          $build = [
            '#theme' => 'palefire_modular_toc',
            '#items' => $items,
            '#heading' => $c['heading'],
            '#depth' => in_array($mode, $c['deep_modes'], TRUE) ? 2 : 1,
            '#mode' => $mode,
          ];
          if (ModularSections::isPalefire()) {
            $build['#attached']['library'][] = 'palefire/modular-toc';
          }
      }
    }

    $cache->applyTo($build);
    return $build;
  }

  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), ['route']);
  }

}
