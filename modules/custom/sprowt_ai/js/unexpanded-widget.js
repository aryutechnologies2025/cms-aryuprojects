(function($, Drupal, once, Sprowt) {

    const UnexpandedWidgetPrompt = function($wrap) {
        this.$button = $wrap.find('.unexpanded-prompt-button');
        this.$field = $wrap;
        this.$promptField = $wrap.find('.unexpanded-prompt-text');
        this.events();
    };

    UnexpandedWidgetPrompt.prototype = $.extend(UnexpandedWidgetPrompt.prototype, {
        events() {
            let t = this;
            this.$field.on('savePrompt', function (e, prompt) {
                t.$promptField.val(prompt);
            });
            this.$field.on('insertResult', function (e, result) {
                t.insertContent(result);
            });
            this.$field.on('openUnexpandedForm', function() {
                t.openForm();
            });
        },
        getOptions() {
            let $optionsField = this.$field.find('.options-value');
            let val = $optionsField.val();
            if(typeof val === 'string') {
                val = JSON.parse(val);
            }
            return val;
        },
        fieldHasCkEditor($field) {
            if(!$field.is('textarea')) {
                return false;
            }
            return $field.closest('.form-textarea-wrapper').find('.ck').length > 0;
        },
        getCkEditorInstance($field) {
            if(!this.fieldHasCkEditor($field)) {
                return null;
            }
            let $ck = $field.closest('.form-textarea-wrapper').find('.ck');
            let $editable = $ck.find('.ck-editor__editable').first();
            return $editable[0].ckeditorInstance;
        },
        openForm() {
            let modalSettings = {
                url: '/sprowt-ai/unexpanded-prompt',
                dialogType: 'dialog',
                dialog: {
                    width: 1600,
                    height: 1300,
                    maxWidth: '95%',
                    maxHeight: '95%',
                    autoResize: false,
                    resizable: true,
                    draggable: true,
                    classes: {
                        'ui-dialog': 'unexpanded-prompt-dialog ui-corner-all ui-widget ui-widget-content ui-front ui-dialog-buttons ui-draggable ui-resizable'
                    }
                }
            };
            let modal = Drupal.ajax(modalSettings);
            modal.execute();
        },
        //from https://stackoverflow.com/questions/15458876/check-if-a-string-is-html-or-not
        isHTML(str) {
            var doc = new DOMParser().parseFromString(str, "text/html");
            return Array.from(doc.body.childNodes).some(node => node.nodeType === 1);
        },
        insertContent(content) {
            let options = this.getOptions();
            if(!options.insertSelector) {
                return;
            }
            let $insertField = $(options.insertSelector);
            if($insertField.length === 0) {
                return;
            }

            let t = this;
            $insertField.each(function() {
                let $field = $(this);
                if(!($field.is('input') || $field.is('textarea'))) {
                    return;
                }

                let hasCkEditor = t.fieldHasCkEditor($field);
                if(hasCkEditor) {
                    let ckEditor = t.getCkEditorInstance($field);
                    if (ckEditor) {
                        if(!t.isHTML(content)) {
                            content = content.replace(/\n/g, '<br>');
                        }
                        ckEditor.setData(content);
                    }
                }
                else {
                    $insertField.val(content);
                }
            });

        },
    });


    Drupal.behaviors.sprowt_ai_unexpanded_prompt = {
        alteredWidgets: [],
        attach: function (context, settings) {
            let t = this;
            $(once('sprowt_ai_unexpanded_prompt', '.unexpanded-prompt-button', context)).each(function() {
                let $button = $(this);
                let $wrap = $button.closest('.claude-prompt');
                let prompt = new UnexpandedWidgetPrompt($wrap);
                t.alteredWidgets.push(prompt);
            });
        }
    };

})(jQuery, Drupal, once, Sprowt);
