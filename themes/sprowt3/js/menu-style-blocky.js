(function($,Drupal,once){

  Drupal.behaviors.sprowt3MenuStyleBlocky = {
    attach(context, settings) {
      $(once('sprowt3MenuStyleTpc', '.menu.menu-style-blocky', context)).each(function() {
        let $menu = $(this);
        console.log("Blocky menu style menu found!", $menu);
      });
    }
  };

})(jQuery, Drupal, once);







