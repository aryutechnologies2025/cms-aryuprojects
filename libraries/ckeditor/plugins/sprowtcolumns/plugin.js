/**
 * Wrap text in button class to pick up button styling
 */
(function ($, Drupal, CKEDITOR) {
  // Register the plugin within the editor.
  CKEDITOR.plugins.add('sprowtcolumns', {
    // Register the icons. They must match command names.
    icons: 'sprowtcolumns',
    // The plugin initialization logic goes inside this method.
    init: function (editor) {
      // Define the editor command that inserts a button.
      editor.addCommand('sprowtcolumns', {
        // Define the function that will be fired when the command is executed.
        exec: function (editor) {
		  // Get Selected
		  var selection = editor.getSelection();
		  var root = selection.root;
		  console.log(root);
		  // Get Selected HTML
		  if (selection) {
            selectedHtml = getSelectionHtml(selection);
          }
		  
		  //var selectedParent = selection.getParents();
		  
		  // Get Selected Text
		  // var selected_text = editor.getSelection().getSelectedText();
		  // Wrap selectedHtml
		  editor.insertHtml('<div class="two-column">' + selectedHtml + '</div>');
		  // Make span
		  // var newElement = new CKEDITOR.dom.element("div");
		  // // Set attributes
		  // newElement.setAttributes({class: 'two-column'});
		  // // Set text to element
		  // newElement.setText(selected_text);
		  // // Add HTML Element
		  // editor.insertElement(newElement);
        }
      });
      // Create the toolbar button that executes the above command.
      editor.ui.addButton('sprowtcolumns', {
        label: 'Two Columns Bulleted List',
        command: 'sprowtcolumns',
        toolbar: 'insert'
      });
    }
  });
  
  /**
   Get HTML of a selection.
   */
  function getSelectionHtml(selection) {
    var ranges = selection.getRanges();
    var html = '';
    for (var i = 0; i < ranges.length; i++) {
      var content = ranges[i].extractContents();
      html += content.getHtml();
    }
    return html;
  }
})(jQuery, Drupal, CKEDITOR);