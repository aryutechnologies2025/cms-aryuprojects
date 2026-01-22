(function($,Drupal, once){
    Drupal.behaviors.sprowtAdminOverrideContentAdmin = {
        attach: function(context, settings) {
            $(once('sprowtAdminOverrideContentAdminClear', '[data-drupal-selector="edit-reset-content"]', context)).each(function() {
                $(this).on('click', function(e) {
                    e.preventDefault();
                    let $form = $(this).closest('form');
                    $form.find('input[type="text"]').val('');
                    $form.find('select').each(function() {
                        let $select = $(this);
                        if($select.is('[data-drupal-selector="edit-items-per-page"]')) {
                            return;
                        }
                        if($select.find('option[value="All"]').length > 0) {
                            $select.val('All');
                        }
                        else {
                            $select.val('');
                        }
                    });
                    $form.submit();
                });
            });
        }
    };
})(jQuery, Drupal, once);
