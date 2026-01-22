## CKEditor 5 Dev

This module adds the CKEditor5Inspector to any instances
of CKEditor 5 in Drupal.

It should **not be enabled** in production!

- Documentation: https://ckeditor.com/docs/ckeditor5/latest/framework/guides/development-tools.html
- Source code: https://github.com/ckeditor/ckeditor5-inspector

Additionally the editor instances are added to `window`
so that you may more easily call methods or check properties.

For example, to get the current dataDowncast that will be saved to the field:
`window['edit-field-FIELD_ID-value'].getData()`

If you're not sure what this field id is, check the "Editor Instance" dropdown
in the Inspector pane. There may be multiple instances on a page, such as a in
a content type with multiple `textarea` fields.
