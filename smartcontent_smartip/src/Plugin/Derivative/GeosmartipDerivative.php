<?php

namespace Drupal\smartcontent_smartip\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;

/**
 * Deriver for GeosmartipCondition.
 *
 * Provides a deriver for
 * Drupal\smartcontent_smartip\Plugin\smart_content\Condition\GeosmartipCondition.
 * Definitions are based on properties available in JS from user's browser.
 */
class GeosmartipDerivative extends DeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives = [
      'smartipregion' => [
        'label' => 'Region',
        'type' => 'select',
        'options_callback' => [get_class($this), 'getRegionOptions'],
      ] + $base_plugin_definition,
    ];
    return $this->derivatives;
  }

  /**
   * Returns list of 'Regions' for select element.
   *
   * @return array
   *   Array of Regions.
   */
  public static function getRegionOptions() {
    $file = fopen(drupal_get_path('module', 'smart_content_paragraphs') . '/data/region_codes.csv', "r");
    $region_codes = [];
    while (!feof($file)) {
      $regions = fgetcsv($file);
      if (!empty($regions[2])) {
        $country_name = self::getCountryNameFromCode($regions[0]);
        $region_codes[$country_name][$regions[2]] = $regions[2];
      }
    }
    ksort($region_codes);
    foreach ($region_codes as $key => $value) {
      ksort($value);
      $region_codes[$key] = $value;
    }
    return $region_codes;
  }

  /**
   * Getting country name from Country code.
   *
   * @return array
   *   Array of Country Name.
   */
  public static function getCountryNameFromCode($country_code) {
    $country_list = \Drupal::service('country_manager')->getList();
    foreach ($country_list as $key => $value) {
      if ($key == $country_code) {
        return $value->__toString();
      }
    }
  }

}
