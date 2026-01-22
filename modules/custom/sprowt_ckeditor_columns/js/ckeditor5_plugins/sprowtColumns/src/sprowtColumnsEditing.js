import { Plugin } from 'ckeditor5/src/core';
import { toWidget, toWidgetEditable } from 'ckeditor5/src/widget';
import { Widget } from 'ckeditor5/src/widget';
import InsertSprowtColumnsCommand from './insertSprowtColumnsCommand';

// cSpell:ignore simplebox insertsimpleboxcommand

/**
 * CKEditor 5 plugins do not work directly with the DOM. They are defined as
 * plugin-specific data models that are then converted to markup that
 * is inserted in the DOM.
 *
 * CKEditor 5 internally interacts with simpleBox as this model:
 * <simpleBox>
 *    <simpleBoxTitle></simpleBoxTitle>
 *    <simpleBoxDescription></simpleBoxDescription>
 * </simpleBox>
 *
 * Which is converted for the browser/user as this markup
 * <section class="simple-box">
 *   <h2 class="simple-box-title"></h1>
 *   <div class="simple-box-description"></div>
 * </section>
 *
 * This file has the logic for defining the simpleBox model, and for how it is
 * converted to standard DOM markup.
 */
export default class SprowtColumnsEditing extends Plugin {
    static get requires() {
        return [Widget];
    }

    init() {
        this._defineSchema();
        this._defineConverters();
        this.editor.commands.add(
            'insertSprowtColumns',
            new InsertSprowtColumnsCommand(this.editor),
        );
    }

    /*
     * This registers the structure that will be seen by CKEditor 5 as
     * <simpleBox>
     *    <simpleBoxTitle></simpleBoxTitle>
     *    <simpleBoxDescription></simpleBoxDescription>
     * </simpleBox>
     *
     * The logic in _defineConverters() will determine how this is converted to
     * markup.
     */
    _defineSchema() {
        // Schemas are registered via the central `editor` object.
        const schema = this.editor.model.schema;

        schema.register('sprowtColumns', {
            inheritAllFrom: '$container'
        });
    }

    /**
     * Converters determine how CKEditor 5 models are converted into markup and
     * vice-versa.
     */
    _defineConverters() {
        // Converters are registered via the central editor object.
        const { conversion } = this.editor;

        // Upcast Converters: determine how existing HTML is interpreted by the
        // editor. These trigger when an editor instance loads.
        //
        // If <div class="two-column"> is present in the existing markup
        // processed by CKEditor, then CKEditor recognizes and loads it as a
        // <sprowtColumns> model.
        conversion.for('upcast').elementToElement({
            model: 'sprowtColumns',
            view: {
                name: 'div',
                classes: 'two-column',
            },
        });

        // conversion.for( 'downcast' )
        //     .elementToElement( {
        //         model: 'sprowtColumns',
        //         view: {
        //             name: 'div',
        //             classes: 'two-column',
        //         }
        //     });

        // Data Downcast Converters: converts stored model data into HTML.
        // These trigger when content is saved.
        //
        // Instances of <sprowtColumns> are saved as
        // <div class="two-column">{{inner content}}</div>.
        conversion.for('dataDowncast').elementToElement({
            model: 'sprowtColumns',
            view: (modelItem, {writer}) => {
                return writer.createContainerElement('div', {class: 'two-column'});
            }
        });

        // Editing Downcast Converters. These render the content to the user for
        // editing, i.e. this determines what gets seen in the editor. These trigger
        // after the Data Upcast Converters, and are re-triggered any time there
        // are changes to any of the models' properties.
        // Convert the <sprowtColumns> model into an editable <div> widget.
        conversion.for('editingDowncast').elementToElement({
            model: 'sprowtColumns',
            view: (modelElement, { writer: viewWriter }) => {
                const div = viewWriter.createContainerElement('div', {
                    class: 'two-column',
                });
                return toWidgetEditable(div, viewWriter, { label: 'Two columns' });
            },
        });
    }
}
