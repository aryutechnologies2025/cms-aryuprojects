(function($, Drupal, once, Sprowt) {


    Drupal.behaviors.AiTagsExposedField = {
        attach(context, settings) {
            $(once('AiTagsExposedField', '.tags-sub-field-hidden')).each(function() {
                let $tagsInput = $(this);
                let $tagsField = $(this).closest('form').find('.tags-sub-field');

                let val = $tagsInput.val();
                let selectVal = [];
                if(val) {
                    let tags = val.split('|');
                    for(let i = 0; i < tags.length; i++) {
                        let tag = tags[i].replace('^', '').replace('$', '');
                        selectVal.push(tag);
                    }
                }
                $tagsField.val(selectVal);
                if($tagsField.hasClass('select2-hidden-accessible')) {
                    $tagsField.trigger('change.select2');
                }

                $tagsField.on('change', function() {
                    let val = Sprowt.selectValue($(this));
                    let str = '';
                    if(val && val.length) {
                        str = '^' + val.join('$|^') + '$';
                    }
                    $tagsInput.val(str);
                });
            });
        }
    };

})(jQuery, Drupal, once, Sprowt);
