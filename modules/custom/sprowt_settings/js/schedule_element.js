(function($, Drupal, Sprowt, once) {

    Drupal.behaviors.scheduleElement = {
        attach(context, settings) {
            let t = this;
            $(once('scheduleElementEvents', 'body')).each(function() {
                t.events();
            });
        },
        events() {
            let t = this;
            $(document).on('click', '.add-month', function (e) {
                e.preventDefault();
                let $schedule = $(this).closest('.schedule-item-wrapper');
                let $tmp = t.getMonthTemplate('01-01', $schedule);
                t.select2($tmp);
                $schedule.find('.month-list').append($tmp);
                $schedule.closest('.schedule-element').trigger('refreshValue');
            });

            $(document).on('click', '.remove-month', function (e) {
                e.preventDefault();
                let $schedule = $(this).closest('.schedule-item-wrapper');
                $(this).closest('.monthfield-wrap').remove();
                $schedule.closest('.schedule-element').trigger('refreshValue');
            });

            $(document).on('change', 'select.schedule-type', function() {
                let $schedule = $(this).closest('.schedule-item-wrapper');
                let tmpSchedule = {
                    type: Sprowt.selectValue($(this)),
                };
                t.hideShowType(tmpSchedule, $schedule);
                if(tmpSchedule.type === 'holidays') {
                    let tmpField = { $field: $schedule.closest('.schedule-element')};
                    tmpSchedule.holidayProvider = 'USA';
                    t.setHolidayProviderOptions($schedule, tmpSchedule, tmpField);
                }
            });

            $(document).on('change', '.holiday-provider-select', function() {
                let $schedule = $(this).closest('.schedule-item-wrapper');
                let tmpSchedule = {};
                t.extractValue($schedule, tmpSchedule);
                let tmpField = { $field: $schedule.closest('.schedule-element')};
                t.setHolidayProviderOptions($schedule, tmpSchedule, tmpField);
                $schedule.closest('.schedule-element').trigger('refreshValue');
            });

            $(document).on('change', '.time-all-check', function(e) {
                let $schedule = $(this).closest('.schedule-item-wrapper');
                let hide = $(this).prop('checked');
                if(hide) {
                    $schedule.find('.time-range-wrap').hide();
                }
                else {
                    $schedule.find('.time-range-wrap').show();
                }
            });

            $(document).on('click', '.schedule-done-button', function(e) {
                e.preventDefault();
                let $schedule = $(this).closest('.schedule-item-wrapper');
                let valid = true;
                $schedule.find('.schedule-item').find('input').each(function() {
                    valid = $(this)[0].reportValidity();
                });
                $schedule.find('.schedule-item').find('select').each(function() {
                    valid = $(this)[0].reportValidity();
                });
                if(valid) {
                    $(this).hide();
                    $schedule.closest('.schedule-element').trigger('refreshValue');
                    $schedule.find('.schedule-edit-button').show();
                    $schedule.find('.schedule-item').hide();
                }
            });

            $(document).on('click', '.schedule-edit-button', function(e) {
                e.preventDefault();
                $(this).hide();
                let $schedule = $(this).closest('.schedule-item-wrapper');
                $schedule.closest('.schedule-element').trigger('refreshValue');
                $schedule.find('.schedule-done-button').show();
                $schedule.find('.schedule-item').show();
            });
        },
        updateSummary($schedule, schedule) {
            let t = this;
            let $summaryText = $schedule.find('.summary-text');
            $.getJSON('/sprowt/schedule-text', {schedule: JSON.stringify(schedule)}, function(data) {
                $summaryText.html(data.text);
            }).fail(function() {
                console.error(arguments);
            });
        },
        select2: function($element) {
            let $selects = [];
            if($element.is('select')) {
                $selects = [$element];
            }
            else {
                $selects = $element.find('select');
            }
            $selects.each(function() {
                let $select = $(this);
                if(!$select.hasClass("select2-hidden-accessible")) {
                    let options = Drupal.behaviors.select2.getElementOptions($select);
                    $select.select2(options);
                }
            });
        },
        getMonthTemplate(monthDay, $parent) {
            let t = this;
            if(!$parent.is('.schedule-element')) {
                $parent = $parent.closest('.schedule-element');
            }
            let $script = $parent.find('.monthfield-template');
            let $month = Sprowt.getTemplate($script);
            let selector = $month.find('.form-datetime-wrapper').attr('data-drupal-selector');
            let monthSelector = selector + '-month';
            let daySelector = selector + '-day';
            let $monthField = $month.find('select[data-drupal-selector="' + monthSelector + '"]');
            let $dayField = $month.find('select[data-drupal-selector="' + daySelector + '"]');
            Sprowt.makeJsRequired($monthField);
            Sprowt.makeJsRequired($dayField);
            $monthField.find('option[value=""]').remove();
            $dayField.find('option[value=""]').remove();
            if(monthDay) {
                let parts = monthDay.split('-');
                let month = parseInt(parts[0]);
                let day = parseInt(parts[1]);

                $monthField.val(month);
                $dayField.val(day);
            }
            return $month;
        },
        extractValue($schedule, obj, Field) {
            let t = this;
            obj.timezone = $schedule.find('.timezone-field').val();
            obj.type = Sprowt.selectValue($schedule.find('.schedule-type'));
            let days;
            if(obj.type === 'daysoftheweek') {
                days = [];
                let $checks = $schedule.find('.daysoftheweek-checkbox');
                $checks.each(function() {
                    let $check = $(this);
                    if($check.prop('checked')) {
                        days.push($check.val());
                    }
                });
                obj.days = days;
            }
            else if(obj.type === 'daysofthemonth') {
                days = Sprowt.selectValue($schedule.find('.daysofthemonth-select'));
                obj.days = days;
            }
            else if(obj.type === 'daysoftheyear') {
                days = [];
                $schedule.find('.monthfield-wrap').each(function() {
                    let $month = $(this);
                    let selector = $month.find('.form-datetime-wrapper').attr('data-drupal-selector');
                    let monthSelector = selector + '-month';
                    let daySelector = selector + '-day';
                    let $monthField = $month.find('select[data-drupal-selector="' + monthSelector + '"]');
                    let $dayField = $month.find('select[data-drupal-selector="' + daySelector + '"]');
                    let month = parseInt(Sprowt.selectValue($monthField));
                    let day = parseInt(Sprowt.selectValue($dayField));
                    if(month < 10) {
                        month = '0' + month.toString();
                    }
                    if(day < 10) {
                        day = '0' + day.toString();
                    }
                    days.push(month + '-' + day);
                });
                obj.days = days;
            }
            else if(obj.type === 'monthsoftheyear') {
                days = [];
                let $checks = $schedule.find('.monthsoftheyear-checkbox');
                $checks.each(function() {
                    let $check = $(this);
                    if($check.prop('checked')) {
                        days.push($check.val());
                    }
                });
                obj.days = days;
            }
            else if(obj.type === 'holidays') {
                let $providerSelect = $schedule.find('.holiday-provider-select');
                obj.holidayProvider = Sprowt.selectValue($providerSelect) || 'USA';
                let days = [];
                let $selectWrapper = $schedule.find('.holiday-select-wrapper');
                $selectWrapper.find('input[type="checkbox"]').each(function() {
                    let $check = $(this);
                    let val = $check.attr('value');
                    if($check.prop('checked')) {
                        days.push(val);
                    }
                });
                obj.days = days;
            }
            if($schedule.find('.time-all-check').prop('checked')) {
                obj.time = 'allDay';
            }
            else {
                obj.time = {};
                obj.time.from = $schedule.find('input.time-from')[0].value;
                obj.time.to = $schedule.find('input.time-to')[0].value;
            }
            obj.negate = false;
            if($schedule.find('.negate-radio').length > 0) {
                obj.negate = $schedule.find('.negate-radio[value="1"]').prop('checked');
            }
            t.updateSummary($schedule, obj);
            return obj;
        },
        hideShowType(schedule, $schedule) {
            let t = this;
            let type = schedule.type || 'everyday';
            $schedule.find('.schedule-type-wrapper').hide();
            if(type == 'daysoftheweek') {
                $schedule.find('.daysoftheweek-wrapper').show();
            }
            else if(type == 'daysofthemonth') {
                $schedule.find('.daysofthemonth-wrapper').show();
            }
            else if(type == 'daysoftheyear') {
                $schedule.find('.daysoftheyear-wrapper').show();
            }
            else if(type == 'monthsoftheyear') {
                $schedule.find('.monthsoftheyear-wrapper').show();
            }
            else if(type == 'holidays') {
                $schedule.find('.holidays-wrapper').show();
            }
        },
        setHolidayProviderOptions($schedule, schedule, Field) {
            let $providerSelect = $schedule.find('.holiday-provider-select');
            let providerMap = $providerSelect.data('provider-map');
            if(typeof providerMap === 'string') {
                providerMap = JSON.parse(providerMap);
                $providerSelect.data('provider-map', providerMap);
            }
            let provider = schedule.holidayProvider;
            let providerOpts = providerMap[provider || 'USA'];
            let selectProvider = Sprowt.selectValue($providerSelect);
            if(selectProvider !== provider) {
                $providerSelect.val(provider);
                $providerSelect.trigger('change.select2');
            }


            let $templateScript;
            $templateScript = Field.$field.find('.holidayfield-template');

            $schedule.find('.holiday-select-wrapper').html('');
            let days = schedule.days || [];
            $.each(providerOpts, function(pkey, pname) {
                let $checkTmp = Sprowt.getTemplate($templateScript);
                let $checkInput = $checkTmp.find('input');
                let $checkLabel = $checkTmp.find('label');
                $checkInput.attr('value', pkey);
                $checkLabel.html(pname);
                if(days.indexOf(pkey) >= 0) {
                    $checkInput.prop('checked', true);
                }
                $schedule.find('.holiday-select-wrapper').append($checkTmp);
            });

            return $schedule;
        },
        setInputs($tmp, schedule, Field) {
            let t = this;
            let defaultScheduleVals = {
                timezone: $tmp.find('.timezone-field').attr('data-default-timezone'),
                type: 'everyday',
                time: 'allDay'
            };
            schedule = $.extend(defaultScheduleVals, schedule);
            t.hideShowType(schedule, $tmp);
            $tmp.find('.timezone-field').val(schedule.timezone);
            $tmp.find('.schedule-type').val(schedule.type);
            if(schedule.type === 'daysoftheweek') {
                $tmp.find('.day-option.daysoftheweek').show();
                let $checks = $tmp.find('.daysoftheweek-checkbox');
                $checks.each(function() {
                    let $check = $(this);
                    $check.prop('checked', false);
                    let checkDay = $(this).val();
                    if(schedule.days.indexOf(checkDay) >= 0) {
                        $check.prop('checked', true);
                    }
                });
            }
            else if(schedule.type === 'daysofthemonth') {
                $tmp.find('.day-option.daysofthemonth').show();
                $tmp.find('.daysofthemonth-select').val(schedule.days);
            }
            else if(schedule.type === 'daysoftheyear') {
                $tmp.find('.day-option.daysoftheyear').show();
                $.each(schedule.days, function(idx, monthDay){
                    let $month = t.getMonthTemplate(monthDay, Field.$field);
                    $tmp.find('.month-list').append($month);
                });
            }
            else if(schedule.type === 'monthsoftheyear') {
                $tmp.find('.day-option.monthsoftheyear').show();
                let $checks = $tmp.find('.monthsoftheyear-checkbox');
                $checks.each(function() {
                    let $check = $(this);
                    $check.prop('checked', false);
                    let checkDay = $(this).val();
                    if(schedule.days.indexOf(checkDay) >= 0) {
                        $check.prop('checked', true);
                    }
                });
            }
            else if(schedule.type === 'holidays') {
                schedule.holidayProvider = schedule.holidayProvider || 'USA';
                let $providerSelect = $tmp.find('.holiday-provider-select');
                $providerSelect.val(schedule.holidayProvider);
                $providerSelect.trigger('change.select2');
                $tmp = t.setHolidayProviderOptions($tmp, schedule, Field);
            }
            let time = schedule.time;
            if(time === 'allDay') {
                $tmp.find('.time-all-check').prop('checked', true);
            }
            else {
                $tmp.find('.time-all-check').prop('checked', false);
                $tmp.find('.time-range-wrap').show();
                $tmp.find('.time-from').val(schedule.time.from);
                $tmp.find('.time-to').val(schedule.time.to);
            }
            if($tmp.find('.negate-radio').length > 0) {
               if(!!schedule.negate) {
                   $tmp.find('.negate-radio[value="1"]').prop('checked', true);
               }
               else {
                   $tmp.find('.negate-radio[value="0"]').prop('checked', true);
               }
            }
            t.select2($tmp);
            $tmp.on('templateFieldAppended', function() {
                t.updateSummary($tmp, schedule);
            });
            return $tmp;
        }
    };

})(jQuery, Drupal, Sprowt, once);
