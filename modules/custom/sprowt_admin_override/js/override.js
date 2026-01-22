(function ($, Drupal, drupalSettings, Sprowt) {
    Drupal.behaviors.select2.settings.minimum_multiple = 0;
    Drupal.behaviors.select2.settings.minimum_single = 0;
    var getElementOptions = Drupal.behaviors.select2.getElementOptions;
    Drupal.behaviors.select2.getElementOptions = function (element) {

        //replace "- None -" with "- Select -"
        let $options = $(element).find('option');
        $options.each(function() {
            let text = $(this).text();
            if(text === '- None -') {
                $(this).html('- Select -');
            }
        });

        var options = getElementOptions.call(Drupal.behaviors.select2, element);

        var template = function (state) {
            var $state = $('<span>'+state.text+'</span>');
            $state.attr('title', state.text);
            return $state;
        };
        options.templateResult = template;

        // var $element = $(element);
        // var $parent = $element.parent();
        // var width = $element.width() + 45;
        // $parent.css('minWidth', width);
        // options.width = '100%';


        var currentWidth = 0;
        if(options.width) {
            currentWidth = parseFloat(options.width.replace(/[^\d\.]/g, ''));
        }
        var dimension;
        var $element = $(element);
        if(options.multiple || $element.attr('multiple')) {
            // improve the way multi select is handled
            $element.prepend('<option class="all-option" value="_all_">- Select All -</option>');
            if($element[0].hasAttribute('data-sprowt2-select2-multi-events')) {
                $element.off('select2:select');
                $element.off('select2:close');
                $element.removeAttr('data-sprowt2-select2-multi-events');
            }
            $element.on('select2:select', function(e) {
                var data = e.params.data;
                if(data.id && data.id === '_all_') {
                    e.preventDefault();
                    let val = [];
                    $element.find('option').each(function () {
                        let $opt = $(this);
                        if($opt.attr('value') && $opt.attr('value') !== '_all_') {
                            val.push($opt.attr('value'));
                        }
                    });
                    $element.val(val);
                    $element.trigger('change.select2');
                    $element.select2('close');
                }
            });

            //if the form is autosubmitted, auto submit on select2 close rather than on change
            $element.attr('data-bef-auto-submit-exclude', 'exclude');
            options.closeOnSelect = false;
            options.allowClear = true;
            options.placeholder = '';
            var selectors = 'form[data-bef-auto-submit-full-form], [data-bef-auto-submit-full-form] form, [data-bef-auto-submit]';
            if($element.closest(selectors).length > 0) {
                $element.on('select2:close', function(e) {
                    var $target = $(e.target);
                    $element.removeAttr('data-bef-auto-submit-exclude');
                    var $submit = $target.closest('form').find('[data-bef-auto-submit-click]');
                    $submit.click();
                });
            }
            $element.attr('data-sprowt2-select2-multi-events', 'added');
        }

        let getWidth = function ($el) {
            var $clone = $el.clone();
            $clone.attr('style', 'width: auto !important; min-width: auto !important; max-width: 100% !important; padding: 0 !important; border: none !important; margin: 0 !important;');
            $clone.appendTo('body');
            var width = $clone.width();
            $clone.remove();
            return width;
        };

        //get width of element with all current options
        let width = getWidth($element);
        // determine width of optgroups if they exist
        // and use the largest width
        $element.find('optgroup').each(function() {
            let $test = $("<select></select>");
            let $testOpt = $('<option></option>');
            $testOpt.append($(this).attr('label'));
            $test.append($testOpt);
            let groupWidth = getWidth($test);
            if(groupWidth > width) {
                width = groupWidth;
            }
        });


        dimension = 'px';
        width = width + 100; //add padding
        if(currentWidth < width) {
            options.width = width + dimension;
        }
        if($(element).closest('.ui-dialog-content').length > 0) {
            options.dropdownParent = $(element).closest('.ui-dialog-content');
        }

        $element.attr('data-select2-options-override', 'overridden');
        let funcTxt = $element.data('select2-options-modify');
        let func = Sprowt.get(window, funcTxt);
        if(func && typeof func === 'function') {
            options = func(options, $element);
        }

        return options;
    };

    Drupal.behaviors.sprowt_admin_override_select_update = {
        attach: function (context, settings) {
            let $selects = $(once('sprowt_admin_override_select_update', 'select', context));
            $selects.each(function() {
                //replace "- None -" with "- Select -"
                let $options = $(this).find('option');
                $options.each(function() {
                    let text = $(this).text();
                    if(text === '- None -') {
                        $(this).html('- Select -');
                    }
                });
            });

            $(once('select2Check', 'select.select2-hidden-accessible', context)).each(function() {
                let $select = $(this);
                //didn't use overridden options for some reason
                if($select.data('select2-options-override') !== 'overridden') {
                    $select.select2('destroy');
                    let options = Drupal.behaviors.select2.getElementOptions($select);
                    $select.select2(options);
                }
            });
            let $moreSelects = $(once('sprowt_admin_override_select_enable_select2', 'select', context));
            $moreSelects.each(function() {
                let $select = $(this);
                if(!$select.hasClass("select2-hidden-accessible")) {
                    Drupal.behaviors.select2.createSelect2($select);
                }
            });
        }
    };

    Drupal.behaviors.sprowt_admin_override_layout_builder_browser_filter = {
        attach: function (context, settings) {
            var self = this;
            if($('.js-layout-builder-categories').length > 0 && $('.js-layout-builder-filter').length > 0) {
                $(once('sprowt_admin_override_layout_builder_browser_filter--keyup', '.js-layout-builder-filter', context)).each(function() {
                    $(this).on('keyup', function () {
                        var val = $(this).val();
                        $('.js-layout-builder-block-link').show();
                        $('.js-layout-builder-block-link').each(function() {
                            var $link = $(this);
                            if(!self.filterByBlockName($link.text(), val)) {
                                $link.hide();
                            }
                        });
                    });
                });
            }
        },
        filterByBlockName(text, filter) {
            if(!filter) {
                return true;
            }
            var ret = true;
            var words = filter.split(' ');
            $.each(words, function(idx, word) {
                ret &= text.toLowerCase().indexOf(word.toLowerCase()) >= 0;
            });
            return ret;
        }
    };

    $(document).ready(function() {
        var $verticalTabs = $('.vertical-tabs__items--processed > details');
        $verticalTabs.each(function() {
            var $this = $(this);
            var verticalTab = $(this).data('verticalTab');
            var focus = verticalTab.focus;
            var newFocus = function() {
                focus.call(verticalTab, arguments);
                //add select 2 to vertical tabs
                $this.trigger('verticalTabFocus');
                Drupal.behaviors.select2.attach(document, {});
                Drupal.behaviors.sprowt_admin_override_layout_builder_browser_filter.attach(document, {});
            }
            verticalTab.focus = newFocus;
        });

        var openDialogFunction = Drupal.AjaxCommands.prototype.openDialog;
        var newOpenDialogFunction = function(ajax, response, status) {
            openDialogFunction.call(Drupal.AjaxCommands.prototype, ajax, response, status);
            //add select2 to open dialogs
            Drupal.behaviors.select2.attach(document, {});
            Drupal.behaviors.sprowt_admin_override_layout_builder_browser_filter.attach(document, {});
        };

        Drupal.AjaxCommands.prototype.openDialog = newOpenDialogFunction;

        $('form.layout-builder-form').each(function() {
            let $actions = $(this).find('#edit-actions');
            $(window).scroll(function() {
                var element = $actions[0];
                if(window.scrollY > (element.offsetTop + element.offsetHeight)) {
                    $actions.addClass('scrolled-past');
                }
                else {
                    $actions.removeClass('scrolled-past');
                }
            });
        });

        if(Drupal.behaviors.dialog) {
            var prepareDialogButtonsOriginal = Drupal.behaviors.dialog.prepareDialogButtons;
            var newPrepareDialogButtons = function ($dialog) {
                var buttons = [];
                if ($dialog.find('#layout-builder-update-block').length > 0
                    || $dialog.find('#layout-builder-add-block').length > 0
                ) {
                    buttons = [];
                    var $buttons = $dialog.find('input[data-drupal-selector="edit-actions-submit"]');
                    $buttons.each(function () {
                        var $originalButton = $(this).css({
                            display: 'none'
                        });
                        buttons.push({
                            text: $originalButton.html() || $originalButton.attr('value'),
                            class: $originalButton.attr('class'),
                            click: function click(e) {
                                if ($originalButton.is('a')) {
                                    $originalButton[0].click();
                                } else {
                                    $originalButton.trigger('mousedown').trigger('mouseup').trigger('click');
                                    e.preventDefault();
                                }
                            }
                        });
                    });
                } else {
                    buttons = prepareDialogButtonsOriginal($dialog);
                }
                return buttons;
            };

            Drupal.behaviors.dialog.prepareDialogButtons = newPrepareDialogButtons;
        }

        if(Drupal.Message) {
            if(!Drupal.Message.messageWrapper) {
                Drupal.Message.messageWrapper = Drupal.Message.defaultWrapper();
            }
            let MessageAdd = Drupal.Message.prototype.add;
            let MessageRemove = Drupal.Message.prototype.remove;
            let MessageClear = Drupal.Message.prototype.clear;

            if(!Drupal.Message.messageWrapper) {
                Drupal.Message.messageWrapper = Drupal.Message.defaultWrapper();
            }

            Drupal.Message.prototype.add = function (message, options = {}) {
                MessageAdd.call(Drupal.Message, message, options);
                $('body').trigger('messageAdd', {
                    message: message,
                    options: options
                });
            };

            Drupal.Message.prototype.remove = function (id) {
                MessageRemove.call(Drupal.Message, id);
                $('body').trigger('messageRemove', {
                    id: id
                });
            };


            Drupal.Message.prototype.clear = function () {
                MessageClear.call(Drupal.Message);
                $('body').trigger('messageClear');
            };

        }

    });

    //fix conflict between jquery ui and skeditor
    $.widget( "ui.dialog", $.ui.dialog, {
        /*! jQuery UI - v1.11.4 - 2015-06-05
         *  http://bugs.jqueryui.com/ticket/9087#comment:27 - bugfix
         *  http://bugs.jqueryui.com/ticket/4727#comment:23 - bugfix
         *  allowInteraction fix to accommodate windowed editors
         */
        _allowInteraction: function( event ) {
            if ( this._super( event ) ) {
                return true;
            }

            // address interaction issues with general iframes with the dialog
            if ( event.target.ownerDocument != this.document[ 0 ] ) {
                return true;
            }

            // address interaction issues with dialog window
            if ( $( event.target ).closest( ".cke_dialog" ).length ) {
                return true;
            }

            // address interaction issues with iframe based drop downs in IE
            if ( $( event.target ).closest( ".cke" ).length ) {
                return true;
            }
        }
    });

    Drupal.behaviors.sprowtAdminTelephoneFields = {
        formatPhoneField($input) {
            var pattern = $input.data('sprowt-phone-format') || '$1-$2-$3';
            var val = $input.data('input-val');
            if(val) {
                var numVal = val.replace(/[^\d]/g, '').toString();
                if(numVal.length) {
                    if (numVal.length <= 3) {
                        let newVal = numVal.replace(/(\d{1,3})/, pattern.replace(/(.*\$1).*/, '$1'));
                        $input.val(newVal);
                    } else if (numVal.length <= 6) {
                        let newVal = numVal.replace(/(\d{3})(\d{1,3})/, pattern.replace(/(.*\$2).*/, '$1'));
                        $input.val(newVal);
                    } else {
                        let newVal = numVal.replace(/(\d{3})(\d{3})(\d{1,4}).*/, pattern);
                        $input.val(newVal);
                    }
                }
            }
        },
        attach(context, settings) {
            let t = this;
            $(once('sprowtAdminTelephoneFields', 'input[type="tel"]')).each(function() {
                let pattern = $(this).data('sprowt-phone-format');
                let placeholder = Sprowt.formatPhone('1234567890', pattern);
                $(this).attr('placeholder', placeholder);
                $(this).data('input-val', $(this).val());
                $(this).on('input', function() {
                    let val = $(this).val();
                    $(this).data('input-val', val);
                    if(val.indexOf('[') < 0) {
                        t.formatPhoneField($(this));
                    }
                });
            });
        }
    };

    //disable links inside ckeditor
    $('.ck-editor').on('click', 'a', function(e) {
        e.preventDefault();
    });

    //add close button to js generated messages
    let messageTheme = Drupal.theme.message;

    Drupal.theme.message = ({ text }, { type, id }) => {
        let wrapper = messageTheme.call(this, {text}, {type, id});
        let hideButton = $('<button type="button" class="button button--dismiss" title="Dismiss"><span class="icon-close"></span>Close</button>');
        $(wrapper).prepend(hideButton);
        Drupal.behaviors.ginMessages.attach($(wrapper)[0]);
        return $(wrapper)[0];
    };

    $(document).on('state:visible', function (e) {
        if (e.trigger) {
            let $element = $(e.target);
            if($element.is('select')) {
                if(!!e.value) {
                    if(!$element.hasClass('select2-hidden-accessible')) {
                        let select2Opts = Drupal.behaviors.select2.getElementOptions($element);
                        $element.select2(select2Opts);
                    }
                }
                else {
                    if($element.hasClass('select2-hidden-accessible')) {
                        $element.select2('destroy');
                    }
                }
            }
        }
    });

    // Insert the span and add click event listener
    // document.querySelectorAll('.layout-builder__add-section').forEach(section => {
    //     const span = document.createElement('span');
    //     span.className = 'section-options';
    //     span.textContent = 'Options'; // Optional label
    //     section.insertBefore(span, section.firstChild);

    //     // Add click listener to the span
    //     span.addEventListener('click', (e) => {
    //     e.stopPropagation(); // Prevent bubbling if needed
    //     section.classList.toggle('open'); // Adds or removes the 'open' class
    //     });
    // });

})(jQuery, Drupal, drupalSettings, Sprowt);
