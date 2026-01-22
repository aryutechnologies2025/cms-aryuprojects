(function($, Drupal){
    Drupal.behaviors.chatCodeEntity = {
        uniqueIdCounter: 0,
        uniqueIds: [],
        attach(context, settings) {
            let t = this;
            $(once('chatCodeEntity', 'body')).each(function() {
                t.ready();
            });
        },
        ready() {
            this.setEvents();
            this.refreshList();
            this.setErrors();
        },
        uniqueId: function(prefix) {
            prefix = prefix || '';
            let t = this;
            t.uniqueIdCounter = t.uniqueIdCounter || 0;
            ++t.uniqueIdCounter;
            t.uniqueIds = t.uniqueIds || [];
            let id = prefix + '--' + t.uniqueIdCounter;
            while(t.uniqueIds.indexOf(id) >= 0) {
                ++t.uniqueIdCounter;
                id = prefix + '--' + t.uniqueIdCounter;
            }
            t.uniqueIds.push(id);
            return id;
        },
        getTemplate(scriptId) {
            let t = this;
            let $script = $('#' + scriptId);
            let html = $script.html();
            let $template = $(html);
            $template.removeAttr('id', '').removeAttr('data-drupal-selector', '');
            $template.filter(function(){
                return $(this).attr('data-drupal-selector');
            }).removeAttr('data-drupal-selector');
            let idMap = {};
            $template.find('*').filter(function() {
                return $(this).attr('id')
            }).each(function() {
                let $element = $(this);
                let id = t.uniqueId($element.attr('id'));
                idMap[$element.attr('id')] = id;
                $element.attr('id', id);
            });
            $template.find('*').filter(function() {
                return $(this).attr('for')
            }).each(function() {
                let $element = $(this);
                let id = idMap[$element.attr('for')];
                if(!id) {
                    $element.removeAttr('for');
                }
                else {
                    $element.attr('for', id);
                }
            });

            $template.find('*').filter(function() {
                return $(this).attr('name');
            }).each(function() {
                $(this).attr('data-name', $(this).attr('name'));
                $(this).removeAttr('name');
            });

            return $template;
        },
        selectValue: function($element) {
            if(!$element.hasClass("select2-hidden-accessible")) {
                // select2 not initialized. return element value
                return $element.val();
            }
            else {
                let select2Data = $element.data('select2');
                let multiple = select2Data.options.options.multiple;
                let data = $element.select2('data');
                if(!multiple) {
                    data = data.shift();
                    return data.id;
                }
                else {
                    let ret = [];
                    $.each(data, function(i, datum) {
                        ret.push(datum.id);
                    });
                    return ret;
                }
            }
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
        getScheduleTemplate: function (schedule) {
            let t = this;
            $tmp = t.getTemplate('schedule-template');
            $tmp.attr('id', schedule.id);
            if(t.uniqueIds.indexOf(schedule.id) < 0) {
                t.uniqueIds.push(schedule.id);
            }
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
                    let $month = t.getMonthTemplate(monthDay);
                    $tmp.find('.month-list').append($month);
                });
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
            t.select2($tmp);
            return $tmp;
        },
        getMonthTemplate(monthDay) {
            let t = this;
            let $month = t.getTemplate('monthfield-template');
            let $monthField = $month.find('select[data-name="monthField[month]"]');
            let $dayField = $month.find('select[data-name="monthField[day]"]');
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
        getScheduleObject($schedule) {
            let t = this;
            let obj = {};
            obj.id = $schedule.attr('id');
            obj.timezone = $schedule.find('.timezone-field').val();
            obj.type = t.selectValue($schedule.find('.schedule-type'));
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
                days = t.selectValue($schedule.find('.daysofthemonth-select'));
                obj.days = days;
            }
            else if(obj.type === 'daysoftheyear') {
                days = [];
                $schedule.find('.monthfield-wrap').each(function() {
                    let $month = $(this);
                    let $monthField = $month.find('select[data-name="monthField[month]"]');
                    let $dayField = $month.find('select[data-name="monthField[day]"]');
                    let month = parseInt(t.selectValue($monthField));
                    let day = parseInt(t.selectValue($dayField));
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
            if($schedule.find('.time-all-check').prop('checked')) {
                obj.time = 'allDay';
            }
            else {
                obj.time = {};
                obj.time.from = $schedule.find('input.time-from')[0].value;
                obj.time.to = $schedule.find('input.time-to')[0].value;
            }
            return obj;
        },
        refreshValue: function() {
            let t = this;
            let schedules = {};
            let $schedules = $('#schedule-list').find('.schedule-wrap');
            $schedules.each(function() {
                let obj = t.getScheduleObject($(this));
                schedules[obj.id] = obj;
            });
            $('#schedule-value-field').val(JSON.stringify(schedules));
        },
        refreshList: function () {
            let t = this;
            let schedules = $('#schedule-value-field').val();
            if(typeof schedules === 'string') {
                schedules = JSON.parse(schedules);
            }
            let $list = $('#schedule-list');
            $list.html('');
            $.each(schedules, function (scheduleId, schedule) {
                let $tmp = t.getScheduleTemplate(schedule);
                $list.append($tmp);
            });
        },
        refreshSchedule: function(scheduleId) {
            this.refreshValue();
            let schedules = $('#schedule-value-field').val();
            if(typeof schedules === 'string') {
                schedules = JSON.parse(schedules);
            }
            let $oldSchedule = $('#'+scheduleId);
            let schedule = schedules[scheduleId];
            let $newSchedule = this.getScheduleTemplate(schedule);
            $oldSchedule.replaceWith($newSchedule);
        },
        setEvents: function () {
            let t = this;
            $(document).on('click', '#add-schedule', function(e) {
                e.preventDefault();
                if(!t.defaultTimeZone) {
                    t.defaultTimeZone = $('#default-time-zone').val() || 'America/New_York';
                }
                let schedule = {
                    id: t.uniqueId('schedule'),
                    timezone: t.defaultTimeZone,
                    type: 'everyday',
                    time: 'allDay'
                }
                let $schedule = t.getScheduleTemplate(schedule);
                $('#schedule-list').append($schedule);
                t.refreshValue();
            });
            $(document).on('click','.remove-schedule', function(e) {
                e.preventDefault();
                let $schedule = $(this).closest('.schedule-wrap');
                $schedule.remove();
                t.refreshValue();
            });
            $(document).on('change', '.timezone-field', function() {
                t.defaultTimeZone = t.selectValue($(this));
            });
            let changeProps = [
                '.schedule-type',
                '.time-all-check',
                '.daysoftheweek-checkbox',
                '.daysofthemonth-select',
                '.monthfield-wrap select[data-name="monthField[month]"]',
                '.monthfield-wrap select[data-name="monthField[day]"]'
            ];

            $.each(changeProps, function(cpi, changeProp) {
                $(document).on('change', changeProp, function() {
                    let scheduleId = $(this).closest('.schedule-wrap').attr('id');
                    t.refreshSchedule(scheduleId);
                });
            });

            $(document).on('keyup click', 'input.time-field', function () {
                let val = $(this).val();
                if(val) {
                    let scheduleId = $(this).closest('.schedule-wrap').attr('id');
                    //t.refreshSchedule(scheduleId);
                    t.refreshValue();
                    let schedules = $('#schedule-value-field').val();
                    if(typeof schedules === 'string') {
                        schedules = JSON.parse(schedules);
                    }
                    console.log(schedules);
                }
            });

            $(document).on('click', '.add-month', function (e) {
                e.preventDefault();
                let $schedule = $(this).closest('.schedule-wrap');
                let $tmp = t.getMonthTemplate('01-01');
                t.select2($tmp);
                $schedule.find('.month-list').append($tmp);
                t.refreshValue();
            });

            $(document).on('click', '.remove-month', function (e) {
                let scheduleId = $(this).closest('.schedule-wrap').attr('id');
                $(this).closest('.monthfield-wrap').remove();
                t.refreshSchedule(scheduleId);
            });
        },
        setErrors() {
            let errors = $('#schedule-error-field').val();
            if(errors) {
                if (typeof errors === 'string') {
                    errors = JSON.parse(errors);
                }
                $.each(errors, function(scheduleId, scheduleErrors) {
                    let $schedule = $('#' + scheduleId);
                    $.each(scheduleErrors, function(classQuery, errorMsg) {
                        $schedule.find(classQuery).addClass('error').addClass('has-error');
                    });
                });


            }
        }
    }
})(jQuery, Drupal)
