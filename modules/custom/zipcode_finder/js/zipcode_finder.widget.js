(function($, Drupal, drupalSettings, Sprowt) {

    const ZipcodeWidget = function($widget) {
        this.$widget = $widget;
        this.$valueField = $widget.find('.zipcodes-widget-value');
        this.$errorField = $widget.find('.zipcodes-widget-errors');
        this.errors = this.$errorField.val() || '[]';
        if(typeof this.errors === 'string') {
            this.errors = JSON.parse(this.errors);
        }
        if(!this.errors) {
            this.errors = [];
        }
        this.$table = $widget.find('.zipcodes-widget-single-element-wrap-table');
        this.$wrap = $widget.find('.zipcodes-widget-single-element-wrap');
        this.$template = $widget.find('.zipcodes-widget-single-element-template');
        this.setWrapFromValue();
        let t = this;
        this.$widget.on('click', '.zipcodes-widget-clear-button', function(e) {
            e.preventDefault();
            t.$wrap.html('');
            t.$valueField.val('[]');
        });
        this.$widget.on('click', '.zipcodes-widget-bulk-remove-button', function(e) {
            e.preventDefault();
            let $checkboxes = t.$wrap.find('.zipcodes-widget-bulk-remove-check');
            $checkboxes.each(function() {
                if($(this).prop('checked')) {
                    $(this).closest('.zipcodes-widget-single-element').remove();
                }
            });
            t.setValueFromWrap();
        });
    };
    ZipcodeWidget.prototype = $.extend(ZipcodeWidget.prototype, {
        getValue() {
            let t = this;
            let value = this.$valueField.val();
            if(typeof value === 'string') {
                value = JSON.parse(value);
            }
            return value || [];
        },
        addSingleElement: function(zipcode, error = false) {
            let t = this;
            let $tmp = Sprowt.getTemplate(this.$template);
            if(error) {
                $tmp.addClass('error');
            }
            $tmp.find('.zipcodes-widget-zip-value').html(zipcode.trim());
            let id = 'zipcodes-widget-single-element--' + this.$wrap.find('.zipcodes-widget-zip-value').length;
            $tmp.find('.zipcodes-widget-zip-value').attr('for', id);
            $tmp.find('.zipcodes-widget-bulk-remove-check').attr('id', id);
            this.$wrap.append($tmp);
            $(once('zipcode-widget-remove', $tmp.find('.zipcodes-widget-remove-button'))).each(function() {
                $(this).click(function(e) {
                    e.preventDefault();
                    $tmp.remove();
                    t.setValueFromWrap();
                });
            });
        },
        setValueFromWrap: function() {
            let t = this;
            let newVal = [];
            this.$wrap.find('.zipcodes-widget-zip-value').each(function() {
                let zip = $(this).text();
                newVal.push(zip.trim());
            });
            this.$valueField.val(JSON.stringify(newVal));
        },
        setWrapFromValue: function () {
            let t = this;
            let value = this.getValue();
            this.$wrap.html('');
            if(value && value.length > 0) {
                $.each(value, function(idx, val) {
                    let error = t.errors.indexOf(val.trim()) >= 0;
                    t.addSingleElement(val.trim(), error);
                });
            }
        },
        bulkAdd: function(bulkStr) {
            let t = this;
            let lines = bulkStr.split("\n");
            let currentValue = this.getValue() || [];
            $.each(lines, function(idx, line) {
                let val = line.trim();
                if(currentValue.indexOf(val) < 0) {
                    t.addSingleElement(val);
                }
            });
            t.setValueFromWrap();
        },
        scrollTo() {
           this.$widget[0].scrollIntoView({
               behavior: 'smooth'
           });
        }
    });

    Drupal.behaviors.zipcodesWidget = {
        widgets: {},
        attach: function (context, settings) {
            let t = this;
            $(once('zipcodesWidgetBehavior', '.zipcodes-widget-fieldset')).each(function() {
                let id = $(this).attr('id');
                t.widgets[id] = new ZipcodeWidget($(this));
            });
            $(once('zipcodesWidgetModal', '.zipcodes-widget-modal')).each(function() {
                let $modal = $(this);
                let $dialog = $modal.find('#drupal-modal');
                let $field = $modal.find('.zipcodes-widget-bulk-add-ui-textarea');
                let wrapperId = $field.data('wrapper-id');
                let zipcodeWidget = t.widgets[wrapperId];
                let $dummy = $modal.find('.zipcodes-widget-modal-dummy-button');
                $dummy.hide();
                let $add = $('<button type="button" class="button button--small add-button">Add</button>');
                let $cancel = $('<button type="button" class="button button--small close-button button--danger">Close</button>');
                $dummy.after($cancel);
                $cancel.after($add);
                $add.click(function(e) {
                    e.preventDefault();
                    let val = $field.val().trim();
                    if(val) {
                        zipcodeWidget.bulkAdd(val);
                    }
                    Drupal.dialog($dialog.get(0)).close();
                    $dialog.remove();
                    zipcodeWidget.scrollTo();
                });
                $cancel.click(function(e) {
                    e.preventDefault();
                    Drupal.dialog($dialog.get(0)).close();
                    $dialog.remove();
                    zipcodeWidget.scrollTo();
                });
            });
        }
    };

})(jQuery, Drupal, drupalSettings, Sprowt);
