<?php

namespace Drupal\language_and_country\Plugin\LanguageNegotiation;

use Drupal\Core\Language\LanguageInterface;
use Drupal\language\LanguageNegotiationMethodBase;
use Drupal\taxonomy\TermStorageInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Url;
use Drupal\language\LanguageSwitcherInterface;

/**
 * Class for identifying language and country from URL.
 *
 * @LanguageNegotiation(
 *   weight = -8,
 *   name = @Translation("Language and country by URL"),
 *   types = {
 *    \Drupal\Core\Language\LanguageInterface::TYPE_INTERFACE,
 *    \Drupal\Core\Language\LanguageInterface::TYPE_CONTENT,
 *    \Drupal\Core\Language\LanguageInterface::TYPE_URL
 *   },
 *   description = @Translation("Provides possibility to display to URL Country code with language"),
 *   id = Drupal\language_and_country\Plugin\LanguageNegotiation\LanguageAndCountryUrlNegotiationMethod::METHOD_ID
 * )
 */
class LanguageAndCountryUrlNegotiationMethod extends LanguageNegotiationMethodBase implements ContainerFactoryPluginInterface, InboundPathProcessorInterface, OutboundPathProcessorInterface, LanguageSwitcherInterface {

  /**
   * The language negotiation method id.
   */
  const METHOD_ID = 'language-and-country-url';

  /**
   * The vocabulary id.
   */
  const VID = 'country_list';

