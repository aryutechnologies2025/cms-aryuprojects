import { Plugin } from 'ckeditor5/src/core';
import { toWidget, toWidgetEditable } from 'ckeditor5/src/widget';
import { Widget } from 'ckeditor5/src/widget';
import InsertSprowtButtonCommand from './insertSprowtButtonCommand';

// cSpell:ignore simplebox insertsimpleboxcommand

/**
 * CKEditor 5 plugins do not work directly with the DOM. They are defined as
 * plugin-specific data models that are then converted to markup that
 * is inserted in the DOM.
 *
 * Basing this roughly on the bold command
 * https://github.com/ckeditor/ckeditor5/blob/2865c8b23e0188d14b3f3f8c0f9a41cbbb40cf5f/packages/ckeditor5-basic-styles/src/bold/boldediting.ts
 */
export default class SprowtButtonEditing extends Plugin {

    _name = 'sprowtButton'

    static get requires() {
        return [Widget];
    }

    init() {
        this._defineSchema();
        this.editor.commands.add(
            'insertSprowtButton',
            new InsertSprowtButtonCommand(this.editor)
        );
    }

    /**
     * @private
     */
    _defineSchema() {
        // Schemas are registered via the central `editor` object.
        const schema = this.editor.model.schema;
        const editor = this.editor;
        schema.extend('$text', {
            allowAttributes: this._name
        });
        schema.setAttributeProperties( this._name, {
            isFormatting: true
        });

        editor.conversion.attributeToElement({
            model: this._name,
            view: {
                name: 'span',
                classes: 'button'
            }
        });
    }
}
