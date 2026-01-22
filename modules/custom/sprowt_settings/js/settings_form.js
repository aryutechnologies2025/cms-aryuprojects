(function($, Sprowt, Drupal){
    Drupal.behaviors.sprowt_settings_form = {
        attach: function(context, settings) {
            let t = this;
            $(once('sprowt_settings_form', 'body')).each(function(){
                t.refreshTokenList();
                t.refreshCtmTable();
                t.openVerticalTab();
                $(document).on('keyup', '.custom-machine-name', function(e) {
                    var $input = $(this);
                    var value = $(this).val();
                    if(/[^a-z0-9_]/g.test(value)) {
                        $input.val(
                            value.toLowerCase()
                                .replace(/[^a-z0-9_]+/g, '_')
                        );
                        window.setTimeout(function() {
                            var value = $input.val();
                            $input.val(
                                value.toLowerCase()
                                    .replace(/[^a-z0-9_]+/g, '_')
                                    .replace(/_[_]+/, '_')
                                    .replace(/^[_]+/, '')
                                    .replace(/[_]+$/, '')
                            );
                            t.refreshTokenValue();
                            t.refreshCtmValue();
                        }, 500);
                    }
                    t.refreshTokenValue();
                    t.refreshCtmValue();
                });
                $(document).on('keyup', '.custom-token-value', function(e) {
                    t.refreshTokenValue();
                });
                $(document).on('click', '.remove-token', function(e) {
                    e.preventDefault();
                    let $table = $('#custom-token-table');
                    let $row = $(this).closest('tr');
                    $row.remove();
                    t.refreshTokenValue();
                    t.restripeTable($table);
                });
                $(document).on('click', '#addTokenButton', function(e) {
                    e.preventDefault();
                    let $table = $('#custom-token-table');
                    let $tableBody = $table.find('tbody');
                    let $row = t.getTemplate('token-template');
                    let $machineName = $row.find('.custom-token-machine-name');
                    $machineName.closest('div').find('label').addClass('form-required').addClass('js-form-required');
                    $tableBody.append($row);
                    Drupal.attachBehaviors($row[0]);
                    t.refreshTokenValue();
                    t.restripeTable($table);
                });
                $(document).on('click', '.remove-ctm-button', function(e) {
                    e.preventDefault();
                    let $table = $('#ctm-table');
                    let $row = $(this).closest('tr');
                    $row.remove();
                    t.refreshCtmValue();
                    t.restripeTable($table);
                });
                $(document).on('click', '#add-ctm-button', function(e) {
                    e.preventDefault();
                    let $table = $('#ctm-table');
                    t.addCtmTableRow('', {
                        phone: 'company_phone',
                        value: 'number_only'
                    });
                    t.refreshCtmValue();
                    t.restripeTable($table);
                });
                $(document).on('change', '.ctm-select', function(e) {
                    t.refreshCtmValue();
                    t.ctmHideShowCustom($(this));
                });
                $(document).on('keyup', '.ctm-input', function(e) {
                    t.refreshCtmValue();
                });
                $(document).on('click', '.vertical-tabs__menu-link', function (e) {
                    let href = $(this).attr('href');
                    let tabId = href.replace('#', '');
                    t.updateUrlQuery(tabId);
                });

                $(document).on('click', '#syncTokenButton', function (e) {
                    e.preventDefault();
                    let jq = $.getJSON('/sprowt-content/custom-tokens-from-source', function (data) {
                        let tokens = $('#token-value-field').val();
                        if(typeof tokens === 'string') {
                            tokens = JSON.parse(tokens);
                        }
                        if(JSON.stringify(tokens) === '[]') {
                            tokens = {};
                        }
                        let added = [];
                        $.each(data, function (key, val) {
                            if(!tokens[key]) {
                                tokens[key] = val;
                                added.push(key);
                            }
                        });
                        $('#token-value-field').val(JSON.stringify(tokens));
                        t.refreshTokenList();
                        if(!added.length) {
                            Sprowt.insertMessage('warning', 'No source tokens found.');
                            window.scrollTo({
                                top: 0,
                                left: 0,
                                behavior: 'smooth'
                            });
                        }
                    }).fail(function(jqXHR, textStatus, errorThrown) {
                        let errorMessage = 'Error retrieving tokens.';
                        console.error(errorMessage, {
                            jqXHR: jqXHR,
                            textStatus: textStatus,
                            errorThrown: errorThrown
                        });
                        Sprowt.insertMessage('error', errorMessage);
                        window.scrollTo({
                            top: 0,
                            left: 0,
                            behavior: 'smooth'
                        });
                    });
                });
            });
            $(once('sprowt-settings-sprowt-settings', '#sprowt-settings-sprowt-settings')).each(function() {
                let $form = $(this);
                let form = this;
                function isInvalid($form) {
                    let req = [];
                    let ret = false;
                    let invalid = [];
                    $form.find('input').each(function () {
                        if (!this.validity.valid) {
                            invalid.push(this);
                        }
                    });
                    $form.find('select').each(function () {
                        if (!this.validity.valid) {
                            invalid.push(this);
                        }
                    });
                    $form.find('textarea').each(function () {
                        if (!this.validity.valid) {
                            invalid.push(this);
                        }
                    });
                    if (invalid.length > 0) {
                        ret = true;
                        let first = invalid.shift();
                        let $tab = $(first).closest('.js-vertical-tabs-pane');
                        if (!$tab.attr('open')) {
                            let verticalTab = $tab.data('verticalTab');
                            verticalTab.focus();
                        }
                        window.setTimeout(function() {
                            first.focus();
                            form.reportValidity();
                        }, 300);
                    }
                    console.log({
                        isInvalid: ret,
                        invalid: invalid,
                    });
                    return ret;
                }
                this.addEventListener('submit', function(e) {
                    let invalid = isInvalid($form);
                    console.log({
                        invalid: invalid,
                    });
                    if(invalid) {
                        e.preventDefault();
                    }
                });
            });

            $('.phone-number-pattern-input').each(function(){
                var $input = $(this);
                var $formItem = $input.closest('.form-item');
                var $example = $formItem.find('.pattern-example');
                $(once('settings-form--phone-number-pattern-input', this)).each(function() {
                    $(this).on('keyup', function() {
                        var replacement = $input.val();
                        var example = '1234567890'.replace(/.*(\d{3})[^\d]{0,7}(\d{3})[^\d]{0,7}(\d{4}).*/, replacement);
                        $example.text(example);
                        $input.closest('form').find('.phone-field').each(function() {
                            t.formatPhoneField($(this));
                        });
                    });
                });
            });

            $(once('settings-form--phone-field-keyup','.phone-field')).each(function() {
                var $input = $(this);
                $input.data('input-val', $input.val());
                t.formatPhoneField($input);
                $input.on('keyup', function() {
                    $input.data('input-val', $input.val());
                    t.formatPhoneField($input);
                });
            });

            $(once('add-new-industry-button--click','#add-new-industry-button')).each(function() {
                $(this).on('click', function(e) {
                    e.preventDefault();
                    $(this).closest('form').find('.company-industry-other').show();
                    $(this).hide();
                });
            });
        },
        getTemplate(scriptId) {
            return Sprowt.getTemplate(scriptId);
        },
        updateUrlQuery(tabId) {
            let url = new URL(window.location.href);
            let currentTabId = url.searchParams.get('tab');
            if(currentTabId === tabId) {
                return;
            }
            if(tabId) {
                url.searchParams.set('tab', tabId);
            }
            else {
                url.searchParams.delete('tab');
            }
            window.history.pushState({tab: tabId}, '', url.href);
            let currentDest = url.href.replace(url.protocol, '').replace('//' + url.host, '');
            $('#sprowt-settings-sprowt-settings').attr('action', currentDest);
            $('a').each(function() {
                let $a = $(this);
                let hrefString = $a.attr('href');
                if(!hrefString || hrefString.indexOf('#') === 0) {
                    return;
                }
                let abs = true;
                if(hrefString.indexOf('/') === 0) {
                    abs = false;
                }
                if(!abs) {
                    hrefString = url.protocol + '//' + url.host + hrefString;
                }
                let href = new URL(hrefString);
                let hasDest = href.searchParams.get('destination');
                if(hasDest) {
                    href.searchParams.set('destination', currentDest);
                    let newHrefString = href.href;
                    if (!abs) {
                        newHrefString = newHrefString.replace(url.protocol, '').replace('//' + url.host, '');
                    }
                    $a.attr('href', newHrefString);
                }
            });
        },
        openVerticalTab(tries = 0) {
            let url = new URL(window.location.href);
            let tabId = url.searchParams.get('tab');
            let t = this;
            if(tabId) {
                let $pane = $('#' + tabId);
                let tab = $pane.data('verticalTab');
                if(!tab && tries < 3) {
                    ++ tries;
                    window.setTimeout(function() {
                        t.openVerticalTab(tries);
                    }, 300);
                }
                else if(tab) {
                    tab.focus();
                }
            }
        },
        formatPhoneField($input) {
            var pattern = $input.closest('form').find('.phone-number-pattern-input').val();
            var val = $input.data('input-val');
            if(val) {
                var numVal = val.replace(/[^\d]/g, '').toString();
                if(!numVal.length) {
                    $input.val('');
                }
                else if (numVal.length <= 3) {
                    var newVal = numVal.replace(/(\d{1,3})/, pattern.replace(/(.*\$1).*/, '$1'));
                    $input.val(newVal);
                }
                else if (numVal.length <= 6) {
                    var newVal = numVal.replace(/(\d{3})(\d{1,3})/, pattern.replace(/(.*\$2).*/, '$1'));
                    $input.val(newVal);
                }
                else {
                    var newVal = numVal.replace(/(\d{3})(\d{3})(\d{1,4}).*/, pattern);
                    $input.val(newVal);
                }
            }
        },
        refreshCtmValue() {
            let t = this;
            let $table = $('#ctm-table');
            let $tableBody = $table.find('tbody');
            let buttons = {};
            $tableBody.find('tr').each(function() {
                let $row = $(this);
                let machineName = $row.find('.ctm-button-machine-name').val();
                let phone = Sprowt.selectValue($row.find('.ctm-button-number'));
                if(phone === 'custom') {
                    phone = $row.find('.ctm-custom-number').val();
                    phone = phone.replace(/[^\d]+/g, '');
                }
                let value = Sprowt.selectValue($row.find('.ctm-button-value'));
                if(value === 'custom') {
                    value = $row.find('.ctm-custom-value').val();
                }
                if(machineName && phone && value) {
                    buttons[machineName] = {
                        phone: phone,
                        value: value
                    };
                }
            });
            $('#ctm-buttons-value-field').val(JSON.stringify(buttons));
        },
        ctmHideShowCustom($select) {
            let val = Sprowt.selectValue($select);
            let $custom = $select.closest('tr').find('.' + $select.data('custom'));
            if(val === 'custom') {
                $custom.closest('.form-item').show();
                $custom.attr('required', 'required');
                $custom.closest('div').find('label').addClass('form-required').addClass('js-form-required');
            }
            else {
                $custom.closest('.form-item').hide();
                $custom.removeAttr('required');
            }
        },
        addCtmTableRow(machineName, val) {
            let t = this;
            let $table = $('#ctm-table');
            let $tableBody = $table.find('tbody');
            let $row = t.getTemplate('ctm-template');
            let getField = function(selector) {
                let $field = $row.find(selector);
                if($field.attr('required')) {
                    $field.closest('div').find('label').addClass('form-required').addClass('js-form-required');
                }
                return $field;
            };
            let $machineName = getField('.ctm-button-machine-name');
            $machineName.val(machineName);
            let $number = getField('.ctm-button-number');
            let $customNumber = getField('.ctm-custom-number');
            if(val.phone === 'company_phone' || val.phone === 'contact_phone') {
                $number.val(val.phone);
            }
            else {
                $number.val('custom');
                $customNumber.val(val.phone);
            }

            let $value = getField('.ctm-button-value');
            let $customValue = getField('.ctm-custom-value');
            if(val.value === 'number_only') {
                $value.val(val.value);
            }
            else {
                $value.val('custom');
                $customValue.val(val.value);
            }

            $tableBody.append($row);
            $number.select2();
            $value.select2();
            t.ctmHideShowCustom($number);
            t.ctmHideShowCustom($value);
            $customNumber.data('input-val', $customNumber.val());
            t.formatPhoneField($customNumber);
            $customNumber.each(function() {
                $(once('addCtmTableRow--customNumber--keyup', this)).each(function() {
                    $(this).on('keyup', function() {
                        $customNumber.data('input-val', $customNumber.val());
                        t.formatPhoneField($customNumber);
                        t.refreshCtmValue();
                    });
                });
            });
            Drupal.attachBehaviors($row[0]);
        },
        refreshCtmTable() {
            let t = this;
            let $table = $('#ctm-table');
            let $tableBody = $table.find('tbody');
            let buttons = $('#ctm-buttons-value-field').val();
            if(typeof buttons === 'string') {
                buttons = JSON.parse(buttons);
            }
            $tableBody.html('');

            $.each(buttons, function (machineName, val) {
                t.addCtmTableRow(machineName, val);
            });
            t.restripeTable($table);
        },
        refreshTokenValue() {
            let t = this;
            let $table = $('#custom-token-table');
            let $tableBody = $table.find('tbody');
            let tokens = {};
            $tableBody.find('tr').each(function() {
                let $row = $(this);
                let machineName = $row.find('.custom-token-machine-name').val();
                let val = $row.find('.custom-token-value').val();
                if(machineName) {
                    tokens[machineName] = val;
                }
            });
            $('#token-value-field').val(JSON.stringify(tokens));
        },
        restripeTable($table) {
            let $tableBody = $table.find('tbody');
            let i = 0;
            $tableBody.find('tr').each(function() {
                ++i;
                $(this).removeClass('odd').removeClass('even');
                if(i % 2 === 0) {
                    $(this).addClass('even');
                }
                else {
                    $(this).addClass('odd');
                }
            });
        },
        refreshTokenList() {
            let t = this;
            let $table = $('#custom-token-table');
            let $tableBody = $table.find('tbody');
            let tokens = $('#token-value-field').val();
            if(typeof tokens === 'string') {
                tokens = JSON.parse(tokens);
            }
            $tableBody.html('');
            $.each(tokens, function (machineName, val) {
                let $row = t.getTemplate('token-template');
                $row.find('.custom-token-machine-name').val(machineName);
                $machineName = $row.find('.custom-token-machine-name');
                $machineName.closest('div').find('label').addClass('form-required').addClass('js-form-required');
                $row.find('.custom-token-value').val(val);
                $tableBody.append($row);
            });
            t.restripeTable($table);
            Drupal.attachBehaviors($table[0]);
        }
    };
})(jQuery, Sprowt, Drupal);
