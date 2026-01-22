(function($, Drupal, once){

    Drupal.behaviors.layoutBuilderHidePreview = {
        attach: function (context, settings) {
            once('layout-builder-hide-preview', '[data-hidden-section]', context).forEach(function (element) {
                let $section = $(element);
                let $sectionWrap = $section.closest('.layout-builder__section');
                let $configureLink = $sectionWrap.find('.layout-builder__link--configure');
                let configureLabel = $configureLink.text();
                configureLabel = configureLabel + ' (hidden)';
                $configureLink.text(configureLabel);
                $configureLink.attr('data-hidden-section-configure-link', 'true');
            });
        }
    };

})(jQuery, Drupal, once);
