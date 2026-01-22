(function($, Drupal, once) {


    Drupal.behaviors.sprowtAdminOverrideDescriptionToggle = {
      attach: function(context) {
        $(once('sprowtAdminOverrideDescriptionToggle', '.help-icon__description-toggle', context)).each(function() {
            let $element = $(this);
            let id = $element.attr('id');
            let $description = $('[aria-labelledby="'+id+'"]');
            $element.attr('data-gin-tooltip', 'data-gin-tooltip');
            $element.attr('title', $description.html());
            Drupal.ginTooltip.init(context);
        });
      }
    };

})(jQuery, Drupal, once);
