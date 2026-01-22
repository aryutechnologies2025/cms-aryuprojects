/**
 * @file
 * A JavaScript file for the theme.
 *
 * In order for this JavaScript to be loaded on pages, see the instructions in
 * the README.txt next to this file.
 */

// JavaScript should be made compatible with libraries other than jQuery by
// wrapping it with an "anonymous closure". See:
// - https://drupal.org/node/1446420
// - http://www.adequatelygood.com/2010/3/JavaScript-Module-Pattern-In-Depth

(function ($) {
  "use strict";

  const checkConcernModal = document.querySelector('.concern-modal')
  if (checkConcernModal) {
      $('a.concern-link').featherlightGallery({
    	previousIcon: '&#9664;',     /* Code that is used as previous icon */
    	nextIcon: '&#9654;',         /* Code that is used as next icon */
    	galleryFadeIn: 100,          /* fadeIn speed when slide is loaded */
    	galleryFadeOut: 300          /* fadeOut speed before slide is loaded */
    });
  }

  //if (typeof featherlightGallery === "function") {
    // $('a.concern-link').featherlightGallery({
    // 	previousIcon: '&#9664;',     /* Code that is used as previous icon */
    // 	nextIcon: '&#9654;',         /* Code that is used as next icon */
    // 	galleryFadeIn: 100,          /* fadeIn speed when slide is loaded */
    // 	galleryFadeOut: 300          /* fadeOut speed before slide is loaded */
    // });
  //}

  //----------------------------------------------------------
  // FORM EXPAND
  //----------------------------------------------------------
  function initForm(form) {
    var firstChild = form.querySelector('.form-item');
    var triggers = firstChild.querySelectorAll('input[type=text], input[type=email], input[type=tel], textarea');
    for(var i = 0; i < triggers.length; ++i) {
      var trigger = triggers[i];
      trigger.addEventListener('focus', function(){
        form.classList.add('form-expanded');
      });
    }
  }

  var formExpands = document.querySelectorAll('[class*="block-webform---solution-page"]');
  for (var i = formExpands.length - 1; i >= 0; i--) {
    initForm(formExpands[i]);
  }


  //----------------------------------------------------------
  // MOBILE HEADER SLIDE IN/OUT - Hide and Show header on scroll down and up
  //----------------------------------------------------------
  var $didScroll;
  var $lastScrollTop = 0;
  var $navbarHeight = $("header.page-header").outerHeight();
  //console.log($navbarHeight);

  $(window).scroll(function(event) {
    $didScroll = true;
  });

  setInterval(function() {
    if ($didScroll) {
      hasScrolled();
      $didScroll = false;
    }
  }, 250);

  function hasScrolled() {
    var $st = $(window).scrollTop();
    var headerHeight = $("header.page-header").outerHeight();
    //console.log($st);

    // Make sure they scroll more than 5px
    if (Math.abs($lastScrollTop - $st) <= 5) {
      return;
    }

    // If they scrolled down and are past the navbar, add class .nav-up.
    // This is necessary so you never see what is "behind" the navbar.
    if ($st > $lastScrollTop && $st > $navbarHeight) {
      // Scroll Down
      $("header.page-header")
        .removeClass("nav-down")
        .addClass("nav-up")
        .css('top', -headerHeight);
      $(".button--ultimenu")
        .removeClass("nav-down")
        .addClass("nav-up");
    } else {
      // Scroll Up
      if ($st + $(window).height() < $(document).height()) {
        $("header.page-header")
          .removeClass("nav-up")
          .addClass("nav-down")
          .css('top', 'inherit');
        $(".button--ultimenu")
          .removeClass("nav-up")
          .addClass("nav-down");
      }
    }

    if ($st == 0) {
      $("header.page-header").removeClass("nav-down");
      $(".button--ultimenu").removeClass("navdown");
    }

    $lastScrollTop = $st;
  }

  //----------------------------------------------------------
  // MOBILE HEADER/FOOTER HEIGHT
  //----------------------------------------------------------
  $(setMobileHeaderHeight);
  window.onresize = setMobileHeaderHeight;

  function setMobileHeaderHeight() {
    var headerHeight = $("header.page-header").outerHeight();
    var footerHeight = $(".menu--mobile-footer-utility-menu").outerHeight();
    $('main[role="main"]').css('margin-top', headerHeight);
    $('.region-footer-layout [id*="footer-bottom"] .layout-container-wrap').css('padding-bottom', footerHeight);
  }

  //----------------------------------------------------------
  // BEFORE/AFTER IMAGE SLIDER
  //----------------------------------------------------------
  const beforeaAfterContainer = document.querySelectorAll('.beforeafter-container');
  beforeaAfterContainer.forEach(function(beforeafter) {
    beforeafter.querySelector('.baslider').addEventListener('input', (e) => {
      beforeafter.style.setProperty('--position', `${e.target.value}%`);
    });
  });

  if(beforeaAfterContainer.length > 0) {
    const baObserver = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('animate');
        }
      });
    });
    baObserver.observe(document.querySelector('.beforeafter-container'));
  }

  //----------------------------------------------------------
  // MOBILE HEADER MENU
  //----------------------------------------------------------
  Drupal.behaviors.mobileMenuToggle = {
    attach: function (context, settings) {
      // Open Mobile Menu Function
      function openMobileMenu() {
        $(".navbar-toggle").toggleClass("open");
        $(".mobile-header-menu").toggleClass("open");
        $('body').toggleClass("blur");
      }

      // Close Mobile Menu Function
      function closeMobileMenu() {
        $(".navbar-toggle").removeClass("open");
        $(".mobile-header-menu").removeClass("open");
        $('body').removeClass("blur");
      }

      // Toggle Mobile Menu
      $("nav.menu--main, nav.source-menu--main").on("click", ".navbar-toggle", function(e) {
        e.preventDefault();
        openMobileMenu();
      });

      $("nav.menu--careers-menu").on("click", ".navbar-toggle", function(e) {
        e.preventDefault();
        openMobileMenu();
      });

      $(".open-menu, .open-mobile-menu").on("click", function(e) {
        e.preventDefault();
        openMobileMenu();
      });

      var $mobileWindowSize = 1280; //viewport window size where mobile menu begins
      // If the user resizes the browser and the mobile menu is open - then close the mobile menu
      $(window).resize(function() {
        $("body").removeClass("is-ultimenu-expanded");
        closeMobileMenu();
      });

      $(".mobile-menu-close, .overlay").on("click", function(e) {
        closeMobileMenu();
      });

      $(once("cloneParentLink", [
        '.mobile-header-menu nav.menu--main > ul.menu > li.menu-item--expanded > a',
        '.mobile-header-menu nav.menu--main > ul.menu > li.menu-item--expanded > ul.menu > li.menu-item--expanded > a',
        '.mobile-header-menu nav.source-menu--main > ul.menu > li.menu-item--expanded > a',
        '.mobile-header-menu nav.source-menu--main > ul.menu > li.menu-item--expanded > ul.menu > li.menu-item--expanded > a'
      ].join(','))).each(function() {
        if ($(this).attr('href')) {
          if ($(this).is('[data-desc]')) {
            $(this).clone().text($(this).data('desc')).prependTo($(this).next()).wrap('<li class="menu-item" />');
          } else {
            $(this).clone().prependTo($(this).next()).wrap('<li class="menu-item" />');
          }
        }
      });

      // Updated to not include 4th level menu items
      $(once("expandSubmenu", [
        '.mobile-header-menu .menu--main > ul.menu > li.menu-item--expanded > a',
        '.mobile-header-menu .menu--main > ul.menu > li.menu-item--expanded > ul.menu > li.menu-item--expanded > a',
        '.mobile-header-menu .source-menu--main > ul.menu > li.menu-item--expanded > a',
        '.mobile-header-menu .source-menu--main > ul.menu > li.menu-item--expanded > ul.menu > li.menu-item--expanded > a'
      ].join(','))).each(function() {
        $(this).on("click", function(e) {
          e.preventDefault();
          $(this).next("ul.menu").slideToggle().parent().toggleClass("open");
          // Close one dropdown when selecting another
          $(this).parent().siblings(".menu-item--expanded").removeClass("open").find("> ul.menu").slideUp();
          e.stopPropagation();
        });
      });
    }
  };

  // extended service areas menu
