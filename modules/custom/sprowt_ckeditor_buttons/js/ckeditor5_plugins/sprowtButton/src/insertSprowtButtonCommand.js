/**
 * @file defines InsertSimpleBoxCommand, which is executed when the simpleBox
 * toolbar button is pressed.
 */
// cSpell:ignore simpleboxediting

import { Command } from 'ckeditor5/src/core';

/**
 * based roughly on
 * https://github.com/ckeditor/ckeditor5/blob/2865c8b23e0188d14b3f3f8c0f9a41cbbb40cf5f/packages/ckeditor5-highlight/src/highlightcommand.ts
 */
export default class InsertSprowtButtonCommand extends Command {

    _name = 'sprowtButton';

    refresh() {
        const model = this.editor.model;
        const doc = model.document;

        this.value = doc.selection.getAttribute(this._name) || false;
        this.isEnabled = model.schema.checkAttributeInSelection(doc.selection, this._name);
    }

    execute() {
        const model = this.editor.model;
        const document = model.document;
        const selection = document.selection;
        const _name = this._name;
        const isApplied = this.value;

        model.change( writer => {
            if (selection.isCollapsed) {
                const position = selection.getFirstPosition();

                // When selection is inside text with `sprowtButton` attribute.
                if (selection.hasAttribute(_name)) {
                    writer.removeSelectionAttribute(_name);
                }
                else {
                    writer.setSelectionAttribute( _name, true);
                }
            } else {
                const ranges = model.schema.getValidRanges( selection.getRanges(), _name);

                for ( const range of ranges ) {
                    if (isApplied) {
                        writer.removeAttribute( _name, range );
                    }
                    else {
                        writer.setAttribute( _name, true, range );
                    }
                }
            }
        });
    }
}
