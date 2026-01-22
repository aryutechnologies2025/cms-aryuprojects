(function($, Drupal, once, Sprowt) {

    Drupal.behaviors.promptLibraryImportForm = {
        attach: function (context, settings) {
            $(once('promptLibraryImportForm', '#sprowt-ai-prompt-library-import-from-library')).each(function() {
                let $form = $(this);
                let $titleFilter = $form.find('[data-drupal-selector="edit-prompt-name"]');
                let $tagFilter = $form.find('[data-drupal-selector="edit-tags"]');
                let $rows = $form.find('tr[data-row-values]');

                let filter = function () {
                    let title = $titleFilter.val();
                    let tags = Sprowt.selectValue($tagFilter);
                    $rows.removeClass('hidden');


                    $rows.each(function() {
                        let titleWords = [];
                        if(title) {
                            titleWords = title.split(' ');
                        }
                        let $row = $(this);
                        let vals = $row.data('row-values') || {};
                        if(typeof vals === 'string') {
                            vals = JSON.parse(vals);
                        }
                        let rowTitle = vals.title || '';
                        let hasTitle = true;
                        for(let i = 0; i < titleWords.length; i++) {
                            if (rowTitle.toLowerCase().indexOf(titleWords[i].toLowerCase()) === -1) {
                                hasTitle = false;
                                break;
                            }
                        }
                        let hasTag = true;
                        if(tags && tags.length > 0) {
                            hasTag = false;
                            let rowTags = $row.attr('data-tags');
                            if(typeof rowTags === 'string') {
                                rowTags = JSON.parse(rowTags);
                            }
                            for(let i = 0; i < rowTags.length; i++) {
                                rowTags[i] = rowTags[i].toLowerCase();
                            }
                            for(let i = 0; i < tags.length; i++) {
                                if (rowTags.indexOf(tags[i].toLowerCase()) >= 0) {
                                    hasTag = true;
                                    break;
                                }
                            }
                        }
                        let match = hasTitle && hasTag;

                        if(!match) {
                            $row.addClass('hidden');
                        }
                    });

                };

                $form.find('.filter-apply').click(function(e) {
                    e.preventDefault();
                    filter();
                });
            });
        }
    };

})(jQuery, Drupal, once, Sprowt);