Drupal.behaviors.menuExtended = {
  attach: function (context, settings) {

    $(once('submenu', 'header.page-header nav.menu--main[class*="menu-style-"] ul.menu li.dropdown-menu.service-areas > ul.menu > li > ul.menu, header.page-header nav.menu--main[class*="menu-style-"] ul.menu li.dropdown-menu.locations > ul.menu > li > ul.menu', context)).each(function() {

      var $county = $('<span class="county">' + $(this).parent().children().first().text() + '</span>');

      $(this).prepend($county);
    });
  }
};



  // zip finder utility
  Drupal.behaviors.zipFinderUtility = {
    attach: function (context, settings) {
      let $zipBlock = $('header.page-header .block--type-zip-code-finder');
      let $zipMenu = $('.menu--utility-zip-finder');
      let $zipLink = $('.menu--utility-zip-finder a');
      let $utilityItemBranch = $('.menu--utility-menu > ul.menu > li[class*="location"]');
      let $mobileMenu = $('.mobile-header-menu nav[class*="menu--utility-menu"]');
      let $zipLinkMobile = $('.mobile-header-menu nav.menu--utility-menu>ul.menu > li[class*="location"] a');
      let $zipClose = $('.block--type-zip-code-finder .close-button');
      let $zipMobileClose = $('.block-zip-code-finder .close-button');
      let $headerBlockZipcode = $('.block--type-header[class*="zipcode"]');
      let $zipOverlay = $('header.page-header .block--type-zip-code-finder .overlay');
      let $headerThree = $('#header-3-nofrills');

      // tablet and desktop functionality
      $zipLink.on('click', function (e) {
        e.preventDefault();
        $zipMenu.append($zipBlock);
        $zipBlock.toggleClass('show');
        $headerThree.toggleClass('behind');
      });

      //zip overlay
      $zipClose.on("click", function () {
        $zipBlock.removeClass('show');
      });
      $zipOverlay.on("click", function () {
        $zipBlock.removeClass('show');
      });

      // mobile functionality
      $zipLinkMobile.on('click', function (e) {
        e.preventDefault();
        $mobileMenu.append($zipBlock);
        $zipBlock.addClass('slide-in');
        $zipBlock.removeClass('slide-out');
      });

      $zipMobileClose.on('click', function () {
        $zipBlock.removeClass('slide-in');
        $zipBlock.addClass('slide-out');
      });

      // if zipcode finder header block exists show the mobile zip finder link and remove in tablet and desktop
      if ($headerBlockZipcode.length) {
        $utilityItemBranch.addClass('show-mobile');
      }
    }
  }




  let elementTypes = [
    $('form.webform-submission-form input[type="text"]'),
    $('form.webform-submission-form input[type="email"]'),
    $('form.webform-submission-form input[type="tel"]')
  ];

  $.each(elementTypes, function(eidx, $elementType) {
    $elementType.each(function() {
      let $element = $(this);
      var checkElement = function() {
        if ($element.val() === "") {
          $element.parent().removeClass("filled");
        } else {
          $element.parent().addClass("filled");
        }
      };
      checkElement();
      $element.change(function() {
        if (!$element.is(":focus")) {
          checkElement();
        }
      });

      $element.focus(function() {
        $element.parent().addClass("filled");
      });
      $element.focusout(function() {
        if ($element.val() === "") {
          $element.parent().removeClass("filled");
        }
      });
    });
  });

  // Section Divider (add class to previous block)
  $(".block-inline-blockdivider").each(function() {
    $(this).prev(".block").addClass("has-divider");
  });

  // Tabbed Content Section Elastic Tabs
  $(".paragraph--type--tabbed-content").each(function() {
    var slidesection = $(this);
    var slidetab = $(this).find(".slide-tabs");
    var slideselector = slidetab.find(".slide-selector");
    var activeItem = slidetab.find("a").first();
    var activeWidth = activeItem.innerWidth();
    slideselector.css({
      left: activeItem.position().left + "px",
      top: activeItem.position().top + "px",
      width: activeWidth + "px"
    });

    slidetab.on("click", "a", function(e) {
      e.preventDefault();
      slideselector.css({
        left: $(this).position().left + "px",
        top: $(this).position().top + "px",
        width: $(this).innerWidth() + "px"
      });

      var currentTab = "." + $(this).attr("data-tab");
      $(currentTab).each(function() {
        $(this)
          .siblings()
          .removeClass("active fade-in");
        $(this).addClass("active fade-in");
        var currentHeight = $(this).outerHeight(true);
        $(this)
          .parent()
          .css("height", currentHeight);
      });
    });
  });

  $(".slide-default").each(function() {
    $(this).css("height", $(this).outerHeight(true));
  });

  $(window).resize(function() {
    $(".slide-default").css("height", "auto");
  });

  $(".compare-table-wrap").on(
    "click",
    ".paragraph--type--feature-category > .field--name-field-title",
    function(e) {
      e.preventDefault();
      $(this).toggleClass("closed");
      $(this)
        .next(".field--name-field-feature-items")
        .not(":animated")
        .slideToggle();
    }
  );

  // Slick Slider
  const checkSlickSlider = document.querySelectorAll('.slick-count-2, .slick-count-3, .slick-count-4, .slick-count-5, .slick-count-6, .slick-count-7, .slick-count-8, .slick-count-9, .slick-count-10, .slick-count-12, .slick-count-12')
  if (checkSlickSlider) {
    $('.slick-count-2[class*="teaser"] .view-content').slick({
      mobileFirst: true,
      accessibility: true,
      arrows: false,
      fade: false,
      dots: true,
      infinite: false,
      draggable: true,
      swipe: true,
      touchMove: true,
      useTransform: true,
      speed: 500,
      slidesToShow: 1,
      slidesToScroll: 1,
      responsive: [
        {
          breakpoint: 900,
          settings: {
            slidesToShow: 2,
            slidesToScroll: 2,
            infinite: true,
            dots: false,
            draggable: false,
            swipe: false,
            touchMove: false
          }
        },
        {
          breakpoint: 1023,
          settings: {
            slidesToShow: 2,
            slidesToScroll: 2,
            infinite: true,
            dots: false,
            draggable: false,
            swipe: false,
            touchMove: false
          }
        }
      ]
    });

    $('.slick-count-3[class*="teaser"] .view-content, .slick-count-4[class*="teaser"] .view-content, .slick-count-5[class*="teaser"] .view-content, .slick-count-6[class*="teaser"] .view-content, .slick-count-7[class*="teaser"] .view-content, .slick-count-8[class*="teaser"] .view-content, .slick-count-9[class*="teaser"] .view-content, .slick-count-10[class*="teaser"] .view-content, .slick-count-11[class*="teaser"] .view-content, .slick-count-12[class*="teaser"] .view-content').slick({
      mobileFirst: true,
      arrows: false,
      dots: true,
      infinite: false,
      speed: 500,
      responsive: [
        {
          breakpoint: 900,
          settings: {
            slidesToShow: 2,
            slidesToScroll: 2,
            infinite: true,
            arrows: true,
            dots: false,
            draggable: false,
            swipe: false,
            touchMove: false
          }
        },
        {
          breakpoint: 1023,
          settings: {
            mobileFirst: false,
            slidesToShow: 3,
            slidesToScroll: 3,
            infinite: true,
            arrows: true,
            dots: false,
            draggable: false,
            swipe: false,
            touchMove: false
          }
        }
      ]
    });
  }

  // Variable Width Slick Slider
  const checkVarSlickSlider = document.querySelectorAll('.brands-slider');
  if (checkVarSlickSlider) {
    $('.brands-slider .field--name-field-brands').slick({
      mobileFirst: true,
      dots: true,
      arrows: false,
      draggable: true,
      swipe: true,
      touchMove: true,
      infinite: true,
      speed: 100,
      variableWidth: true,
      infinite: true,
      swipeToSlide: true,
      responsive: [
        {
          breakpoint: 769,
          settings: 'unslick',
        }
      ]
    });
  }

  // Back To Top Link
  Drupal.behaviors.back_to_top = {
    attach: function(context) {
      $(window).scroll(function() {
        if ($(window).scrollTop() > 300) {
          $('#backtotop').addClass('show');
        } else {
          $('#backtotop').removeClass('show');
        }
      });
      $('#backtotop').on('click', function(e) {
        e.preventDefault();
        $('html, body').animate({scrollTop:0}, '300');
      });
    }
  };

  // Modal Block Links / Smooth Scroll for Anchor Links
  Drupal.behaviors.smooth_anchor_scroll = {
    attach: function(context) {
      $('a[href*="#"]').not('[href="#"]').not('[href="#0"]').click(function(event) {
        const targetId = $(this).attr('href');
        const $modal = $(targetId); // Find the modal with the matching ID

        // Check if the target is a .modal-block-modal
        if ($modal.length && $modal.hasClass('modal-block-modal')) {
          event.preventDefault();
          $modal.addClass('show');
          $('.modal-overlay').addClass('show');
          $('body > .dialog-off-canvas-main-canvas').addClass('blur');
        } else if (/^#/.test(targetId)) { //only smooth scroll for internal anchors
          console.log('offset error');
          event.preventDefault();
          $('html, body').stop(true, false).animate({
              scrollTop: $($(this).attr('href')).offset().top - 10,
            }, 850, 'swing'
          );
        }
      });
    }
  };

  //----------------------------------------------------------
  // ACCORDIONS
  //----------------------------------------------------------
  // Accordion Content Block
  $(".field--name-field-accordions").find(".field__item").on("click", ".field--name-field-heading", function() {
    $(this)
      .toggleClass("active")
      .next()
      .slideToggle();
  });

  // FAQs
  $(".view-faqs").find(".view-content").on("click", ".views-field-field-question", function() {
    $(this)
      .toggleClass("active")
      .next()
      .slideToggle();
  });

  //AWS Block
  $(".block-inline-blockareas-serviced.accordioned").find(".view-content").on("click", ".group-title", function() {
    $(this)
      .toggleClass("active")
      .next()
      .slideToggle();
  });
  $("block-inline-blockareas-serviced.accordioned .group-wrapper:first .group-title").each(function(){
    $(this).addClass('active');
  });

  //AWS Cities Serviced By Branch Block
  $(".block-inline-blockcities-serviced-by-branch.accordioned").find(".view-content").on("click", ".group-title", function() {
    $(this)
      .toggleClass("active")
      .next()
      .slideToggle();
  });
  $(".block-inline-blockcities-serviced-by-branch.accordioned .group-wrapper:first .group-title").each(function(){
    $(this).addClass('active');
  });

  //AWS Homepage Block
  $(".block-inline-blockareas-serviced-homepage.accordioned").find(".view-content").on("click", ".group-title", function() {
    $(this)
      .toggleClass("active")
      .next()
      .slideToggle();
  });
  $(".block-inline-blockareas-serviced-homepage.accordioned .group-wrapper:first .group-title").each(function(){
    $(this).addClass('active');
  });

  //AWS Service Page Block
  $(".block-inline-blockareas-serviced-service-page.accordioned").find(".view-content").on("click", ".group-title", function() {
    $(this)
      .toggleClass("active")
      .next()
      .slideToggle();
  });
  $(".block-inline-blockareas-serviced-service-page.accordioned .group-wrapper:first .group-title").each(function(){
    $(this).addClass('active');
  });
  
  //AWS Manual Block
  $(".block-inline-blockareas-serviced-manual.accordioned").find(".field--name-field-grouped-links").on("click", ".field--name-field-group-heading", function() {
    $(this)
      .toggleClass("active")
      .next()
      .slideToggle();
  });
  $(".block-inline-blockareas-serviced-manual.accordioned .field--name-field-grouped-links .field__item:first .field--name-field-group-heading").each(function(){
    $(this).addClass('active');
  });

  //Package Teaser Features
  $(".package-features").on("click", ".see-details", function() {
    $(this).parent().toggleClass("open");
    $(this).next().slideToggle();
  });

  function equalizeHeights(selector) {
    var maxHeight = 0;
    $(selector).each(function() {
      var currentHeight = $(this).height();
      if (currentHeight > maxHeight) {
        maxHeight = currentHeight;
      }
    });
    $(selector).height(maxHeight);
  }

  // Tocbot Heading
  $('.block-tocbot-block').each(function() {
    $(this).prepend('<div class="toc-heading">Table of Contents</div>');
  });

  if(Drupal.behaviors.select2) {
    // Select 2
    Drupal.behaviors.select2.settings.minimum_multiple = 0;
    Drupal.behaviors.select2.settings.minimum_single = 4;
  }

  Drupal.behaviors.jobListForm = {
    attach: function(context) {
      $('#job-list').each(function() {
        let $block = $(this);
        let $view = $block.find('.view');
        let $form = $view.find('.views-exposed-form');
        $(once('jobListForm', $form)).each(function() {
          let $selects = $form.find('select.form-select');
          $selects.each(function() {
            let $select = $(this);
            let $formItem = $select.closest('.form-item');
            let label = $formItem.find('label').text();
            $formItem.find('label').hide();
            if($select.hasClass('select2-hidden-accessible')) {
              $select.select2('destroy');
            }
            let select2options = Drupal.behaviors.select2.getElementOptions($select);
            if(label) {
              select2options.placeholder = label;
            }
            $select.select2(select2options);
          });
          let $clear = $form.find('input[data-drupal-selector="edit-reset"]');
          let $apply = $form.find('input.form-submit[value="Apply"]');
          $clear.click(function(e) {
            //make clear button use ajax
            e.preventDefault();
            $form.find('input[type="text"]').val('');
            $selects.val('');
            $selects.trigger('change');
            $apply.trigger('click');
          });

        });
      });
    }
  };

  $(document).ready(function() {
    // show admin links region if not empty
    let $adminLinks = $('.region-admin-links');
    let text = $adminLinks.find('.region-blocks-wrap').text().trim();
    if(text) {
      $adminLinks.show();
    }
    equalizeHeights('.block-inline-blockpackages .view-packages [class*="node--view-mode-alt"] .package-copy'); //Package Block Alt view-modes
  });

  $(window).resize(function() {
    var windowWidth = $(document).width();
    if(windowWidth < 900) {
      $('.block-inline-blockpackages .view-packages [class*="node--view-mode-alt"] .package-copy').each(function(i, obj) {
        $(this).css('height','initial');
      });
    }
    if(windowWidth >= 900) {
      equalizeHeights('.block-inline-blockpackages .view-packages [class*="node--view-mode-alt"] .package-copy'); //Package Block Alt view-modes
    }
  });

  // video thumbnail replace
  Drupal.behaviors.vidThumb = {
    attach: function(context) {
      let $defaultImage = $(once('vidThumbSwitch', '.colorbox-media-video img', context));
      let $thumb = $defaultImage.parents('.field--name-field-media-oembed-video').siblings('.field--name-field-video-thumbnail').find('img');

      if($thumb) {
        $defaultImage.each(function() {

          let $thumbSrc = $(this).parents('.field--name-field-media-oembed-video')
          .siblings('.field--name-field-video-thumbnail')
          .find('img')
          .attr('src');

          $(this).attr('src', $thumbSrc);
        });
      };
    }
  };

})(jQuery);

