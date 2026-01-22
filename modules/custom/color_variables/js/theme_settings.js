(function($){

    var ColorVariables = function() {
        this.$valueInput = $('#color-variables');
        this.colorVariables = this.$valueInput.val();
        this.inheritedVariables = {};
        if(typeof this.colorVariables === 'string') {
            this.colorVariables = JSON.parse(this.colorVariables);
        }
        this.$inheritedInput = $('#inherited-color-values');

        var t = this;
        $.each(this.colorVariables, function(name, value) {
            t.addColorInput(name, value);
        });

        this.buildInheritedValues();
        this.setSassVariables();
    }

    ColorVariables.prototype.buildInheritedValues = function() {
        var t = this;
        if(this.$inheritedInput.length > 0) {
            var inheritedColors = this.$inheritedInput.val();
            if(typeof inheritedColors === 'string') {
                inheritedColors = JSON.parse(inheritedColors);
            }
            var currentColors = Object.keys(this.colorVariables);
            var buildColors = {};
            $.each(inheritedColors, function(name, color) {
                if(currentColors.indexOf(name) < 0) {
                    buildColors[name] = color;
                }
            });

            this.inheritedVariables = buildColors;

            if(JSON.stringify(buildColors) === '{}') {
                $('#inherited-color-variables').html('<div>No inherited colors</div>');
            }
            else {
                $('#inherited-color-variables').html('');
                $.each(buildColors, function (name, color) {
                    t.addInheritedColor(name, color);
                });
            }
            this.setSassVariables();
        }
    }

    ColorVariables.prototype.getInheritedTemplate = function() {
        var $tmp = $($('#inherited-color-variable-template').html());
        $tmp.find('*').filter(function () {
            return $(this).attr('id') || $(this).attr('data-drupal-selector') || $(this).attr('name');
        }).removeAttr('id').removeAttr('data-drupal-selector').removeAttr('name');
        return $tmp.removeAttr('id').removeAttr('data-drupal-selector').removeAttr('name');
    }

    ColorVariables.prototype.addInheritedColor = function(name, colorValue) {
        var $tmp = this.getInheritedTemplate();
        $tmp.data('variable-name', name);
        $tmp.data('variable-value', colorValue);
        $tmp.find('label').text(name);
        $tmp.find('.inherited-color-value').val(colorValue);
        this.setBgColor($tmp.find('.inherited-color-value'));
        $('#inherited-color-variables').append($tmp);
    }

    ColorVariables.prototype.getTemplate = function() {
        var $tmp = $($('#color-value-template').html());
        $tmp.find('*').filter(function () {
            return $(this).attr('id') || $(this).attr('data-drupal-selector') || $(this).attr('name');
        }).removeAttr('id').removeAttr('data-drupal-selector').removeAttr('name');
        $tmp.find('.variable-name').attr('required', 'required').closest('.form-item').find('label').addClass('form-required');
        $tmp.find('.variable-value').attr('required', 'required').closest('.form-item').find('label').addClass('form-required');
        return $tmp.removeAttr('id').removeAttr('data-drupal-selector').removeAttr('name');
    }

    ColorVariables.prototype.addColorInput = function(name, value) {
        var $tmp = this.getTemplate();
        $tmp.find('.variable-name').val(name);
        $tmp.find('.variable-value').val(value);
        this.setBgColor($tmp.find('.variable-value'));
        if(value) {
            var jColor = $.Color($tmp.find('.variable-value')[0].style.backgroundColor);
            $tmp.find('.color-picker').val(jColor.toHexString());
        }
        $('#color-variable-values').append($tmp);
        this.buildInheritedValues();
    }

    ColorVariables.prototype.validateColorValue = function(stringToTest){
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
    }

    ColorVariables.prototype.setBgColor = function($colorField) {
        var colorValue = $colorField.val();
        if(!colorValue || !this.validateColorValue(colorValue)) {
            $colorField.css({
                backgroundColor: '',
                color: ''
            });
        }
        else {
            var textColor = 'black';
            $colorField.css({
                backgroundColor: colorValue,
            });
            var jColor = $.Color($colorField[0].style.backgroundColor);
            if(jColor.lightness() <= 0.5) {
                textColor = 'white';
            }
            if(textColor === 'white' && jColor.alpha() <= 0.4) {
                textColor = 'black';
            }
            $colorField.css({
                color: textColor
            });
        }
    }

    ColorVariables.prototype.setVariables = function() {
        var variables = {};
        $('#color-variable-values').find('.color-fieldset').each(function() {
                var $fieldset = $(this);
                var name = $fieldset.find('.variable-name').val();
                var value = $fieldset.find('.variable-value').val();
                if(name && value) {
                    variables[name] = value;
                }
        });
        this.colorVariables = variables;
        this.$valueInput.val(JSON.stringify(this.colorVariables));
        this.setSassVariables();
    }

    ColorVariables.prototype.setSassVariables = function() {
        var strArray = []
        $.each(this.inheritedVariables, function(name, colorValue) {
            strArray.push('$color_' + name.replace('-', '_') + ': var(--' + name + ');');
        });
        $.each(this.colorVariables, function(name, colorValue) {
            strArray.push('$color_' + name.replace('-', '_') + ': var(--' + name + ');');
            $('.color-variables-sass').val(strArray.join("\n"));
        });
    }

    var waitForMasterVariable = function() {
        var sec = 0;
        while(!window.colorVariables) {
            ++sec;
        }
        return sec;
    }

    $(document).on('input', '.variable-name', function(e) {
        this.value = this.value.toLowerCase()
            .replace(/[^a-z\-]+/g,'-')
            .replace(/-[-]+/g, '-');
        waitForMasterVariable();
        window.colorVariables.setVariables();
        window.colorVariables.buildInheritedValues();
    });

    $(document).on('input', '.variable-value', function(e) {
        waitForMasterVariable();
        window.colorVariables.setBgColor($(this));
        window.colorVariables.setVariables();
        var jColor = $.Color($(this)[0].style.backgroundColor);
        var $fieldset = $(this).closest('.color-fieldset');
        var $colorPicker = $fieldset.find('.color-picker');
        $colorPicker.val(jColor.toHexString());
        window.colorVariables.buildInheritedValues();
    });

    $(document).on('change', '.color-picker', function(e) {
        waitForMasterVariable();
        var $fieldset = $(this).closest('.color-fieldset');
        var color = $(this).val();
        var $valueField = $fieldset.find('.variable-value');
        $valueField.val(color);
        window.colorVariables.setBgColor($valueField);
        window.colorVariables.setVariables();
        window.colorVariables.buildInheritedValues();
    });

    $(document).on('click', '.remove-color-value', function(e) {
        waitForMasterVariable();
        e.preventDefault();
        var $fieldset = $(this).closest('.color-fieldset');
        $fieldset.remove();
        window.colorVariables.setVariables();
        window.colorVariables.buildInheritedValues();
    });

    $(document).on('click', '.add-color-value', function(e) {
        waitForMasterVariable();
        e.preventDefault();
        window.colorVariables.addColorInput();
        window.colorVariables.setVariables();
    });

    $(document).on('click', '.color-variable-override-color', function(e){
        e.preventDefault();
        var $wrap = $(this).closest('.inherited-color');
        var name = $wrap.data('variable-name');
        var colorValue = $wrap.data('variable-value');
        window.colorVariables.addColorInput(name, colorValue);
        window.colorVariables.setVariables();
        window.colorVariables.buildInheritedValues();
    });

    $(document).ready(function() {
        window.colorVariables = new ColorVariables();
    });
})(jQuery)
