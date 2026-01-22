(function ($, Drupal, once) {

    Drupal.behaviors.solutionEntityFormDestinations = {
        searchResult: function(state) {
            if (!state.id) {
                return state.text;
            }
            let $result = $('<div class="select2-search-result"></div>');
            $result.append('<div><strong>Subsite: </strong> '+state.subsite+'</div>');
            $result.append('<div><strong>Content type: </strong>'+state.bundle_label+'</div>');
            $result.append('<div><strong>label: </strong>'+state.label+' ['+state.id+']</div>');
            return $result;
        },
        selection: function(state) {
            if(!state.id) {
                return state.text;
            }
            return state.subsite + ': ' + state.bundle_label + ' -> ' + state.label + ' ['+state.id+']';
        },
        attach: function (context, settings) {
            let t = this;
            $(once('solutionEntityFormDestinations', 'select.destination-element', context)).each(function () {
               let $select = $(this);
               let chosen = $select.val();
               if($select.hasClass('select2-hidden-accessible')) {
                   $select.select2('destroy');
               }
               let select2Opts = Drupal.behaviors.select2.getElementOptions($select);
                $select.html("");
               let optionsDefinition = $select.data('options-definition') || [];
               if(typeof optionsDefinition === 'string') {
                   optionsDefinition = JSON.parse(optionsDefinition);
               }
               let dataMap = {};
               $.each(optionsDefinition, function (i, item) {
                   if(!dataMap[item.subsite_uuid]) {
                       dataMap[item.subsite_uuid] = {
                           text: item.subsite,
                           children: []
                       };
                   }
                   item.text =  [item.subsite, item.bundle_label, item.label].join(' ');
                   if(chosen && item.id === chosen) {
                       item.selected = true;
                   }
                   dataMap[item.subsite_uuid].children.push(item);
               });
               let data = Object.values(dataMap);
                select2Opts.data = data;
                select2Opts.templateSelection = t.selection;
                select2Opts.templateResult = t.searchResult;
                select2Opts.allowClear = true;
                select2Opts.placeholder = {
                    id: '', // the value of the option
                    text: '-- Select --',
                };
                $select.select2(select2Opts);
                if(!chosen) {
                    $select.val('');
                    $select.trigger('change');
                }
            });
        }
    };

})(jQuery, Drupal, once);
