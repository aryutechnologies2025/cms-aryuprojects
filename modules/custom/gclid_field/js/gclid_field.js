(function($, Drupal, once){
    Drupal.behaviors.gclidField = {
        attach: function (context, settings) {
            let t = this;
            $(once('gclidField', 'input[data-gclid]', context)).each(function() {
                let $gclidField = $(this);
                let gclid = window.GCLID;
                if (gclid) {
                    $gclidField.val(gclid);
                }
            });
        }
    };
})(jQuery, Drupal, once);
