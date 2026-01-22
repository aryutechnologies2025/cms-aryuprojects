(function($, Drupal, once){

    function isElementInViewport (el) {

        // Special bonus for those using jQuery
        if (typeof jQuery === "function" && el instanceof jQuery) {
            el = el[0];
        }

        var rect = el.getBoundingClientRect();

        return (
            rect.top >= 0 &&
            rect.left >= 0 &&
            rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) && /* or $(window).height() */
            rect.right <= (window.innerWidth || document.documentElement.clientWidth) /* or $(window).width() */
        );
    }

    Drupal.behaviors.sprowtMetatagDashboard = {
        attach(context, settings) {
            $(once('sprowtMetatagDashboard', '[data-drupal-selector="sprowt-admin-override-metatag-dashboard"]', context)).each(function () {
                let $form = $(this);
                $form.on('click', '.clear-button', function(e) {
                    e.preventDefault();
                    $form.find('.filter-field').each(function() {
                        let $this = $(this);
                        if($this.is('select')) {
                            $this.val('');
                            if($this.is('[data-drupal-selector="edit-filterbystatus"]')) {
                                $this.val('all');
                            }
                            if($this.hasClass('select2-hidden-accessible')) {
                                $this.trigger('change');
                            }
                        }
                        else {
                            $this.val('');
                        }
                    });
                    $form.find('[data-drupal-selector="edit-filtersubmit"]').trigger('mousedown');
                });
                $form.on('click', '.back-to-top', function (e) {
                    e.preventDefault();
                    window.scrollTo({
                        top: 0,
                        left: 0,
                        behavior: 'smooth'
                    });
                });
                $form.on('click', '.bundle-title-link', function (e) {
                    e.preventDefault();
                    $($(this).attr('href'))[0].scrollIntoView({behavior: 'smooth'});
                });
                let backToTopScrollEvent = function() {
                    let filterIsInView = isElementInViewport($form.find('[data-drupal-selector="edit-filters"]')[0]);
                    if(filterIsInView) {
                        $form.find('.back-to-top').removeClass('show');
                    }
                    else {
                        $form.find('.back-to-top').addClass('show');
                    }
                };
                backToTopScrollEvent();
                $(window).on('scroll', backToTopScrollEvent);

                let keyUpTrigger = function ($input) {
                    let originalValue = $input.data('originalValue');
                    if ($input.val() === originalValue) {
                        $form.find('[data-drupal-selector="edit-filtersubmit"]').trigger('mousedown');
                    }
                    else {
                        $input.data('originalValue', $input.val());
                        window.setTimeout(function () {
                            keyUpTrigger($input);
                        }, 300);
                    }
                };

                // $form.find('.filter-field').each(function () {
                //     let $this = $(this);
                //     if ($this.is('select')) {
                //         $form.on('change', '[data-drupal-selector="' + $this.attr('data-drupal-selector') + '"]', function () {
                //             $form.find('[data-drupal-selector="edit-filtersubmit"]').trigger('mousedown');
                //         });
                //     }
                //     if($this.is('input[type="text"]')) {
                //         $form.on('keyup', '[data-drupal-selector="' + $this.attr('data-drupal-selector') + '"]', function () {
                //             keyUpTrigger($this);
                //         });
                //     }
                // });

            });
        }
    };

})(jQuery, Drupal, once)
