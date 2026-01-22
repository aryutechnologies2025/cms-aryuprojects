import { Plugin } from 'ckeditor5/src/core';
import SprowtColumnsEditing from "./sprowtColumnsEditing";
import SprowtColumnsUI from "./sprowtColumnsUi";
export default class sprowtColumns extends Plugin {
    // Note that SprowtColumns and SprowtColumnsUI also extend `Plugin`, but these
    // are not seen as individual plugins by CKEditor 5. CKEditor 5 will only
    // discover the plugins explicitly exported in index.js.

    static get pluginName() {
        return 'sprowtColumns';
    }

    static get requires() {
        return [SprowtColumnsEditing, SprowtColumnsUI];
    }
}