  /**
   * The taxonomy storage.
   *
   * @var \Drupal\Taxonomy\TermStorageInterface
   */
  protected $termStorage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('entity.manager')->getStorage('taxonomy_term')
    );
  }

  /**
   * Builds the class.
   *
   * @param \Drupal\Taxonomy\TermStorageInterface $term_storage
   *   The taxonomy term storage.
   */
  public function __construct(TermStorageInterface $term_storage) {
    $this->termStorage = $term_storage;
  }

  /**
   * Helper function to provide contry code list.
   *
   * @return array
   *   The array of country code of terms.
   */
  protected function getCountryList() {
    $list = [];

    foreach ((array) $this->termStorage->loadTree(
      LanguageAndCountryUrlNegotiationMethod::VID,
      0,
      1,
      TRUE
    ) as $term) {
      $list[$term->id()] = $term->get('field_country_code')->first()->getValue()['value'];
    }

    return $list;
  }

  /**
   * {@inheritdoc}
   */
  public function getLangcode(Request $request = NULL) {
    if ($request && $this->languageManager) {
      $languages = $this->languageManager->getLanguages();
      $countries = $this->getCountryList();
      $request_path = urldecode(trim($request->getPathInfo(), '/'));
      $path_args = explode('/', $request_path);
      $prefix = array_shift($path_args);

      foreach ($countries as $country) {
        foreach ($languages as $language) {
          if ("{$country}-{$language->getId()}" == $prefix) {
            return $language->getId();
          }
        }
      }
    }

    return FALSE;
  }

  /**
   * Performs country negotiation.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   (optional) The current request. Defaults to NULL if it has not been
   *   initialized yet.
   *
   * @return string
   *   A valid country code or FALSE if the negotiation was unsuccessful.
   */
  public function getCountryCode(Request $request = NULL) {
    if ($request && $this->languageManager) {
      $languages = $this->languageManager->getLanguages();
      $countries = $this->getCountryList();
      $request_path = urldecode(trim($request->getPathInfo(), '/'));
      $path_args = explode('/', $request_path);
      $prefix = array_shift($path_args);

      foreach ($countries as $country) {
        foreach ($languages as $language) {
          if ("{$country}-{$language->getId()}" == $prefix) {
            return $country;
          }
        }
      }
    }

    return FALSE;
  }

  /**
   * Provide default country term.
   */
  public function getDefaultCountryTerm() {
    $list = $this->getCountryList();

    return $this->termStorage->load(key($list));
  }

  /**
   * Performs country negotiation.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   (optional) The current request. Defaults to NULL if it has not been
   *   initialized yet.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   A valid country term or NULL if the negotiation was unsuccessful.
   */
  public function getCountryTerm(Request $request = NULL) {
    if ($request && $this->languageManager) {
      $languages = $this->languageManager->getLanguages();
      $countries = $this->getCountryList();
      $request_path = urldecode(trim($request->getPathInfo(), '/'));
      $path_args = explode('/', $request_path);
      $prefix = array_shift($path_args);

      foreach ($countries as $tid => $country) {
        foreach ($languages as $language) {
          if ("{$country}-{$language->getId()}" == $prefix) {
            return $this->termStorage->load($tid);
          }
        }
      }
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request) {
    $parts = explode('/', trim($path, '/'));
    $prefix = array_shift($parts);
    $languages = $this->languageManager->getLanguages();
    $countries = $this->getCountryList();

    // Search prefix within added languages.
    foreach ($countries as $tid => $country) {
      foreach ($languages as $language) {
        if ("{$country}-{$language->getId()}" == $prefix) {
          // Rebuild $path with the language removed.
          $path = '/' . implode('/', $parts);
          break;
        }
      }
    }

    return $path;
  }

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], Request $request = NULL, BubbleableMetadata $bubbleable_metadata = NULL) {
    $languages = array_flip(array_keys($this->languageManager->getLanguages()));

    // Language can be passed as an option, or we go for current URL language.
    if (empty($options['language'])) {
      $options['language'] = $this->languageManager->getLanguage($this->getLangcode($request));
    }
    elseif (is_string($options['language']) && !empty($languages[$options['language']])) {
      $options['language'] = $this->languageManager->getLanguage($options['language']);
    }

    if (empty($options['language'])) {
      $options['language'] = $this->languageManager->getDefaultLanguage();
    }

    if (empty($options['country']) && $request) {
      $options['country'] = $this->getCountryTerm($request);
    }

    if (empty($options['country'])) {
      $options['country'] = $this->getDefaultCountryTerm();
    }

    if (is_object($options['language']) && is_object($options['country'])) {
      $lang = $options['language']->getId();
      $code = $options['country']->get('field_country_code')->first()->getValue()['value'];
      $options['prefix'] = "{$code}-{$lang}/";

      if ($bubbleable_metadata) {
        $bubbleable_metadata->addCacheContexts(['languages:' . LanguageInterface::TYPE_URL]);
      }
    }

    return $path;
  }

  /**
   * {@inheritdoc}
   */
  public function getLanguageSwitchLinks(Request $request, $type, Url $url) {
    $links = [];
    $query = $request->query->all();
    $country = $this->getCountryTerm($request);

    foreach ($this->languageManager->getNativeLanguages() as $language) {
      $links[$language->getId()] = [
        // We need to clone the $url object to avoid using the same one for all
        // links. When the links are rendered, options are set on the $url
        // object, so if we use the same one, they would be set for all links.
        'url' => clone $url,
        'title' => $language->getName(),
        'language' => $language,
        'country' => $country,
        'attributes' => ['class' => ['language-link']],
        'query' => $query,
      ];
    }

    return $links;
  }

  /**
   * Returns country switch links.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param string $type
   *   The language type.
   * @param \Drupal\Core\Url $url
   *   The URL the switch links will be relative to.
   *
   * @return array
   *   An array of link arrays keyed by language code and country.
   */
  public function getCountrySwitchLinks(Request $request, $type, Url $url) {
    $links = [];
    $query = $request->query->all();
    $langcode = $this->getLangcode($request);
    $language = $this->languageManager->getLanguage($langcode);

    foreach ($this->getCountryList() as $tid => $code) {
      /** @var \Drupal\taxonomy\TermInterface $term */
      $term = $this->termStorage->load($tid);
      $links[$tid] = [
        // We need to clone the $url object to avoid using the same one for all
        // links. When the links are rendered, options are set on the $url
        // object, so if we use the same one, they would be set for all links.
        'url' => clone $url,
        'title' => $term->getName(),
        'language' => $language,
        'country' => $term,
        'attributes' => ['class' => ['country-link']],
        'query' => $query,
      ];
    }

    return $links;
  }

}
