(function($, Drupal, once) {
    Drupal.behaviors.layoutBuilderBrowser = {
        attach: function (context, settings) {
            let t = this;
            $(once('layoutBuilderBrowser', '.js-layout-builder-category', context)).each(function() {
                $(this).find('.layout-builder-browser-block-item').each(function() {
                    let $item = $(this);
                    let $link = $item.find('.js-layout-builder-block-link');
                    let $tooltip = $(this).find('.tooltip');
                    if(window.tippy && $tooltip.length) {
                        window.tippy($link[0], {
                            content: $tooltip.html(),
                            allowHTML: true,
                        });
                    }
                });
            });
        }
    };
})(jQuery, Drupal, once);
