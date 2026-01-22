(function($,Drupal) {

    Drupal.behaviors.sprowtSubsiteCondition = {
        changeSubsiteValueFieldTitle($select) {
            let $negate = $select.closest('.subsite-field-fieldset').find('.subsite-field-negate');
            let negateValue = $negate.prop('checked');
            let $title = $select.closest('.form-item').find('.subsite-value-field-title');
            let $description = $select.closest('.form-item').find('.subsite-value-field-description');
            if(negateValue) {
                $title.html($select.data('not-contain-title'));
                $description.html($select.data('not-contain-description'));
            }
            else {
                $title.html($select.data('contain-title'));
                $description.html($select.data('contain-description'));
            }
        },
        attach: function(context, settings) {
            let t= this;
            $(once('sprowtSubsiteCondition-subsite-value-field', '.subsite-value-field', context)).each(function() {
                let $select = $(this);
                if(!$select.hasClass("select2-hidden-accessible")) {
                    let select2Opts = Drupal.behaviors.select2.getElementOptions($select);
                    $select.select2(select2Opts);
                }
                t.changeSubsiteValueFieldTitle($select);
            });
            $(once('sprowtSubsiteCondition-subsite-negate', '.subsite-field-negate', context)).each(function() {
                let $select = $(this).closest('.subsite-field-fieldset').find('.subsite-value-field');
                $(this).change(function() {
                    t.changeSubsiteValueFieldTitle($select);
                });
            });
            $(once('sprowtSubsiteCondition-summary', '#summaryHidden', context)).each(function () {
                let $details = $(this).closest('details');
                let summaryText = $(this).val();
                if($details.length > 0) {
                    $details.drupalSetSummary(function(c) {
                        return summaryText;
                    });
                }
            });
        }
    };

})(jQuery, Drupal);
