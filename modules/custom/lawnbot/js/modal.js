(function ($, Drupal) {
    $(document).ready(function() {
        var $modal = $('#instantquote-modal');
        if($modal.length) {
            var $iframe = $modal.find('.lawnbot-modal-iframe');
            var url = $iframe.data('src');
            console.log({
                modal: $modal,
                iframe: $iframe,
                url: url
            });
            $modal.modal({
                modalClass: "lawnbot-modal-modal",
                blockerClass: "lawnbot-modal-modal-blocker"
            });
            $iframe.attr('src', url);
        }
    });
})(jQuery, Drupal);
