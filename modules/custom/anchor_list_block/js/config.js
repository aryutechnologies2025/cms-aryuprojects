(function($, Drupal){
    Drupal.behaviors.anchor_list_block_config = {
        attach: function (context, settings) {
            this.setSectionSelects();
            this.setSelect2s();
        },
        getSelectValue: function($select) {
            if($select.hasClass('select2-hidden-accessible')) {
                let data = $select.select2('data');
                return data.shift().id;
            }
            else {
                return $select.val();
            }
        },
        setSelect2s: function() {
            $('.anchor-list-block-select').each(function() {
                if(!$(this).hasClass('select2-hidden-accessible')) {
                    $(this).select2();
                }
            });
        },
        hideCustomField($select, $urlFieldWrap) {
            let val = this.getSelectValue($select);
            if(val === 'custom') {
                $urlFieldWrap.show();
            }
            else {
                $urlFieldWrap.find('.link-url').val(val);
                $urlFieldWrap.hide();
            }
        },
        setSectionSelects: function() {
            let t = this;
            var sectionSettings = [];
            if($('#layout-builder').length > 0
                && $('#layout-builder').data('section-settings')
            ) {
                sectionSettings = $('#layout-builder').data('section-settings');
            }

            var opts = [];
            $.each(sectionSettings, function(i, section) {
                let opt = {
                    text: section.label,
                    value: '#' + (section.layout_builder_id || '')
                }
                if(opt.value !== '#') {
                    opts.push(opt);
                }
            });
            opts = $.merge([
                {
                    text: 'Custom',
                    value: 'custom'
                }
            ], opts);

            $('.anchor-list-block-link-value').each(function() {
                let $wrap = $(this);
                let $select = $wrap.find('.link-url-select');
                let $urlField = $wrap.find('.link-url');
                let $urlFieldWrap = $urlField.closest('.form-item');
                let url = $urlField.val();
                if($select.hasClass('select2-hidden-accessible')) {
                    $select.select2('destroy');
                }
                $select.off('change');
                $select.html('');
                let urlFound = false;
                $.each(opts, function(optI, opt) {
                    let $opt = $('<option></option>')
                        .attr('value', opt.value)
                        .text(opt.text);
                    if(opt.value === url) {
                        $opt.attr('selected', 'selected');
                        if(url !== 'custom') {
                            urlFound = true;
                        }
                    }
                    $select.append($opt);
                });
                $urlFieldWrap.hide();
                if(!urlFound) {
                    $select.val('custom');
                    $urlFieldWrap.show();
                }
                $select.select2();
                $select.on('change', function() {
                    t.hideCustomField($select, $urlFieldWrap);
                });
            });
        }
    }
})(jQuery, Drupal)
