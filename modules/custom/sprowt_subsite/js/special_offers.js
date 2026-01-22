(function ($, Drupal, drupalSettings, once){

    Drupal.behaviors.subsiteSpecialOffers = {
        attach(context, settings) {
            let subsiteNid = $('body').data('subsite-nid');
            let subsiteSpecialOfferFormUri;
            if(subsiteNid) {
                let map = settings['subsiteSpecialOfferMap'] || {};
                subsiteSpecialOfferFormUri = map[subsiteNid] || null;
                //console.log(subsiteSpecialOfferFormUri);
            }
            if(subsiteSpecialOfferFormUri) {
                let updateLink = function($link) {
                    let domain = window.location.protocol + '//' + window.location.host;
                    let originalHref = $link.attr('href');
                    let originalUrl;
                    if(originalHref.indexOf('http') === 0
                        || originalHref.indexOf('//') === 0
                    ) {
                        originalUrl = new URL(originalHref);
                    }
                    else {
                        originalUrl = new URL(originalHref, domain);
                    }
                    let newUrl = new URL(subsiteSpecialOfferFormUri, domain);
                    originalUrl.searchParams.forEach(function(val, key) {
                        newUrl.searchParams.set(key, val);
                    });
                    if(originalUrl.hash) {
                        newUrl.hash = originalUrl.hash;
                    }
                    $link.attr('href', newUrl.toString().replace(domain, ''));
                };

                $(once('subsiteSpecialOffers', '.node--type-special-offer', context)).each(function() {
                    let $wrap = $(this).closest('.article-wrapper');
                    let $specialOffer = $(this);
                    $wrap.find('a').each(function() {
                        let $link = $(this);
                        if($link.hasClass('special-offer-overlay-link')
                            || $link.hasClass('special-offer-link')
                        ) {
                            updateLink($link);
                        }
                    });
                });

                $(once('subsiteServiceSpecialOffers', '.service-special-offer', context)).each(function() {
                    let $wrap = $(this).find('.article-wrapper');
                    let $specialOffer = $(this);
                    $wrap.find('a').each(function() {
                        let $link = $(this);
                        if($link.hasClass('special-offer-overlay-link')
                            || $link.hasClass('special-offer-link')
                        ) {
                            updateLink($link);
                        }
                    });
                });
            }
        }
    };

})(jQuery, Drupal, drupalSettings, once);
