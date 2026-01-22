/**
 * @file defines InsertSprowtColumnsCommand, which is executed when the
 * toolbar button is pressed.
 *
 * copied roughly from the blockquote command:
 * https://github.com/ckeditor/ckeditor5/blob/d878f6348fc2ab3647da8347cdd39bbab35616a0/packages/ckeditor5-block-quote/src/blockquotecommand.ts
 *
 */

import { Command } from 'ckeditor5/src/core';
import { first } from 'ckeditor5/src/utils';

export default class InsertSprowtColumnsCommand extends Command {

    _name = 'sprowtColumns';

    execute() {
        const t = this;
        const editor = this.editor;
        const model = this.editor.model;
        const schema = model.schema;
        const value = this.value;

        model.change((writer) => {
            let selection = editor.model.document.selection;
            let blocks = Array.from(selection.getSelectedBlocks());
            if(value) {
                this._remove(writer, blocks.filter(t._findElement.bind(t)));
            }
            else {
                let wrapBlocks = blocks.filter(block => {
                    return t._findElement(block) || t._canBeWrapped(schema, block);
                });
                t._apply(writer, wrapBlocks);
            }
        });
    }

    refresh() {
        this.value = this._getValue(); //indicates that we are in the element
        this.isEnabled = this._checkEnabled();
    }

    _apply(writer, blocks) {
        let elementsToMerge = [];
        let ranges = this._getRanges(writer, blocks);
        let t = this;
        //Wrap all groups of block. Iterate in the reverse order to not break follo
        ranges.reverse().forEach(range => {
            let el = t._findElement(range.start);
            if(!el) {
                el = writer.createElement(t._name);
                writer.wrap(range, el);
            }
            elementsToMerge.push(el);
        });

        // Merge subsequent elements. Reverse the order again because this time we want to go through
        // the elements in the source order (due to how merge works – it moves the right element's content
        // to the first element and removes the right one. Since we may need to merge a couple of subsequent elements
        // we want to keep the reference to the first (furthest left) one.

        elementsToMerge.reverse().reduce((current, next) => {
            if(current.nextSibling === next) {
                writer.merge(writer.createPositionAfter(current));
                return current;
            }
            return next;
        });
    }

    _remove(writer, blocks) {
        //Iterate in the reverse order to not break followi
        this._getRanges(writer, blocks).reverse().forEach(range => {
            if ( range.start.isAtStart && range.end.isAtEnd ) {
                writer.unwrap(range.start.parent);

                return;
            }

            // The group of blocks are at the beginning of the element so let's move them left (out of the element).
            if ( range.start.isAtStart ) {
                const positionBefore = writer.createPositionBefore( range.start.parent);

                writer.move( range, positionBefore );

                return;
            }

            // The blocks are in the middle of an element so we need to split the element after the last block
            // so we move the items there.
            if ( !range.end.isAtEnd ) {
                writer.split( range.end );
            }

            // Now we are sure that groupRange.end.isAtEnd is true, so let's move the blocks right.

            const positionAfter = writer.createPositionAfter( range.end.parent);

            writer.move(range, positionAfter);
        });
    }

    _findElement(elementOrPosition) {
        return elementOrPosition.parent.name === this._name ? elementOrPosition.parent : null;
    }

    _getRanges(writer, blocks) {
        let startPosition;
        let i = 0;
        const ranges = [];
        while ( i < blocks.length ) {
            const block = blocks[ i ];
            const nextBlock = blocks[ i + 1 ];

            if ( !startPosition ) {
                startPosition = writer.createPositionBefore( block );
            }

            if ( !nextBlock || block.nextSibling != nextBlock ) {
                ranges.push( writer.createRange( startPosition, writer.createPositionAfter( block ) ) );
                startPosition = null;
            }

            i++;
        }

        return ranges;
    }

    _getValue() {

        const selection = this.editor.model.document.selection;

        const firstBlock = first( selection.getSelectedBlocks() );
        // In the current implementation, the element must be an immediate parent of a block element.
        return !!( firstBlock && this._findElement(firstBlock) );
    }

    _checkEnabled() {
        if ( this.value ) {
            return true;
        }

        const selection = this.editor.model.document.selection;
        const schema = this.editor.model.schema;

        const firstBlock = first( selection.getSelectedBlocks() );

        if ( !firstBlock ) {
            return false;
        }

        return this._canBeWrapped(schema, firstBlock);
    }

    _canBeWrapped(schema, block) {
        let isAllowed = schema.checkChild(block.parent, this._name);
        let isAllowedInElement = schema.checkChild(['$root', this._name], block);
        return isAllowed && isAllowedInElement;
    }
}
