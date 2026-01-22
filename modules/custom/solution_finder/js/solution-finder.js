(function($, Drupal){
    Drupal.behaviors.solutionFinder = {
        attach: function(context, settings) {
            var $forms = $('.solution-finder-concerns');
            $forms.each(function() {
                var $form = $(this);
                var $optionsButton = $form.find('.more-options-button');
                var changeOptionButtonText = function() {
                    if($optionsButton.hasClass('all-shown')) {
                        $optionsButton.val($optionsButton.data('hide-options-text'));
                    }
                    else {
                        $optionsButton.val($optionsButton.data('show-options-text'));
                    }
                };
                $(once('solution-finder-changeOptionButtonText', '.more-options-button', this)).each(function() {
                    $(this).on('click', function (e){
                        e.preventDefault();
                        $form.toggleClass('more-options');
                        $(this).toggleClass('all-shown');
                        changeOptionButtonText();
                    });
                });
                var $checkboxes = $form.find('.concern-check');
                var toggleCheckboxClass = function() {
                    $checkboxes.each(function() {
                        var $wrap = $(this).closest('.concern-list-item');
                        $wrap.removeClass('checked');
                        if($(this).prop('checked')) {
                            $wrap.addClass('checked');
                        }
                    });
                };
                $(once('solution-finder-toggleCheckboxInit', this)).each(function() {
                    toggleCheckboxClass();
                    changeOptionButtonText();
                });
                $(once('solution-finder-checkboxChange', '.concern-check', this)).each(function() {
                    $(this).on('change', toggleCheckboxClass);
                });
                $(once('solution-finder-item-wrap', '.solution-finder-item-wrap', this)).each(function() {
                    $(this).on('click', function() {
                        let id = $(this).data('for');
                        let checked = $(this).find('#' + id).prop('checked');
                        $(this).find('#' + id).prop('checked', !checked);
                        $(this).find('#' + id).trigger('change');
                    });
                });
            });
        }
    }
})(jQuery, Drupal)
