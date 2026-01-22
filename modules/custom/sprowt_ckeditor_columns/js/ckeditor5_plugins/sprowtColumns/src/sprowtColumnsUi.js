import { Plugin } from 'ckeditor5/src/core';
import { ButtonView } from 'ckeditor5/src/ui';
import icon from '../../../../icons/sprowtcolumns.svg';

export default class SprowtColumnsUI extends Plugin {
    init() {
        const editor = this.editor;

        // This will register the simpleBox toolbar button.
        editor.ui.componentFactory.add('SprowtColumns', (locale) => {
            const command = editor.commands.get('insertSprowtColumns');
            const buttonView = new ButtonView(locale);

            // Create the toolbar button.
            buttonView.set({
                label: editor.t('Two Columns Bulleted List'),
                icon: icon,
                tooltip: true,
                isToggleable: true
            });

            // Bind the state of the button to the command.
            buttonView.bind('isOn', 'isEnabled').to(command, 'value', 'isEnabled');

            // Execute the command when the button is clicked (executed).
            this.listenTo(buttonView, 'execute', () =>
                editor.execute('insertSprowtColumns')
            );

            return buttonView;
        });
    }
}
