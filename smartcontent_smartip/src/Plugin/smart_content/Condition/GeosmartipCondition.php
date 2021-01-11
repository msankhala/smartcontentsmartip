<?php

namespace Drupal\smartcontent_smartip\Plugin\smart_content\Condition;

use Drupal\smart_content\Condition\ConditionTypeConfigurableBase;

/**
 * Provides a default Smart Condition.
 *
 * @SmartCondition(
 *   id = "geosmartip",
 *   label = @Translation("Geolocation - Smart Ip"),
 *   group = "geosmartip",
 *   weight = 0,
 *   deriver = "Drupal\smartcontent_smartip\Plugin\Derivative\GeosmartipDerivative"
 * )
 */
class GeosmartipCondition extends ConditionTypeConfigurableBase {

}
