/**
 * Wrap text in button class to pick up button styling
 */
(function ($, Drupal, CKEDITOR) {
  // Register the plugin within the editor.
  CKEDITOR.plugins.add('sprowtbutton', {
    // Register the icons. They must match command names.
    icons: 'sprowtbutton',
    // The plugin initialization logic goes inside this method.
    init: function (editor) {
      // Define the editor command that inserts a button.
      editor.addCommand('sprowtbutton', {
        // Define the function that will be fired when the command is executed.
        exec: function (editor) {
		  // Get Selected Text
		  var selected_text = editor.getSelection().getSelectedText();
		  // Make span
		  var newElement = new CKEDITOR.dom.element("span");
		  // Set attributes
		  newElement.setAttributes({class: 'button'});
		  // Set text to element
		  newElement.setText(selected_text);
		  // Add HTML Element
		  editor.insertElement(newElement);
        }
      });
      // Create the toolbar button that executes the above command.
      editor.ui.addButton('sprowtbutton', {
        label: 'Insert Button',
        command: 'sprowtbutton',
        toolbar: 'insert'
      });
    }
  });
})(jQuery, Drupal, CKEDITOR);