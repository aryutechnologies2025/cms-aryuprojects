(function($, Drupal, Sprowt, once){

    const Field = function($field) {
        this.$field = $field;
        this.$valueField = $field.find('.template-field-hidden-value');
        this.$valueWrap = $field.find('.template-field-value-wrap');
        this.$addButton = $field.find('.template-field-add-button');
        this.$template = $field.find('.template-field-template');
        this.objectDef = this.extractJsonValue(this.$template.data('object-def'));
        this.carryOverElements = this.extractJsonValue(this.$template.data('carry-over'));
        this.carryOverValues = {};
        this.value = this.extractJsonValue(this.$valueField.val() || []);
        this.setHtml();
        this.events();
        this.setValueFromHtml();
        this.$field.data('templateField', this);
    };

    Field.prototype = $.extend(Field.prototype, {
        extractJsonValue: function(jsonValue) {
            if(typeof jsonValue === 'string') {
                return JSON.parse(jsonValue);
            }

            return jsonValue;
        },
        setValue: function($input, val) {
            if($input.is('input') && $input.attr('type') === 'checkbox') {
                $input.prop('checked', !!val);
            }
            else {
                $input.val(val);
            }
        },
        setValues: function ($tmp, obj) {
            let t = this;
            $.each(this.objectDef, function(key, def) {
                if(typeof def === 'string') {
                    def = {
                        selector: def
                    };
                }

                if(typeof def === 'object' && typeof def !== 'function') {
                    let $input;
                    if (!!def.selectCallback) {
                        let callback = t.getCallback(def.selectCallback);
                        $input = callback($tmp, def, t);
                    } else if (!!def.selector) {
                        $input = $tmp.find(def.selector);
                    }
                    if ($input) {
                        if (!!def.setCallback) {
                            let setCallback = t.getCallback(def.setCallback);
                            setCallback($input, obj, key, t);
                        }
                        else {
                            t.setValue($input, obj[key] || null);
                        }
                    }
                }
            });
            return $tmp;
        },
        setHtml: function() {
            let t = this;
            this.$valueWrap.html('');
            $.each(this.value, function(vdx, valObj) {
                t.addElement(valObj);
            });
        },
        setCarryOver: function(obj) {
            let t = this;
            $.each(t.carryOverElements, function(idx, el) {
                let current = obj[el];
                if(typeof current === 'undefined' || current === null) {
                    let carryOver = t.carryOverValues || {};
                    let coVal = carryOver[el];
                    if(typeof coVal !== 'undefined') {
                        obj[el] = coVal;
                    }
                }
            });
            return obj;
        },
        addElement: function(obj) {
            let setCallback = !!this.objectDef.setCallBack ? this.getCallback(this.objectDef.setCallBack) : this.setValues.bind(this);
            let $tmp = Sprowt.getTemplate(this.$template);
            this.massageElement($tmp);
            let t = this;
            obj = t.setCarryOver(obj);
            setCallback($tmp, obj, t);
            let $itemWrap = $('<div class="template-field-item"></div>');
            $itemWrap.append($tmp);
            this.$valueWrap.append($itemWrap);
            $tmp.find('[data-js-required="true"]').each(function() {
                Sprowt.makeJsRequired($(this));
            });

            $tmp.trigger('templateFieldAppended');

            let drupalSettings = window.drupalSettings || {};
            Drupal.attachBehaviors($tmp[0], drupalSettings);
        },
        templateId() {
            let fieldId = this.$field.attr('data-drupal-selector');
            let itemId = this.$field.find('.template-field-item').length + 1;
            return fieldId + '--' + itemId;
        },
        massageElement($tmp) {
            let t = this;
            let radiosId = 0;
            $tmp.find('.form-radios').each(function() {
                ++radiosId;
                let name = t.templateId() + '--' + radiosId;
                let $radios = $(this);
                $radios.find('input[type="radio"]').each(function() {
                    $(this).attr('name', name);
                });
            });
        },
        getCallback(str) {
            let callback = Sprowt.get(window, str);
            if(typeof callback === 'function') {
                let functionObj = window;
                if(str.indexOf('.') > -1) {
                    let parts = str.split('.');
                    let funcName = parts.pop();
                    functionObj = Sprowt.get(window, parts.join('.'));
                }
                return callback.bind(functionObj);
            }
            return null;
        },
        extractValue: function($item) {
            let obj = {};
            let t = this;
            $.each(this.objectDef, function(key, def) {
                if(typeof def === 'string') {
                    def = {
                        selector: def
                    };
                }

                if(typeof def === 'object' && typeof def !== 'function') {
                    let $input;
                    if (!!def.selectCallback) {
                        let callback = t.getCallback(def.selectCallback);
                        $input = callback($item, def, t);
                    }
                    else if (!!def.selector) {
                        $input = $item.find(def.selector);
                    }
                    if ($input) {
                        if (!!def.getCallback) {
                            let getCallback = t.getCallback(def.getCallback);
                            getCallback($input, obj, key, t);
                        }
                        else {
                            if($input.is('select')) {
                                obj[key] = Sprowt.selectValue($input);
                            }
                            else {
                                if($input.is('input') && $input.attr('type') === 'checkbox') {
                                    obj[key] = $input.prop('checked');
                                }
                                else {
                                    obj[key] = $input.val();
                                }
                            }
                        }
                    }
                }
            });
            if(!!this.objectDef.extractValueCallback) {
                let extractCallback = t.getCallback(this.objectDef.extractValueCallback);
                obj = extractCallback($item, obj, t);
            }
            let isEmpty = !!this.objectDef.isEmpty ? this.objectDef.isEmpty.bind(this) : function(obj) {
                if(JSON.stringify(obj) === '{}') {
                    return true;
                }
                let ret = true;
                $.each(obj, function(odx, oVal) {
                    if(oVal !== null && oVal !== '') {
                        ret = false;
                    }
                });
                return ret;
            };
            if(isEmpty(obj)) {
                return null;
            }
            return obj;
        },
        setCarryOverValues(obj) {
            this.carryOverValues = JSON.parse(JSON.stringify(obj));
        },
        setValueFromHtml: function() {
            let t = this;
            let val = [];
            this.$valueWrap.children().each(function() {
                let $item = $(this);
                let obj = t.extractValue($item);
                if(obj) {
                    t.setCarryOverValues(obj);
                    val.push(obj);
                }
            });
            this.value = val;
            this.$valueField.val(JSON.stringify(val));
        },
        events: function() {
            let t = this;
            $(once('templateFieldEvents', t.$field)).each(function() {
                t.$addButton.click(function(e) {
                    e.preventDefault();
                    t.addElement({});
                });

                t.$field.on('input', 'input', function() {
                    let $input = $(this);
                    if($input.attr('type') !== 'checkbox') {
                        t.setValueFromHtml();
                    }
                });

                t.$field.on('input', 'textarea', function() {
                    let $input = $(this);
                    if($input.attr('type') !== 'checkbox') {
                        t.setValueFromHtml();
                    }
                });

                t.$field.on('change', 'select', function() {
                    t.setValueFromHtml();
                });

                t.$field.on('change', 'input', function() {
                    let $input = $(this);
                    if($input.attr('type') === 'checkbox') {
                        t.setValueFromHtml();
                    }
                });

                t.$field.on('click', '.remove-button', function(e) {
                    e.preventDefault();
                    $(this).closest('.template-field-item').remove();
                    t.setValueFromHtml();
                });

                t.$field.on('bulkAdd', function(e, items) {
                    $.each(items, function (idx, item) {
                        t.addElement(item);
                    });
                });
                t.$field.on('refreshValue', function() {
                    t.setValueFromHtml();
                });
            });
        }
    });

    Drupal.behaviors.templateFieldField = {
        fields: [],
        attach(context, settings) {
            let t = this;
            $(once('templateFieldField', '.template-field-wrap', context)).each(function() {
                let $field = $(this);
                t.fields.push(new Field($field));
            });
        }
    };


})(jQuery, Drupal, Sprowt, once);
