(function($){
    $(document).ready(function() {
        $('details.vertical-tabs__item').each(function() {
            let id = $(this).attr('id');
            let $tab = $('.vertical-tabs__menu-link[href="#'+id+'"]');
            let $desc = $(this).find('.claro-details__wrapper--vertical-tabs-item > .claro-details__content > .claro-details__description');
            if($desc.length > 0) {
                let text = $desc.text();
                $desc.hide();
                if(text) {
                    $tab.find('.vertical-tabs__menu-link-summary').html(text);
                }
            }
        });
    });
})(jQuery);
