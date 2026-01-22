import { Plugin } from 'ckeditor5/src/core';
import SprowtButtonEditing from "./sprowtButtonEditing";
import SprowtButtonUi from "./sprowtButtonUi";

export default class sprowtButton extends Plugin {
    // Note that SprowtColumns and SprowtColumnsUI also extend `Plugin`, but these
    // are not seen as individual plugins by CKEditor 5. CKEditor 5 will only
    // discover the plugins explicitly exported in index.js.

    static get pluginName() {
        return 'sprowtButton';
    }

    static get requires() {
        return [SprowtButtonEditing, SprowtButtonUi];
    }
}
