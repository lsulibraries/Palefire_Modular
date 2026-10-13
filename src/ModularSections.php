<?php

declare(strict_types=1);

namespace Drupal\palefire_modular;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Render\Element;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Describes the scroll sections of one modular_page node.
 *
 * Used by the field preprocess (section ids and classes), the html
 * preprocess (snap mode) and the TOC block. All three read the same
 * description, so a TOC link always matches its section id.
 */
final class ModularSections {

  public const BUNDLE = 'modular_page';
  public const FIELD = 'field_scroll_section';
  public const MODE_FIELD = 'field_modular_type';
  public const THEME = 'palefire';

  /**
   * The background image field, and the name shared by the media view mode
   * and the responsive image style used to render it.
   */
  public const BG_FIELD = 'field_section_bg';
  public const BG_STYLE = 'section_background';

  /**
   * Ids that would collide with ids the page itself uses.
   */
  private const RESERVED_IDS = ['main', 'main-content', 'header', 'footer', 'page', 'top', 'toc'];

  /**
   * @return array<int|string, array{id: string, label: string, bundle: string, snap: bool, height: string, theme: string, toc: bool, delta: int}>
   *   Keyed by paragraph id, in field order. Unpublished and inaccessible
   *   paragraphs are left out, matching what is rendered.
   */
  public static function describe(NodeInterface $node): array {
    $cache = &drupal_static(__METHOD__, []);
    $key = ($node->id() ?? spl_object_id($node)) . ':' . $node->getRevisionId();
    if (isset($cache[$key])) {
      return $cache[$key];
    }

    $sections = [];
    $used = array_flip(self::RESERVED_IDS);
    if ($node->bundle() === self::BUNDLE && $node->hasField(self::FIELD)) {
      foreach ($node->get(self::FIELD) as $delta => $item) {
        $paragraph = $item->entity;
        if (!$paragraph instanceof ParagraphInterface || !$paragraph->isPublished() || !$paragraph->access('view')) {
          continue;
        }

        $label = self::text($paragraph, 'field_section_title');
        $anchor = self::text($paragraph, 'field_section_anchor');
        $base = Html::getId($anchor !== '' ? $anchor : ($label !== '' ? $label : $paragraph->bundle()));
        $id = $base;
        for ($i = 2; isset($used[$id]); $i++) {
          $id = $base . '-' . $i;
        }
        $used[$id] = TRUE;

        $sections[$paragraph->id()] = [
          'id' => $id,
          'label' => $label,
          'bundle' => $paragraph->bundle(),
          'snap' => self::bool($paragraph, 'field_section_snap'),
          'height' => self::text($paragraph, 'field_section_height') ?: 'auto',
          'theme' => self::text($paragraph, 'field_section_theme') ?: 'light',
          'toc' => $label !== '' && !self::bool($paragraph, 'field_section_toc_hide'),
          'delta' => (int) $delta,
        ];
      }
    }

    return $cache[$key] = $sections;
  }

  /**
   * NULL (no snapping), 'mandatory' (every section snaps) or 'proximity'.
   *
   * Mandatory snapping skips any section that has no snap point, so when
   * only some sections snap the page uses proximity instead.
   */
  public static function snapMode(array $sections): ?string {
    $snapping = count(array_filter($sections, static fn (array $s): bool => $s['snap']));
    if ($snapping === 0) {
      return NULL;
    }
    return $snapping === count($sections) ? 'mandatory' : 'proximity';
  }

  /**
   * The editor's Mode choice: page, guide or form (or any value you add).
   */
  public static function mode(NodeInterface $node): string {
    return $node->hasField(self::MODE_FIELD) ? (self::text($node, self::MODE_FIELD) ?: 'page') : 'page';
  }

  /**
   * TRUE when Palefire (or a sub-theme of it) is rendering the page.
   *
   * Every preprocess in this module checks this first, so Origami's
   * markup is never changed while it is still the default theme.
   */
  public static function isPalefire(): bool {
    $theme = \Drupal::theme()->getActiveTheme();
    return $theme->getName() === self::THEME || array_key_exists(self::THEME, $theme->getBaseThemeExtensions());
  }

  /**
   * The modular_page node for the current route (canonical, revision, preview).
   */
  public static function currentNode(): ?NodeInterface {
    $match = \Drupal::routeMatch();
    foreach (['node', 'node_preview', 'node_revision'] as $param) {
      $value = $match->getParameter($param);
      if ($value instanceof NodeInterface && $value->bundle() === self::BUNDLE) {
        return $value;
      }
    }
    return NULL;
  }

  private static function text(FieldableEntityInterface $entity, string $field): string {
    return $entity->hasField($field) && !$entity->get($field)->isEmpty()
      ? trim((string) $entity->get($field)->value)
      : '';
  }

  private static function bool(FieldableEntityInterface $entity, string $field): bool {
    return $entity->hasField($field) && !$entity->get($field)->isEmpty() && (bool) $entity->get($field)->value;
  }

  /**
   * Render array for a section's background image.
   *
   * A Media reference renders in the "section_background" media view mode,
   * which should use the "section_background" responsive image style. A
   * plain Image field uses that responsive image style directly, or the
   * original image if Responsive Image or the style is missing.
   *
   * The result is always returned, even when it has no visible items (for
   * example, unpublished media), so its cache metadata can still bubble.
   * Use hasItems() to decide whether to print it.
   */
  public static function background(ParagraphInterface $paragraph): array {
    if (!$paragraph->hasField(self::BG_FIELD) || $paragraph->get(self::BG_FIELD)->isEmpty()) {
      return [];
    }
    $items = $paragraph->get(self::BG_FIELD);
    if ($items->getFieldDefinition()->getType() === 'image') {
      $responsive = \Drupal::moduleHandler()->moduleExists('responsive_image')
        && \Drupal::entityTypeManager()->getStorage('responsive_image_style')->load(self::BG_STYLE);
      $display = $responsive
        ? ['type' => 'responsive_image', 'settings' => ['responsive_image_style' => self::BG_STYLE, 'image_link' => '']]
        : ['type' => 'image', 'settings' => ['image_style' => '', 'image_link' => '']];
    }
    else {
      $display = ['type' => 'entity_reference_entity_view', 'settings' => ['view_mode' => self::BG_STYLE, 'link' => FALSE]];
    }
    return $items->view($display + ['label' => 'hidden']);
  }

  /**
   * TRUE when a field render array has at least one item to show.
   */
  public static function hasItems(array $build): bool {
    return (bool) Element::children($build);
  }

}
