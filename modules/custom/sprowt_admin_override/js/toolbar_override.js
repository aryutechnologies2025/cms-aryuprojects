(function ($, Drupal, drupalSettings) {
    $(document).ready(function() {
        if(drupalSettings.environmentIndicator) {
            let $tray = $('#toolbar-item-administration-tray');
            if($tray.length) {
                let $envIndicator = $('#environment-indicator');
                let title = drupalSettings.environmentIndicator.name;
                if($envIndicator.length > 0) {
                    title = $envIndicator.html();
                }
                let $env = $('<div class="sprowt-hq-environment-indicator"><span class="environment-text">' + title + '</span></span></div>');
                $env.css({
                    backgroundColor: drupalSettings.environmentIndicator.bgColor,
                    color: drupalSettings.environmentIndicator.fgColor
                });
                $tray.append($env);
            }
        }
    });
})(jQuery, Drupal, drupalSettings);
