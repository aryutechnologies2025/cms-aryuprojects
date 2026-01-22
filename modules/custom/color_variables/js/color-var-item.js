(function($, Drupal){
    $(document).ready(function() {
        let adjustTextColor = function() {
            $('.color-var-bg').each(function () {
                let color = $(this).css('background-color');
                var jColor = $.Color(color);
                let textColor = 'black';
                if(jColor.lightness() <= 0.5) {
                    textColor = 'white';
                }
                if(textColor === 'white' && jColor.alpha() <= 0.4) {
                    textColor = 'black';
                }
                if(textColor) {
                    $(this).css({
                        color: textColor
                    });
                }
                else {
                    $(this).css({
                        color: ''
                    });
                }
            });
        };

        let validateColorValue = function(stringToTest){
            //Alter the following conditions according to your need.
            if (stringToTest === "") { return false; }
            if (stringToTest === "inherit") { return true; }
            if (stringToTest === "transparent") { return true; }
            if (stringToTest === "rgb(0, 0, 0)") { return true; }
            var ret;
            var image = document.createElement('img');
            image.style.color = "rgb(0, 0, 0)";
            image.style.color = stringToTest;
            if (image.style.color !== "rgb(0, 0, 0)") {
                ret = true;
            }
            if(!ret) {
                image.style.color = "rgb(255, 255, 255)";
                image.style.color = stringToTest;
                if (image.style.color !== "rgb(255, 255, 255)") {
                    ret = true;
                }
            }
            image.remove();
            return ret;
        };

        let setColorBackgrounds = function() {
            $('.color-field').each(function() {
                let $colorField = $(this);
                let colorValue = $colorField.val();
                if(!colorValue || !validateColorValue(colorValue)) {
                    $colorField.css({
                        backgroundColor: '',
                    });
                }
                else {
                    $colorField.css({
                        backgroundColor: colorValue,
                    });
                }
            });
            window.setTimeout(function() {
                adjustTextColor();
            },300);
        };

        let setPreviewStyleVariables = function() {
            let vars = {};
            $('.color-field').each(function() {
                let $colorField = $(this);
                let colorValue = $colorField.val();
                let colorVar = $colorField.data('variable');
                vars[colorVar] = colorValue;
            });

            let $style = $('#color-variable-style-block');
            let originalVars = $style.data('original-vars');
            if(typeof originalVars === 'string') {
                originalVars = JSON.parse(originalVars);
            }
            let cssVars = [];
            cssVars.push('#preview {');
            $.each(vars, function(cv, color){
                let val = color;
                if(!color || !validateColorValue(color)) {
                    val = originalVars[cv];
                }
                cssVars.push('--color-'+cv+':'+val+';');
            });
            cssVars.push('}');
            $style.html(cssVars.join("\n"));
            window.setTimeout(function() {
                adjustTextColor();
            },300);
        };

        $('.color-field').keyup(function() {
            setColorBackgrounds();
            setPreviewStyleVariables();
            let val = $(this).val();
            let $picker = $(this).closest('.color-field-form-item-wrap').find('.color-picker');
            if(validateColorValue(val)) {
                $picker.val();
            }
        });

        $('.color-picker').change(function() {
            let color = $(this).val();
            let $colorField = $(this).closest('.color-field-form-item-wrap').find('.color-field');
            $colorField.val(color);
            setColorBackgrounds();
            setPreviewStyleVariables();
        });

        $('.color-field').each(function() {
            let val = $(this).val();
            let $picker = $(this).closest('.color-field-form-item-wrap').find('.color-picker');
            if(validateColorValue(val)) {
                $picker.val(val);
            }
        });

        $('.color-picker-button').click(function(e){
            e.preventDefault();
            $picker = $(this).closest('.color-field-form-item-wrap').find('.color-picker');
            $picker.click();
        });

        $('.clear-field').click(function(e) {
            let color = $(this).data('default-color') || '';
            let $colorField = $(this).closest('.color-field-form-item-wrap').find('.color-field');
            $colorField.val(color);
            $colorField.trigger('keyup');
        });

        setColorBackgrounds();
        setPreviewStyleVariables();

    });
} )(jQuery, Drupal);
