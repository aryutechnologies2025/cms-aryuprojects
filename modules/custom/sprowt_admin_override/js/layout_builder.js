(function($, Drupal, once){
    const kebabCase = string => string
        .replace(/([a-z])([A-Z])/g, "$1-$2")
        .replace(/[\s_]+/g, '-')
        .toLowerCase();


    Drupal.behaviors.LayoutBuilderSectionIdAutomation = {
        attach: function (context, settings) {
            let t = this;
            $(once('LayoutBuilderSectionIdAutomation', 'form[data-drupal-selector="layout-builder-configure-section"]')).each(function(){
                let $form = $(this);
                let $label = $form.find('input[data-drupal-selector="edit-layout-settings-label"]');
                let $idField = $form.find('input[data-drupal-selector="edit-layout-settings-layout-builder-id"]');
                let labelVal = $label.val();
                let kebabLabel = kebabCase(labelVal);
                if(!labelVal || $idField.val() === kebabLabel) {
                    $form.addClass('auto-section-id');
                }
                t.disableIdField($idField);
                let $editLink = $('<a class="edit-link" href="#">edit</a>');
                $editLink.on('click', function(e) {
                    e.preventDefault();
                    $form.removeClass('auto-section-id');
                    t.enableIdField($idField);
                });
                $idField.closest('.form-item').find('label').append($editLink);
                $label.on('input', function() {
                    t.updateIdField($idField, $label);
                });
                if(labelVal && $idField.val() !== kebabLabel) {
                    t.enableIdField($idField);
                }
            });
        },
        updateIdField($idField, $label) {
            let $form = $idField.closest('form');
            if($form.hasClass('auto-section-id')){
                let val = kebabCase($label.val());
                if(!$label.val()) {
                    val = '';
                }
                $idField.val(val);
            }
        },
        disableIdField($idField) {
            $idField.prop('readonly', true);
            $idField.addClass('disabled');
            let $wrap = $idField.closest('.form-item');
            $wrap.addClass('disabled');
        },
        enableIdField($idField) {
            $idField.prop('readonly', false);
            $idField.removeClass('disabled');
            let $wrap = $idField.closest('.form-item');
            $wrap.removeClass('disabled');
        }
    };

})(jQuery, Drupal, once);
