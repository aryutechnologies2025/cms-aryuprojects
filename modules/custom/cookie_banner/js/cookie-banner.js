(function($, Drupal, drupalSettings, Cookies) {

    Drupal.behaviors.cookieBanner = {
        expirationDate() {
            let settings = this.settings || {
                expires: 'never'
            };
            let now = new Date();
            let expires = new Date();
            switch(settings.expires) {
                case 'day':
                    expires.setDate(now.getDate() + 1);
                    break;
                case 'week':
                    expires.setDate(now.getDate() + 7);
                    break;
                case 'month':
                    expires.setMonth(now.getMonth() + 1);
                    break;
                case 'year':
                    expires.setFullYear(now.getFullYear() + 1);
                    break;
                default:
                    expires = new Date(3000, 0, 1);
                    break;
            }
            return expires;
        },
        initBanner() {
            let $banner = this.$banner;
            let settings = this.settings;
            let cookie = Cookies.get(settings.cookieName);
            let t = this;
            window.setTimeout(function() {
                if(!cookie) {
                    $banner.removeClass('accepted');
                }
            }, 500);
            $banner.on('click', '.close-button', function(e) {
                e.preventDefault();
                $banner.addClass('accepted');
            });
            $banner.on('click', '.accept-button', function(e) {
                e.preventDefault();
                Cookies.set(settings.cookieName, true, {
                    expires: t.expirationDate()
                });
                $banner.addClass('accepted');
            });
        },
        attach(context, settings) {
            let t = this;
            $(once('cookieBanner', '.cookie-banner', context)).each(function() {
                let $banner = $(this);
                t.$banner = $banner;
                t.settings = settings.cookie_banner;
                t.initBanner();
            });
        }
    };

})(jQuery, Drupal, drupalSettings, Cookies);
