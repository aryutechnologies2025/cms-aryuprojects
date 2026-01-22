(function($, Drupal, Sprowt) {
    function resetSelect2($select, disabled = false) {
        let value = Sprowt.selectValue($select);
        let takenValues = [];
        let $elements = $('.component-field');
        $elements.each(function() {
            if($(this).attr('id') !== $select.attr('id')) {
                let val = Sprowt.selectValue($(this));
                if(val) {
                    takenValues.push(val);
                }
            }
        });
        if($select.hasClass('select2-hidden-accessible')) {
            $select.select2('destroy');
        }
        if(!$select.hasClass('no-option-disable')) {
            $select.find('option').each(function () {
                $(this).removeAttr('disabled');
                let val = $(this).attr('value');
                if (takenValues.indexOf(val) >= 0) {
                    $(this).attr('disabled', 'disabled');
                }
            });
        }
        let opts = Drupal.behaviors.select2.getElementOptions($select);
        opts.disabled = disabled;
        $select.select2(opts);
    }

    function disable($input) {
        let $wrap = $input.closest('.form-item');
        let $label = $wrap.find('.form-item__label');
        $wrap.addClass('form-item--disabled');
        $label.addClass('is-disabled');
        $input.attr('disabled', 'disabled');
        if($input.hasClass('form-select') && $input.hasClass('select2-hidden-accessible')) {
            resetSelect2($input, true);
        }
    }

    function unDisable($input) {
        let $wrap = $input.closest('.form-item');
        let $label = $wrap.find('.form-item__label');
        $wrap.removeClass('form-item--disabled');
        $label.removeClass('is-disabled');
        $input.removeAttr('disabled');
        if($input.hasClass('form-select') && $input.hasClass('select2-hidden-accessible')) {
            resetSelect2($input, false);
        }
    }

    function enableDisable() {
        if($('.lawnbot-enable-check').length <= 0) {
            return;
        }
        let enabled = $('.lawnbot-enable-check').prop('checked');
        console.log({
            check: $('.lawnbot-enable-check'),
            enabled: enabled
        });
        let $elements = $('.component-field');
        $elements.each(function() {
            if(enabled) {
                unDisable($(this));
                if($(this).hasClass('map-required')) {
                    $(this).closest('.form-item').find('.form-item__label').addClass('form-required');
                    $(this).attr('required', 'required');
                }
            }
            else {
                disable($(this));
                if($(this).hasClass('map-required')) {
                    $(this).closest('.form-item').find('.form-item__label').removeClass('form-required');
                    $(this).removeAttr('required');
                }
            }
        });
    }

    function disableOptions() {
        if($('.lawnbot-enable-check').length <= 0) {
            return;
        }
        let $elements = $('.component-field');
        $elements.each(function() {
            let disabled = !!$(this).attr('disabled');
            resetSelect2($(this), disabled);
        });
    }

    function addExclusion(obj) {
        let $tmp = Sprowt.getTemplate('excludeTemplate');
        let $select = $tmp.find('.exclude-field');
        $tmp.find('.exclude-field').val(obj.field || null);
        $tmp.find('.exclude-value').val(obj.value || null);
        $('#excludeWrap').append($tmp);
        if(!$select.hasClass("select2-hidden-accessible") && Drupal.behaviors.select2) {
            Drupal.behaviors.select2.createSelect2($select);
        }
    }

    function writeExcludeValue() {
        let $check = $('#excludeCheck');
        if($check.prop('checked')) {
            let array = [];
            $('#excludeWrap').find('.exclusion').each(function () {
                let obj = {
                    field: $(this).find('.exclude-field').val(),
                    value: $(this).find('.exclude-value').val()
                };
                if (obj.field && obj.value) {
                    array.push(obj);
                }
            });
            $('#exclusions').val(JSON.stringify(array));
        }
        else {
            $('#exclusions').val('[]');
        }
    }

    function resetExclusions() {
        if($('.lawnbot-enable-check').length <= 0) {
            return;
        }
        $('#excludeWrap').html();
        let current = $('#exclusions').val();
        if(typeof current === 'string') {
            current = JSON.parse(current) || [];
        }

        $.each(current, function(idx, obj) {
            addExclusion(obj);
        });
    }

    function showExclusions() {
        if($('.lawnbot-enable-check').length <= 0) {
            return;
        }
        let $check = $('#excludeCheck');
        if($check.prop('checked')) {
            $('#excludeFieldset').show();
        }
        else {
            $('#excludeFieldset').hide();
        }
    }

    $(document).on('click', '.exclude-remove', function(e) {
        e.preventDefault();
        let $exclusion = $(this).closest('.exclusion');
        $exclusion.remove();
        writeExcludeValue();
    });

    $(document).on('click', '#addExclusion', function(e) {
        e.preventDefault();
        addExclusion({});
        writeExcludeValue();
    });

    $(document).on('input', '.exclude-value', function () {
        writeExcludeValue();
    });

    $(document).on('change', '.exclude-field', function() {
        writeExcludeValue();
    });

    $(document).on('change', '#excludeCheck', function() {
        showExclusions();
        writeExcludeValue();
    });

    $(document).on('change', '.lawnbot-enable-check', enableDisable);
    $(document).on('select2:select select2:selecting select2:unselecting', '.component-field', disableOptions);
    $(document).on('click', '.enable-url-button', function(e) {
        if($('.lawnbot-enable-check').length <= 0) {
            return;
        }
        e.preventDefault();
        let $button = $(this);
        let $buttonWrap = $button.closest('.form-item__suffix');
        let $item = $button.closest('.form-item');
        let $input = $item.find('input');
        $input.removeAttr('disabled');
        $buttonWrap.remove();
    });

    Drupal.behaviors.servicebotHandler = {
        attach: function (context, settings) {
            enableDisable();
            resetExclusions();
            if($('#excludeWrap').find('exclusion').length > 0) {
                $('#excludeCheck').prop('checked', true);
            }
            showExclusions();
        }
    };
})(jQuery, Drupal, Sprowt);
