(function($, Drupal, once, Sprowt){

    Drupal.behaviors.sprowt_ai_filter_clear = {
        attach: function(context, settings) {
            $(once('sprowt_ai_filter_clear', 'input[name="filterClear"]')).each(function (){
                let $button = $(this);
                let $fieldSet = $button.closest('fieldset');
                let $applyButton = $fieldSet.find('input[name="filterApply"]');
                $button.on('mousedown click', function (e) {
                    e.preventDefault();
                    $fieldSet.find('input[type="checkbox"]').prop('checked', false);
                    $fieldSet.find('input[type="radio"]').prop('checked', false);
                    $fieldSet.find('input[type="text"]').val('');
                    $fieldSet.find('select').val('');
                    if($fieldSet.find('select').hasClass("select2-hidden-accessible")){
                        $fieldSet.find('select').trigger('change.select2');
                    }
                    $applyButton.trigger('mousedown');
                });
            });
        }
    };

})(jQuery, Drupal, once, Sprowt);
