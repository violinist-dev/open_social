<?php

namespace Drupal\social_language;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\MetadataBubblingUrlGenerator;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;

/**
 * Class SocialLanguageMetadataBubblingUrlGenerator.
 *
 * @package Drupal\social_language
 */
class SocialLanguageMetadataBubblingUrlGenerator extends MetadataBubblingUrlGenerator {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a new bubbling URL generator service.
   *
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The non-bubbling URL generator.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(UrlGeneratorInterface $url_generator, RendererInterface $renderer, LanguageManagerInterface $language_manager) {
    parent::__construct($url_generator, $renderer);

    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function generateFromRoute($name, $parameters = [], $options = [], $collect_bubbleable_metadata = FALSE) {
    if (isset($options['language']) && $options['language'] instanceof LanguageInterface) {
      $language = $this->languageManager->getCurrentLanguage();

      // By default we change the link language if it differs from the current.
      $reset_language = $options['language']->getId() != $language->getId();

      if (isset($options['entity'])) {
        $entity_type = $options['entity']->getEntityTypeId();

        // Ignore the following uri relationships because they are language
        // specific. e.g. a node edit form editing a translation.
        // Entity::toURL does something similar for entity add pages.
        $allowed_rel = [
          'edit-form',
          'cancel-form',
          'delete-form',
          'drupal:content-translation-add',
          'drupal:content-translation-edit',
          'drupal:content-translation-delete',
        ];

        // Allow other modules to add their own language specific links.
        \Drupal::moduleHandler()->alter('social_language_allowed_rels', $allowed_rel);

        $exception_routes = [];

        foreach ($allowed_rel as $rel) {
          $exception_routes[] = "entity.{$entity_type}." . str_replace(['-', 'drupal:'], ['_', ''], $rel);
        }

        if (in_array($name, $exception_routes)) {
          $reset_language = FALSE;
        }
      }

      // If we're on a path where there are links intentionally leading to
      // pages in other languages then we also don't modify any routes.
      // An example is the node translation overview page.
      $current_route = \Drupal::routeMatch()->getRouteName();

      $unmodified_pages = [
        'content_translation_overview'
      ];

      // Allow other modules to add their own pages where this is disabled.
      \Drupal::moduleHandler()->alter('social_language_unmodified_pages', $unmodified_pages);

      // Route names like entity.<node_type>.translation_overview
      // $unmodified_pages = ['entity']; disables this for all entity routes.
      $route_parts = explode('.', $current_route);

      foreach ($unmodified_pages as $page) {
        if (in_array($page, $route_parts)) {
          $reset_language = FALSE;
          break;
        }
      }

      if ($reset_language) {
//        $options['language'] = $language;
      }
    }

    return parent::generateFromRoute($name, $parameters, $options, $collect_bubbleable_metadata);
  }

}
