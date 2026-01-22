(function($, Drupal, once, Sprowt) {

    const AlteredWidgetPrompt = function($button) {

        this.$button = $button;
        let $wrap = $button.closest('.form-wrapper');
        this.$wrap = $wrap;
        this.$buttonProgress = null;
        this.$formElement = $wrap.find('.form-element'+this.$button.attr('data-selector'));
        this.$promptField = $wrap.find('.prompt-field[data-prompt-field="'+this.$button.attr('data-widget-key')+'"]');
        this.$systemField = $wrap.find('.system-field[data-system-field="'+this.$button.attr('data-widget-key')+'"]');
        this.$referenceButton = $wrap.find('.reference-button[data-reference-button="'+this.$button.attr('data-widget-key')+'"]');
        this.events();
    };

    AlteredWidgetPrompt.prototype = $.extend(AlteredWidgetPrompt.prototype, {
        events() {
            let t = this;
            this.$button.on('click', function (e) {
                e.preventDefault();
                t.setProgressing();
                t.$referenceButton.click();
            });
            this.$formElement.on('updateEntityReferences', function (e, references, preprompt) {
                let options = t.$button.attr('data-options');
                if(typeof options === 'string') {
                    options = JSON.parse(options);
                }
                options.references = references;
                if(preprompt) {
                    options.preprompt = preprompt;
                }
                t.$button.attr('data-options', JSON.stringify(options));
                t.openForm();
            });
            this.$formElement.on('updateEntityReferencesError', function (e, msg) {
                t.openForm();
            });
            $(once('sprowt_ai_altered_widget_reference_button', 'body')).on('updateEntityReferencesError', function (e, msg) {
                console.error('Reference error', msg);
                t.setNotProgressing();
            });
            this.$formElement.on('input', function (e) {
                let value = $(this).val();
                console.log({value});
                t.$button.attr('data-widget-value', value);
            });
            window.setTimeout(function (){
                if(t.fieldHasCkEditor()) {
                    let editor = t.getCkEditorInstance();
                    if(editor) {
                        editor.model.document.on('change:data', function (evt, data) {
                            let value = editor.getData();
                            t.$button.attr('data-widget-value', value);
                        });
                    }
                    t.$wrap.find('select').on('change', function (e) {
                        let value = Sprowt.selectValue($(this));
                        let formArray = t.$button.attr('data-widget-value-element');
                        if(typeof formArray === 'string') {
                            formArray = JSON.parse(formArray);
                        }
                        formArray['#format'] = value;
                        t.$button.attr('data-widget-value-element', JSON.stringify(formArray));
                    });
                }
            }, 300);

            this.$formElement.on('savePrompt', function (e, prompt, systemId) {
                t.$promptField.val(prompt);
                t.$systemField.val(systemId);
                let buttonText = t.$button.text();
                if(buttonText !== 'Generate content') {
                    t.$button.text('Generate content');
                }
            });
            this.$formElement.on('insertResult', function (e, result) {
                t.insertContent(result);
            });
        },
        setProgressing() {
            if(this.$buttonProgress && this.$buttonProgress.length > 0) {
                this.setNotProgressing();
            }
            this.$buttonProgress = $(
                Drupal.theme('ajaxProgressThrobber', 'Opening...')
            );
            this.$button.after(this.$buttonProgress);
        },
        setNotProgressing() {
            if(this.$buttonProgress && this.$buttonProgress.length > 0) {
                this.$buttonProgress.remove();
                this.$buttonProgress = null;
            }
        },
        fieldHasCkEditor() {
            if(!this.$formElement.is('textarea')) {
                return false;
            }
            return this.$formElement.closest('.form-textarea-wrapper').find('.ck').length > 0;
        },
        getCkEditorInstance() {
            if(!this.fieldHasCkEditor()) {
                return null;
            }
            let $ck = this.$formElement.closest('.form-textarea-wrapper').find('.ck');
            let $editable = $ck.find('.ck-editor__editable').first();
            return $editable[0].ckeditorInstance;
        },
        openForm() {
            let t = this;
            let data = {};
            data.widgetKey = this.$button.attr('data-widget-key');
            data.selector = this.$button.attr('data-selector');
            data.options = this.$button.attr('data-options');
            if (typeof data.options === 'string') {
                data.options = JSON.parse(data.options);
            }
            data.entityUuid = this.$button.attr('data-entity-uuid');
            data.entityBundle = this.$button.attr('data-entity-bundle');
            data.entityType = this.$button.attr('data-entity-type');
            data.widget = this.$button.attr('data-widget-value-element');
            if (typeof data.widget === 'string') {
                data.widget = JSON.parse(data.widget);
            }
            data.widgetValue = this.$button.attr('data-widget-value');
            data.prompt = this.$promptField.val();
            data.systemId = this.$systemField.val();
            data.fieldProperty = this.$button.attr('data-field-property');
            let ajax = Drupal.ajax({
                url: '/sprowt-ai/widget-prompt-temp-store',
                submit: JSON.stringify(data),
            });
            $.ajax({
                url: '/sprowt-ai/widget-prompt-temp-store',
                type: 'POST',
                data: JSON.stringify(data),
                contentType: 'application/json',
            }).then(function(res) {
                let modalSettings = {
                    url: '/sprowt-ai/widget-prompt/' + data.widgetKey,
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
                            'ui-dialog': 'widget-prompt-dialog ui-corner-all ui-widget ui-widget-content ui-front ui-dialog-buttons ui-draggable ui-resizable'
                        }
                    }
                };
                let modal = Drupal.ajax(modalSettings);
                modal.execute().then(function(res) {
                    t.setNotProgressing();
                });
            });
        },
        //from https://stackoverflow.com/questions/15458876/check-if-a-string-is-html-or-not
        isHTML(str) {
            var doc = new DOMParser().parseFromString(str, "text/html");
            return Array.from(doc.body.childNodes).some(node => node.nodeType === 1);
        },
        insertContent(content) {
            console.log(content);
            let $insertField = this.$formElement;
            if($insertField.length === 0) {
                return;
            }

            let hasCkEditor = this.fieldHasCkEditor();
            if(hasCkEditor) {
                let ckEditor = this.getCkEditorInstance();
                if (ckEditor) {
                    if(!this.isHTML(content)) {
                        content = content.replace(/\n/g, '<br>');
                    }
                    ckEditor.setData(content);
                }
            }
            else {
                $insertField.val(content);
            }
        },
    });


    Drupal.behaviors.sprowt_ai_altered_widget = {
        alteredWidgets: [],
        attach: function (context, settings) {
            let t = this;
            $(once('sprowt_ai_altered_widget', '.sprowt-ai-generate-content-button')).each(function() {
                let $button = $(this);
                let prompt = new AlteredWidgetPrompt($button);
                t.alteredWidgets.push(prompt);
            });
        }
    };

})(jQuery, Drupal, once, Sprowt);
