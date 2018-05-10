<?php

namespace Drupal\leaflet_bbox_widget\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\geofield\Plugin\Field\FieldWidget\GeofieldBoundsWidget;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Leaflet\LeafletService;
use Drupal\geofield\GeoPHP\GeoPHPInterface;
use Drupal\geofield\WktGeneratorInterface;


/**
 * Plugin implementation of the 'leaflet_bbox_default_widget' widget.
 *
 * @FieldWidget(
 *   id = "leaflet_bbox_default_widget",
 *   label = @Translation("Leaflet Bbox default widget"),
 *   field_types = {
 *     "geofield"
 *   }
 * )
 */
class LeafletBboxDefaultWidget extends GeofieldBoundsWidget {

  /**
   * Leaflet service.
   *
   * @var \Drupal\Leaflet\LeafletService
   */
  protected $leafletService;


  /**
   * GeofieldBaseWidget constructor.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param array $third_party_settings
   *   Any third party settings settings.
   * @param \Drupal\geofield\GeoPHP\GeoPHPInterface $geophp_wrapper
   *   The geoPhpWrapper.
   * @param \Drupal\geofield\WktGeneratorInterface $wkt_generator
   *   The WKT format Generator service.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    GeoPHPInterface $geophp_wrapper,
    WktGeneratorInterface $wkt_generator,
    LeafletService $leaflet_service
  )
  {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings, $geophp_wrapper, $wkt_generator);
    $this->leafletService = $leaflet_service;
  }


  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('geofield.geophp'),
      $container->get('geofield.wkt_generator'),
      $container->get('leaflet.service')
    );
  }


  /**
   * {@inheritdoc}
   */
  public static function defaultSettings()
  {
    return [
        'height' => 400,
        'width' => 0,
        'style_url' => '',
        'token' => '',
        'zoomlevel' => '',
      ] + parent::defaultSettings();
  }


  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state)
  {
    $elements = parent::settingsForm($form, $form_state);

    $elements['height'] = [
      '#title' => $this->t('Map Height'),
      '#type' => 'number',
      '#default_value' => $this->getSetting('height'),
      '#field_suffix' => $this->t('px'),
    ];
    $elements['width'] = [
      '#title' => $this->t('Map Width'),
      '#type' => 'number',
      '#default_value' => $this->getSetting('width'),
      '#field_suffix' => $this->t('px'),
    ];
    $elements['style_url'] = [
      '#type' => 'textfield',
      '#title' => t('Style URL'),
      '#default_value' => $this->getSetting('style_url'),
      '#description' => t('Copy and paste the style URL. Example: %url.', array(
        '%url' => 'mapbox://styles/johndoe/erl4zrwto008ob3f2ijepsbszg',
      )),
      '#required' => TRUE,
    ];
    $elements['token'] = [
      '#type' => 'textfield',
      '#title' => t('Map access token'),
      '#required' => TRUE,
      '#default_value' => $this->getSetting('token'),
      '#description' => t('You will find this in the mapbox user account settings'),
    ];
    $elements['zoomlevel'] = [
      '#type' => 'textfield',
      '#title' => t('Zoom Level'),
      '#required' => TRUE,
      '#default_value' => $this->getSetting('zoomlevel'),
      '#description' => t('It should be set to 0 for correct work bounds fit. You must clear the site caches after changing this value or wait for the caches to expire before this change shows.'),
    ];

    return $elements;
  }


  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $bounds_value = [];

    foreach ($this->components as $component) {
      $bounds_value[$component] = isset($items[$delta]->{$component}) ? floatval($items[$delta]->{$component}) : '';
    }

    $element += [
      '#type' => 'leaflet_bbox_widget_element',
      '#default_value' => $bounds_value,
    ];

    $element['map'] = $this->buildMapElement($items, $delta, $bounds_value);

    $element['#attributes']['data-widget-map-id'] = $element['map']['#map_id'];

    return ['value' => $element];
  }

  /**
   * Build leaflet map element.
   */
  public function buildMapElement(FieldItemListInterface $items, $delta, $bounds_value){
    //Prepare leaflet map element
    $settings = [
      'attributionControl' => TRUE,
      'closePopupOnClick' => TRUE,
      'doubleClickZoom' => TRUE,
      'dragging' => TRUE,
      'fadeAnimation' => TRUE,
      'layerControl' => FALSE,
      'maxZoom' => 19,
      'minZoom' => 0,
      'scrollWheelZoom' => TRUE,
      'touchZoom' => TRUE,
      'trackResize' => TRUE,
      //Zoom should be 0 for correct work fit bounds
      'zoom' => intval($this->getSetting('zoomlevel')),
      'zoomAnimation' => TRUE,
      'zoomControl' => TRUE,
    ];
    // Extract username and styleid from style url.
    $style_url = $this->getSetting('style_url');
    preg_match('/^mapbox:\/\/styles\/(\S*)\/(\S*)$/', $style_url, $matches);
    if (count($matches)) {
      $username = $matches[1];
      $styleid = $matches[2];
      // Build urlTemplate.
      $url_template = "//api.mapbox.com/styles/v1/$username/$styleid/tiles/{z}/{x}/{y}?access_token={$this->getSetting('token')}";
    }

    $map = [
      'label' => '',
      'description' => '',
      'settings' => $settings,
      'layers' => [
        'earth' => [
          'urlTemplate' => $url_template,
          'options' => [
            'attribution' => '',
            'tileSize' => 512,
            'zoomOffset' => -1,
          ],
        ]
      ],
    ];

    $map['settings']['zoom'] = isset($settings['zoom']) ? $settings['zoom'] : NULL;
    $map['settings']['minZoom'] = isset($settings['minZoom']) ? $settings['minZoom'] : NULL;
    $map['settings']['maxZoom'] = isset($settings['zoom']) ? $settings['maxZoom'] : NULL;

    $settings['popup'] = 0;
    $settings['popup'] = 0;
    $settings['icon'] = [
      'icon_url' => '',
      'shadow_url' => '',
      'icon_size' => [
        'x' => '0',
        'y' => '0'
      ],
      'icon_anchor' => [
        'x' => '0',
        'y' => '0'
      ],
      'shadow_anchor' => [
        'x' => '0',
        'y' => '0'
      ],
      'popup_anchor' => [
        'x' => '0',
        'y' => '0'
      ],
    ];

    $icon_url = $settings['icon']['icon_url'];
    //Features render items on the map
    //If needs rendreing items on the map this line should be uncommented
    $features = [];
    //$features = $this->leafletService->leafletProcessGeofield($items[$delta]->value);
    //If only a single feature, set the popup content to the entity title.
    if ($settings['popup'] && count($items) == 1) {
      $features[0]['popup'] = $items->getEntity()->label();
    }
    if (!empty($icon_url)) {
      foreach ($features as $key => $feature) {
        $features[$key]['icon'] = $settings['icon'];
      }
    }

    $element_map = $this->leafletService->leafletRenderMap($map, $features, $this->getSetting('height') . 'px');
    //We need have able to set width.
    $element_map['#theme'] = 'leaflet_map_widget';
    $element_map['#width'] = ($this->getSetting('width')) ? $this->getSetting('width') . 'px' : '';

    $map_id = $element_map['#map_id'];

    $element_map['#attached']['library'][] = 'leaflet_bbox_widget/leaflet-bbox-widget';
    $element_map['#attached']['drupalSettings']['leaflet_widget'][$map_id] = [
      'map_id' => $map_id,
      'default_value' => $bounds_value,
    ];

    return $element_map;
  }

}
