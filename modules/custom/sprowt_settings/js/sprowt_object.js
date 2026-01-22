(function($){
    window.Sprowt = {
        uniqueIdCounter: 0,
        uniqueIds: [],
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
        getTemplate(scriptIdOrObj) {
            let t = this;
            let $script;
            if(typeof scriptIdOrObj === 'string') {
                $script = $('#' + scriptIdOrObj);
            }
            else {
                $script = scriptIdOrObj;
            }
            let html = $script.html();
            let $template = $(html);
            $template.removeAttr('id', '').removeAttr('data-drupal-selector', '');
            $template.filter(function(){
                return $(this).attr('data-drupal-selector');
            }).removeAttr('data-drupal-selector');
            let idMap = {};
            $template.find('*').filter(function() {
                return $(this).attr('id');
            }).each(function() {
                let $element = $(this);
                let id = t.uniqueId($element.attr('id'));
                idMap[$element.attr('id')] = id;
                $element.attr('id', id);
            });
            $template.find('*').filter(function() {
                return $(this).attr('for');
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
        makeJsRequired($field) {
            $field.attr('required', 'required');
            let $formItem = $field.closest('.form-item');
            let $label = $formItem.find('.form-item__label');
            $label.addClass('form-required');
        },
        makeJsUnrequired($field) {
            $field.removeAttr('required');
            let $formItem = $field.closest('.form-item');
            let $label = $formItem.find('.form-item__label');
            $label.removeClass('form-required');
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
                    data = data.shift() || {};
                    return data.id || null;
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
        button: function(text, type) {
            let $button = $('<button type="button" class="button"></button>');
            $button.append(text);
            switch(type) {
                case 'primary': {
                    $button.addClass('button--primary');
                    break;
                }
                case 'danger':
                case 'error': {
                    $button.addClass('button--danger');
                    break;
                }
            }
            return $button;
        },
        formatPhone: function(val, pattern) {
            if(!pattern) {
                pattern = '$1-$2-$3';
            }
            if(val) {
                let numVal = val.replace(/[^\d]/g, '').toString();
                if(!numVal.length) {
                    return val;
                }
                else if (numVal.length <= 3) {
                    let newVal = numVal.replace(/(\d{1,3})/, pattern.replace(/(.*\$1).*/, '$1'));
                    return newVal;
                }
                else if (numVal.length <= 6) {
                    let newVal = numVal.replace(/(\d{3})(\d{1,3})/, pattern.replace(/(.*\$2).*/, '$1'));
                    return newVal;
                }
                else {
                    let newVal = numVal.replace(/(\d{3})(\d{3})(\d{1,4}).*/, pattern);
                    return newVal;
                }
            }
            return val;
        },
        insertMessage: function(type, message) {
            let $region = $('.region-highlighted');
            if(!$region.length) {
                $region = $('<div class="region region-highlighted"></div>');
                $('main').prepend($region);
            }
            let $messagesLists = $region.find('.messages-list');
            let $messagesList;
            if($messagesLists.length) {
                $messagesLists.each(function() {
                    if(!$(this).hasClass('hidden')) {
                        $messagesList = $(this);
                    }
                });
            }
            if(!$messagesList) {
                $messagesList = $('<div data-drupal-messages="" class="messages-list"></div>');
            }
            if(!$region.find($messagesList).length) {
                $region.append($messagesList);
            }
            let $messageWrapper = $messagesList.find('.messages__wrapper');
            if(!$messageWrapper.length) {
                $messageWrapper = $('<div class="messages__wrapper"></div>');
                $messagesList.prepend($messageWrapper);
            }
            let $messagesItem = $messageWrapper.find('.messages.messages--'+type);
            if(!$messagesItem.length) {
                let itemTitle = type + '';
                itemTitle = itemTitle.charAt(0).toUpperCase() + itemTitle.slice(1);
                let $messagesItem = $(''+
                    '<div role="contentinfo" aria-labelledby="message-'+type+'-title" class="messages-list__item messages messages--'+type+'">\n' +
                    '    <div class="messages__header">\n' +
                    '         <h2 id="message-'+type+'-title" class="messages__title">'+itemTitle+' message</h2>\n' +
                    '    </div>\n' +
                    '    <button type="button" class="button button--dismiss" title="Dismiss"><span class="icon-close"></span>Close</button>\n' +
                    '     <div class="messages__content">'+message+'</div>\n' +
                    '</div>');
                $messageWrapper.append($messagesItem);
                $messagesItem.find('.button--dismiss').click(function(e) {
                    e.preventDefault();
                    $messagesItem.remove();
                });
            }
            else {
                let $messageContent = $messagesItem.find('.messages__content');
                let $messageText = $messageContent.html();
                if ($messageText) {
                    $messageText += '<br>';
                }
                $messageText += message;
                $messageContent.html($messageText);
            }
        },
        /**
         * Works like lodash get https://www.npmjs.com/package/lodash.get
         *
         * Source: https://github.com/you-dont-need/You-Dont-Need-Lodash-Underscore#_get
         * @param obj
         * @param path
         * @param defaultValue
         * @returns {undefined|*}
         */
        get: function(obj, path, defaultValue = undefined) {
            if(!obj) {
                return defaultValue;
            }
            if(typeof obj !== 'object') {
                return defaultValue;
            }
            if(typeof path !== 'string') {
                return defaultValue;
            }
            const travel = regexp =>
                String.prototype.split
                    .call(path, regexp)
                    .filter(Boolean)
                    .reduce((res, key) => (res !== null && res !== undefined ? res[key] : res), obj);
            const result = travel(/[,[\]]+?/) || travel(/[,[\].]+?/);
            return result === undefined || result === obj ? defaultValue : result;
        }
    };
})(jQuery);
