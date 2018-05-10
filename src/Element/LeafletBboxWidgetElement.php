<?php

namespace Drupal\leaflet_bbox_widget\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\geofield\Element\GeofieldBounds;

/**
 * Provides a Leaflet Geofield bounds form element.
 *
 * @FormElement("leaflet_bbox_widget_element")
 */
class LeafletBboxWidgetElement extends GeofieldBounds {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#input' => TRUE,
      '#process' => [
        [$class, 'elementProcess'],
      ],
      '#element_validate' => [
        [$class, 'boundsValidate'],
      ],
      '#theme' => 'leaflet_bbox_widget_element',
      '#theme_wrappers' => ['fieldset'],
    ];
  }

  /**
   * Generates a Geofield generic component based form element.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   element. Note that $element must be taken by reference here, so processed
   *   child elements are taken over into $form_state.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The processed element.
   */
  public static function elementProcess(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $element['#tree'] = TRUE;
    $element['#input'] = TRUE;

    foreach (static::$components as $name => $component) {
      $element[$name] = [
        '#type' => 'hidden',
        // The t func is needed to make component translatable throw interface.
        '#required' => (!empty($element['#required'])) ? $element['#required'] : FALSE,
        '#default_value' => (isset($element['#default_value'][$name])) ? $element['#default_value'][$name] : '',
        '#attributes' => [
          'class' => ['geofield-' . $name],
        ],
      ];
    }

    unset($element['#value']);
    // Set this to false always to prevent notices.
    $element['#required'] = FALSE;

    return $element;
  }

  /**
   * Validates a Geofield bounds element.
   *
   * @param array $element
   *   The element being processed.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   */
  public static function boundsValidate(array &$element, FormStateInterface $form_state, array &$complete_form) {
    static::elementValidate($element, $form_state, $complete_form);

    $pairs = [
      [
        'bigger' => 'top',
        'smaller' => 'bottom',
      ],
      [
        'bigger' => 'right',
        'smaller' => 'left',
      ],
    ];

    foreach ($pairs as $pair) {
      if ($element[$pair['smaller']]['#value'] >= $element[$pair['bigger']]['#value']) {
        $form_state->setError(
          $element[$pair['smaller']],
          t('@title: @component_bigger must be greater than @component_smaller.', [
            '@title' => $element['#title'],
            '@component_bigger' => static::$components[$pair['bigger']]['title'],
            '@component_smaller' => static::$components[$pair['smaller']]['title'],
          ])
        );
      }
    }
  }

}
