(function($, Drupal, drupalSettings){

    Drupal.behaviors.sprowt_address_autocomplete_element_init = {
        autocompletes: [],
        placeChanged(autocomplete, $input) {
            // https://developers.google.com/maps/documentation/javascript/reference/places-service#PlaceResult
            let placeResult = autocomplete.getPlace();
            let t = this;
            t.setPlaceDetails(placeResult, $input);
        },
        setPlaceDetails(placeResult, $input) {
            let details;
            if(placeResult) {
                details = this.placeDetails(placeResult);
            }
            else {
                details = {
                    formattedAddress: $input.val(),
                    name: $input.val()
                };
            }
            $input.val(details.formattedAddress);
            let $hidden = $input.closest('.sprowt-address-autocomplete').find('.hidden-value');
            $hidden.val(JSON.stringify(details));
            let event = $.Event('sprowt_place_change');
            event.placeResult = placeResult;
            event.details = details;
            $input.trigger(event);
            let detailKeys = [
                'placeName',
                'placeId',
                'formattedAddress',
                'lat',
                'lng',
                'address',
                'address_2',
                'city',
                'state_province',
                'state_province_code',
                'country',
                'country_code',
                'postal_code'
            ];
            detailKeys.forEach(function (key) {
                //change val and trigger change for conditional logic
                let $keyField = $input.closest('.sprowt-address-autocomplete').find('[data-conditional-key="' + key + '"]');
                let detailVal = details[key] || '';
                if(key === 'placeName') {
                    detailVal = details.name || '';
                }
                if ($keyField.length > 0) {
                    $keyField.val(detailVal);
                    $keyField.trigger('change');
                }
            });

            let overrides = [
                'address',
                'address_2',
                'city',
                'state_province',
                'country',
                'postal_code'
            ];

            overrides.forEach(function (key) {
                let $overrideField = $input.closest('.sprowt-address-autocomplete').find('[data-override-field="' + key + '"]');
                let detailVal = details[key] || '';
                if ($overrideField.length > 0) {
                    $overrideField.val(detailVal);
                    $overrideField.trigger('change');
                }
            });
        },
        placeDetails(placeResult) {
            let components = placeResult.address_components || [];
            let geo = placeResult.geometry || false;
            let details = {
                name: placeResult.name
            };
            if(placeResult.place_id) {
                details.placeId = placeResult.place_id;
            }
            if(placeResult.formatted_address) {
                details.formattedAddress = placeResult.formatted_address;
            }
            else {
                details.formattedAddress = placeResult.name;
            }
            if(geo) {
                details.lat = geo.location.lat();
                details.lng = geo.location.lng();
            }
            $.each(components, function(cdx, component) {
                let types = component.types;
                $.each(types, function(tdx, type) {
                    switch(type) {
                        case 'street_number':
                            if(!details.address) {
                                details.address = [];
                            }
                            details.address.push(component.long_name);
                            break;
                        case 'route':
                            if(!details.address) {
                                details.address = [];
                            }
                            details.address.push(component.long_name);
                            break;
                        case 'subpremise':
                            details.address_2 = component.long_name;
                            break;
                        case 'locality':
                            details.city = component.long_name;
                            break;
                        case 'administrative_area_level_1':
                            details.state_province = component.long_name;
                            details.state_province_code = component.short_name;
                            break;
                        case 'country':
                            details.country = component.long_name;
                            details.country_code = component.short_name;
                            break;
                        case 'postal_code':
                            details.postal_code = component.long_name;
                            break;
                    }
                });
            });

            if(details.address && Array.isArray(details.address)) {
                details.address = details.address.join(' ');
            }
            return details;
        },
        async attach(context, settings) {
            if(!this.placesLibrary) {
                this.placesLibrary = await google.maps.importLibrary("places");
            }
            let t = this;

            $(once('sprowt_address_autocomplete_element_init', '.sprowt-address-autocomplete', context)).each(function() {
                let $wrap = $(this);
                let $input = $wrap.find('.autocomplete-textfield');
                if($input.length > 0) {
                    // https://developers.google.com/maps/documentation/javascript/reference/places-widget#Autocomplete
                    let autocomplete = new t.placesLibrary.Autocomplete($input[0], {
                        types: ['address'],
                        fields: ['name', 'place_id', 'address_components', 'formatted_address', 'geometry']
                    });
                    autocomplete.addListener('place_changed', function () {
                        t.placeChanged(autocomplete, $input);
                    });
                    //triggered on browser autofill
                    $input.on('onautocomplete', function(e){
                        console.log('autocomplete');
                        window.setTimeout(function() {
                            t.placeChanged(autocomplete, $input);
                        }, 300);
                    });
                    t.autocompletes.push(autocomplete);
                    $input.on('keyup', function (e) {
                        window.setTimeout(function() {
                            //reset hidden values if emptied
                            if($input.val() === '') {
                                t.setPlaceDetails(null, $input);
                            }
                        }, 300);
                    });
                }
            });
        },

    };

})(jQuery, Drupal, drupalSettings);
