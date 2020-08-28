<?php

namespace Drupal\lane_donations;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\config_pages\ConfigPagesLoaderService;

/**
 * Class WhereDidYouHearAboutUsOptions.
 */
class WhereDidYouHearAboutUsOptions {

  use StringTranslationTrait;

  /**
   * @var ConfigPagesLoaderService
   */
  protected $config_page_loader;

  protected $config_page;

  /**
   * @var array The parsed options
   */
  protected $options;

  /**
   * Constructs a new WhereDidYouHearAboutUsOptions object.
   */
  public function __construct(ConfigPagesLoaderService $config_page_loader)
  {
    $this->config_page_loader = $config_page_loader;
    $this->config_page = $this->config_page_loader->load('site_settings');
  }

  public function load()
  {

    $options = $this->config_page->field_where_did_you_hear_options->value;
    $options = array_map('trim', explode("\n", $options));
    $options = array_filter($options);

    // Split the options into key / value pairs
    $this->options = [];
    foreach ($options as $option) {
      $parts = explode('|', $option);
      $this->options[trim($parts[0])] = trim($parts[1]);
    }

  }

  public function getOptions()
  {
    if (!$this->options) {
      $this->load();
    }
    return $this->options;
  }

  public function getSelectOptions()
  {
    if (!$this->options) {
      $this->load();
    }
    return array_merge(['' => $this->t('Please select')->render()], $this->options);
  }

}
