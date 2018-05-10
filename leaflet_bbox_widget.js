(function ($) {

  Drupal.behaviors.mapbox_widget = {
    attach: function (context, settings) {

      $.each(settings.leaflet_widget, function (m, data) {
        var map_id = data.map_id;
        var map_object = $('#' + map_id).data('leaflet');
        var $map_bounds_element = $('[data-widget-map-id=' + map_id + ']');

        //Fit bounds if there is default value
        if (data.default_value.top && data.default_value.left && data.default_value.bottom && data.default_value.right) {
          var default_bound = L.latLngBounds(
            L.latLng(data.default_value.top, data.default_value.left),
            L.latLng(data.default_value.bottom, data.default_value.right)
          );

          map_object.lMap.fitBounds(default_bound);
        }

        //Add listener for changing bounds value
        map_object.lMap.on('moveend', function(e) {
          var bounds = map_object.lMap.getBounds();
          var coords = {};

          coords['geofield-top'] = bounds._northEast.lat;
          coords['geofield-right'] = bounds._northEast.lng;
          coords['geofield-bottom'] = bounds._southWest.lat;
          coords['geofield-left'] = bounds._southWest.lng;

          $.each(coords, function(index, value) {
            $map_bounds_element.find('.' + index).val(value);
          });

        });

      });

    }
  };

})(jQuery);
