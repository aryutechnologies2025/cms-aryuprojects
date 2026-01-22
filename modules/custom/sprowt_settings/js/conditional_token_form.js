(function($, Drupal, once){

    Drupal.behaviors.conditionalTokenForm = {
        attach: function(context, settings) {
            let t = this;
            $(once('conditionalTokenForm', '.sprowt-settings-conditional-token')).each(function() {
                let $form = $(this);
                let $key = $form.find('input[name="key"]');
                $key.on('keyup', function(e) {
                    let $input = $(this);
                    let value = $(this).val();
                    if(/[^a-z0-9_]/g.test(value)) {
                        $input.val(
                            value.toLowerCase()
                                .replace(/[^a-z0-9_]+/g, '_')
                        );
                        window.setTimeout(function() {
                            let value = $input.val();
                            $input.val(
                                value.toLowerCase()
                                    .replace(/[^a-z0-9_]+/g, '_')
                                    .replace(/_[_]+/, '_')
                                    .replace(/^[_]+/, '')
                                    .replace(/[_]+$/, '')
                            );
                        }, 500);
                    }
                });
            });
        }
    };

})(jQuery, Drupal, once);
