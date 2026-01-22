(function($, Drupal, once){

    /**
     * Most of this code is taken from: https://support.google.com/google-ads/answer/7012522
     */
    Drupal.behaviors.collectGclid = {
        getExpiryRecord(value) {
            let expiryPeriod = 90 * 24 * 60 * 60 * 1000; // 90 day expiry in milliseconds

            let expiryDate = new Date().getTime() + expiryPeriod;
            return {
                value: value,
                expiryDate: expiryDate
            };
        },
        collect() {
            let t = this;
            let url = new URL(window.location.href);
            let gclidParam = url.searchParams.get('gclid');
            let gclidRecord;
            window.GCLID = null;
            let gclsrcParam =  url.searchParams.get('gclsrc');
            let isGclsrcValid = !gclsrcParam || gclsrcParam.indexOf('aw') !== -1;
            if (gclidParam && isGclsrcValid) {
                gclidRecord = t.getExpiryRecord(gclidParam);
                window.localStorage.setItem('gclid', JSON.stringify(gclidRecord));
            }
            let storedRecord = window.localStorage.getItem('gclid');
            if (storedRecord) {
                storedRecord = JSON.parse(storedRecord);
            }
            let gclid = gclidRecord || storedRecord;
            let isGclidValid = gclid && new Date().getTime() < gclid.expiryDate;
            if (isGclidValid) {
                window.GCLID = gclid.value;
            }
        },
        attach: function (context, settings) {
            let t = this;
            $(once('collectGclid', 'body', context)).each(function() {
                t.collect();
            });
        }
    };
})(jQuery, Drupal, once);
