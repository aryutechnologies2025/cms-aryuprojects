(function ($){
    let $actions = $('#edit-actions');

    let actionScroll = function() {
        let actionBottom = $actions.data('initialBottom');
        if(actionBottom) {
            let viewableWindow = $(window).scrollTop() + 53 + 72; //top of window plus toolbar plus header that sticks
            if (viewableWindow > actionBottom) {
                $actions.addClass('to-bottom');
            } else {
                $actions.removeClass('to-bottom');
            }
        }
    };
    $(document).ready(function() {
        let topOfActions = $actions.offset().top;
        let actionHeight = $actions.outerHeight();
        let actionBottom = topOfActions + actionHeight;
        $actions.data('initialBottom', actionBottom);
        actionScroll();
        $(window).scroll(function() {
            actionScroll();
        });
    });

})(jQuery);
