(function($, Sprowt) {

    function makeRequired($input) {
        let $wrap = $input.closest('.form-item');
        let $label = $wrap.find('.form-item__label');
        $label.addClass('form-required');
        $input.addClass('required');
        $input.attr('required', 'required');
    }

    function unRequire($input) {
        let $wrap = $input.closest('.form-item');
        let $label = $wrap.find('.form-item__label');
        $label.removeClass('form-required');
        $input.removeClass('required');
        $input.removeAttr('required');
    }

    function disable($input) {
        let $wrap = $input.closest('.form-item');
        let $label = $wrap.find('.form-item__label');
        $wrap.addClass('form-item--disabled');
        $label.addClass('is-disabled');
        $input.attr('disabled', 'disabled');
    }

    function unDisable($input) {
        let $wrap = $input.closest('.form-item');
        let $label = $wrap.find('.form-item__label');
        $wrap.removeClass('form-item--disabled');
        $label.removeClass('is-disabled');
        $input.removeAttr('disabled');
    }

    function addBot(botObj) {
        let $tmp = Sprowt.getTemplate('botTemplate');
        if(botObj.id) {
            $tmp.data('new', false);
            $tmp.attr('data-id', botObj.id);
            $tmp.find('.edit-button').removeClass('hidden');
            $tmp.find('.edit-button').attr('href', '/admin/content/servicebot/'+botObj.id+'/edit');
            $tmp.find('.view-button').removeClass('hidden');
            $tmp.find('.view-button').attr('href', '/servicebot/'+botObj.id);
        }
        else {
            $tmp.data('new', true);
        }
        $tmp.find('.label').val(botObj.label || '');
        $tmp.find('.customerId').val(botObj.customerId || '');
        $tmp.find('.botId').val(botObj.botId || '');
        $tmp.find('.enabled').prop('checked', botObj.enabled || false);
        $('#bot-wrap').append($tmp);
        $tmp.find('input').each(function() {
            if($(this).data('required')) {
                makeRequired($(this));
            }
        });
    }

    function updateValue() {
        let val = [];
        $('#bot-wrap .bot').each(function() {
            let $bot = $(this);
            let botObj = {
                enabled: $bot.find('.enabled').prop('checked'),
                label: $bot.find('.label').val(),
                customerId: $bot.find('.customerId').val(),
                botId: $bot.find('.botId').val()
            };
            if(!$bot.data('new')) {
                botObj.id = $bot.data('id');
                botObj.isNew = false;
            }
            else {
                botObj.isNew = true;
            }
            val.push(botObj);
        });
        $('#serviceBots').val(JSON.stringify(val));
    }

    $(document).on('click', '#addBot', function(e) {
        e.preventDefault();
        addBot({
            enabled: true
        });
        updateValue();
    });

    $(document).on('click', '.remove-button', function(e) {
        e.preventDefault();
        let $bot = $(this).closest('.bot');
        if($bot.data('id')) {
            let toDelete = $('#toDelete').val();
            if(typeof toDelete === 'string') {
                toDelete = JSON.parse(toDelete);
            }
            toDelete.push($bot.data('id'));
            $('#toDelete').val(JSON.stringify(toDelete));
        }
        $bot.remove();
        updateValue();
    });

    $(document).on('change', '.check-action', function(e) {
        updateValue();
    });

    $(document).on('keyup', '.text-action', function(e) {
        updateValue();
    });

    $(document).ready(function() {
        let bots = $('#serviceBots').val();
        if(typeof bots === 'string') {
            bots = JSON.parse(bots);
        }
        $.each(bots, function(idx, bot) {
            addBot(bot);
        });
    });

})(jQuery, Sprowt);
