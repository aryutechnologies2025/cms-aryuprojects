(function($, Drupal, once) {
    let debounce = Drupal.debounce,
        announce = Drupal.announce,
        formatPlural = Drupal.formatPlural;

    Drupal.behaviors.sprowt_admin_override_layout_builder_browser_filter = {
        attach: function (context, settings) {
            let t = this;
            $(once('sprowt_admin_override_layout_builder_browser_filter', '.js-sprowt-layout-builder-filter', context)).each(function() {
                $(this).on('input', t.onInput.bind(t));
                $(this).closest('form').submit(function(e) {
                    e.preventDefault();
                });
            });
        },
        layoutBuilderBlocksFiltered: false,
        onInput: function (e) {
            let t = this;
            if (e.which === 13) {
                e.preventDefault();
                return;
            }
            let filter = debounce(t.filterBlockList.bind(t), 200);
            filter(e);
        },
        filterBlockList: function (e) {
            let t = this;
            let query = $(e.target).val().toLowerCase();
            console.log({query});
            // Custom selector, remove context to ensure filter works in modal.
            let $categories = $('.js-layout-builder-categories');
            let tabLinks = [];
            $categories.find('.js-layout-builder-category').each(function() {
                let $cat = $(this);
                let $tabLink = $('.vertical-tabs__menu-link[href="#'+$cat.attr('id')+'"]');
                tabLinks.push($tabLink);
            });
            let $filterLinks = $categories.find('.js-layout-builder-block-link');
            $filterLinks.addClass('visually-shown');
            let toggleBlockEntry = function toggleBlockEntry(index, link) {
                var $link = $(link);
                var textMatch = $link.text().toLowerCase().indexOf(query) !== -1;
                $link.toggle(textMatch);
                if(!textMatch) {
                    $link.removeClass('visually-shown');
                }
            };

            $.each(tabLinks, function(index, $tabLink) {
                $tabLink.find('.vertical-tabs__menu-link-summary').text('');
            });

            if (query.length >= 2) {
                $filterLinks.each(toggleBlockEntry);
                announce(formatPlural($categories.find('.js-layout-builder-block-link:visible').length, '1 block is available in the modified list.', '@count blocks are available in the modified list.'));

                $categories.find('.js-layout-builder-category:not(:has(.js-layout-builder-block-link.visually-shown))').each(function() {
                    let $cat = $(this);
                    let $tabLink = $('.vertical-tabs__menu-link[href="#'+$cat.attr('id')+'"]');
                    $tabLink.find('.vertical-tabs__menu-link-summary').text('(empty)');
                });

                t.layoutBuilderBlocksFiltered = true;
            } else if (t.layoutBuilderBlocksFiltered) {
                t.layoutBuilderBlocksFiltered = false;
                $filterLinks.show();
                $filterLinks.addClass('visually-shown');
                $.each(tabLinks, function(index, $tabLink) {
                    $tabLink.find('.vertical-tabs__menu-link-summary').text('');
                });
                announce(Drupal.t('All available blocks are listed.'));
            }
        }
    };
})(jQuery, Drupal, once);