(function ($, Cookies) {
  Drupal.behaviors.modalBlockHandler = {
    attach: function (context, settings) {

      // For each modal block
      $('.block-inline-blockmodal-block', context).each(function () {
        const $block = $(this);

        // Move the block to the body
        $('body').append($block);

        // Extract the block-id- class for cookie name
        const blockIdClass = $block.attr('class').match(/block-id-\S+/);

        // Check if the modal overlay already exists and create it
        if ($('.modal-overlay').length === 0) {
          $('body').append('<div class="modal-overlay"></div>');
        }

        if (blockIdClass) {
          // Check if the cookie exists
          if (!Cookies.get(blockIdClass[0])) {
            // If the current block is visble show modal-overlay
            if ($block.hasClass('showonload')) {
              $block.addClass('show');
              $('.modal-overlay').addClass('show');
              $('body > .dialog-off-canvas-main-canvas').addClass('blur');
              return false; // breaks
            }
          }
        }
      });

      // Close Modal and add cookie
      $(document).on('click', '.modal-close, .modal-overlay', function () {
        $('.block-inline-blockmodal-block').each(function () {
          const $block = $(this);

          if ($block.hasClass('show')) {

            if ($block.hasClass('cookie')) {
              // Extract the block-id- class for cookie name
              const blockIdClass = $block.attr('class').match(/block-id-\S+/);

              if (blockIdClass) {
                // Get the expiration value from the data-expiration attribute
                const expiration = $block.data('expiration') || 7; // Default to 7 days if not set

                // Set a cookie named after the block-id- class
                Cookies.set(blockIdClass[0], true, { expires: expiration });
                console.log(`Cookie set for: ${blockIdClass[0]}`);
              }
            }

            // Remove 'show' class
            $block.removeClass('show');
            $('.modal-overlay').removeClass('show');
            $('body > .dialog-off-canvas-main-canvas').removeClass('blur');
          }
        });
      });
    }
  };
})(jQuery, Cookies);
