<?php

namespace Drupal\smartcontent_smartip\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\field\FieldConfigInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\smart_content_segments\Entity\SmartSegment;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\smart_ip\SmartIp;

/**
 * Class ReactionsController.
 *
 * @package Drupal\smartcontent_smartip\Controller
 */
class ReactionsController extends ControllerBase {

  /**
   * The input parameters.
   *
   * @var string
   */
  private $inputParameters;

  /**
   * Database connection variable.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructor for ReactionsController objects.
   *
   * @param \Drupal\Core\Cache\RequestStack $requestStack
   *   Get the values from post request.
   * @param \Drupal\Core\Extension\Json $json
   *   The module handler to deal with json format.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity
   *   The Entity type manager service.
   * @param \Drupal\Core\Database\Connection $connection
   *   Database connectivity.
   */
  public function __construct(RequestStack $requestStack, Json $json, EntityTypeManagerInterface $entity, Connection $connection) {
    $request_parameters = $requestStack->getCurrentRequest()->getContent();
    $this->inputParameters = $json::decode($request_parameters);
    $this->nodeStorage = $entity->getStorage('node');
    $this->database = $connection;
  }

  /**
   * Create containers.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('serialization.json'),
      $container->get('entity_type.manager'),
      $container->get('database')
    );
  }

  /**
   * Get the reactions based on the conditions specified.
   */
  public function reaction($nid) {
    $response['data'] = [];
    // Looping the list of "Components" paragraph types.
    foreach ($this->getSmartComponents($nid) as $smart_components) {
      foreach ($smart_components as $delta => $paragraph) {
        if (empty($response['data'][$delta])) {
          // Looping the list of "Variations - Smart Paragraph" paragraph types.
          foreach ($paragraph->get('field_variations')
            ->getValue() as $field_variation) {
            $variation_paragraph = Paragraph::load($field_variation['target_id']);

            if (($variation_paragraph->getType() == 'smart_content_paragraph')
              && (empty($response['data'][$delta]))) {
              $field_smart_content_conditions = $variation_paragraph->get('field_smart_content_conditions')
                ->getValue();
              if ($this->validateVariation($field_smart_content_conditions, $response, $field_variation, $delta)) {
                $response['data'][$delta] = $field_variation['target_id'];
              }
            }
          }
        }
      }
    }

    return new JsonResponse($response);
  }

  /**
   * Function to check string starting with given substring.
   */
  public function startsWith($string, $startString) {
    $len = strlen($startString);
    return (substr($string, 0, $len) === $startString);
  }

  /**
   * Validate the conditions.
   */
  public function validateCondition($condition) {
    $result = $this->evaluateSelect($condition);
    return $result;
  }

  /**
   * Validate variations.
   */
  public function validateVariation($field_smart_content_conditions, $response, $field_variation, $delta) {
    // All conditions need to be satisfied in order to be displayed.
    $all_conditions_match = TRUE;
    foreach ($field_smart_content_conditions as $field_smart_content_condition) {
      $conditions =
        SmartSegment::load($field_smart_content_condition['target_id'])
          ->conditions_settings;

      foreach ($conditions as $variation_set_condition) {
        $all_conditions_match = $all_conditions_match
          && $this->validateCondition($variation_set_condition);
      }
    }
    return $all_conditions_match;
  }

  /**
   * Get smart content components.
   */
  public function getSmartComponents($nid) {
    $node = $this->nodeStorage->load($nid);

    $fieldsArray = $this->getContentTypeFields($node->bundle());

    $paragraphs = [];

    foreach ($fieldsArray as $fieldName => $fieldConfig) {
      if ($fieldConfig->getType() == 'entity_reference_revisions') {
        $paragraph_ids = array_map(function ($b) {
          return $b['target_id'];
        }, $node->get($fieldName)->getValue());

        $paragraphs[] = array_filter(Paragraph::loadMultiple($paragraph_ids), function ($component) {
          return $component->getType() === 'smart';
        });
      }
    }

    return $paragraphs;
  }

  /**
   * Helper function to Get all fields of content type.
   */
  public function getContentTypeFields($contentType) {

    $fields = [];

    if (!empty($contentType)) {
      $fields = array_filter(
        \Drupal::service('entity.manager')
          ->getFieldDefinitions('node', $contentType), static function ($field_definition) {
            return $field_definition instanceof FieldConfigInterface;
          }
      );
    }

    return $fields;
  }

  /**
   * Get key name from plugin ID.
   */
  public function getKeynameFromPluginId($condition) {
    preg_match('/.*:(.*)/', $condition["id"], $matches);
    return $matches[1];
  }

  /**
   * Validating select field conditions.
   */
  public function evaluateSelect($condition) {

    $user_value_key = $this->getKeynameFromPluginId($condition);

    $user_value = $this->inputParameters[$user_value_key];
    $value = $condition['conditions_type_settings']['value'];
    // IF/IF NOT for the condition.
    $negate = (bool) $condition['conditions_type_settings']['negate'];
    if ($user_value_key == 'smartipregion') {
      return $this->validateRegion($value, $user_value_key) & !$negate;
    }
    else {
      return (strtolower($user_value) == strtolower($value)) & !$negate;
    }
  }

  /**
   * Validate Regions.
   */
  public function validateRegion($region, $condition_key) {
    $query = $this->database->select('smart_content_paragraphs_regions', 'hww');
    $query->condition('hww.region', $region, '=');
    $query->fields('hww', [
      'boundsNElat',
      'boundsNElong',
      'boundsSWlat',
      'boundsSWlong',
    ]);
    $result = $query->execute()->fetchObject();

    $lat = $long = '';
    if ($condition_key == 'smartipregion') {
      SmartIp::updateUserLocation();
      $ip = $_SESSION['smart_ip']['location']['ipAddress'];
      // Hard coded ip for tesing in local.
      $ip = '2401:4900:44e6:6aa:3098:d15:c5a1:3d4';
      $location = SmartIp::query($ip);
      if (isset($location['countryCode'])) {
        $lat = $location['latitude'];
        $long = $location['longitude'];
      }
    }

    $eastBound = $long < $result->boundsNElong;
    $westBound = $long > $result->boundsSWlong;

    if ($result->boundsNElong < $result->boundsSWlong) {
      $inLong = $eastBound || $westBound;
    }
    else {
      $inLong = $eastBound && $westBound;
    }

    $inLat = $lat > $result->boundsSWlat
      && $lat < $result->boundsNElat;
    return $inLat && $inLong;
  }

}
