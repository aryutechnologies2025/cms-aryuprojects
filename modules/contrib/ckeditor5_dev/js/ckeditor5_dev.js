(function (Drupal, once) {
  Drupal.behaviors.CKEditor5Inspector = {
    /**
     * Facilitates adding and removing inspectors.
     */
    attach() {
      // This is added in a Drupal behavior so it can be applied after the
      // Drupal.CKEditor5Instances Map() is initialized without having to change
      // loading weights.
      once('ckeditor5-inspector', 'body').forEach(() => {
        // Get the current set and delete functions from
        // Drupal.CKEditor5Instances so they can be called in their overrides.
        const oldSet = Drupal.CKEditor5Instances.set;
        const oldDelete = Drupal.CKEditor5Instances.delete;

        // Override set() so an inspector is added when it is called, then the
        // default functionality is invoked.
        Drupal.CKEditor5Instances.set = function (...args) {
          const [id, editor] = args;
          CKEditorInspector.attach({ [id]: editor });
          return oldSet.apply(Drupal.CKEditor5Instances, args);
        };

        // Override delete() so an inspector is detached when it is called,
        // then the default functionality is invoked.
        Drupal.CKEditor5Instances.delete = function (...args) {
          const [id] = args;
          CKEditorInspector.detach(id);
          return oldDelete.apply(Drupal.CKEditor5Instances, args);
        };
      });
    },
  };
})(Drupal, once);
