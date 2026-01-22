(function($, Drupal, once){

    const ClaudePrompt = function($field) {
      this.$field = $field;
      this.$textarea = $field.find('.claude-prompt-textarea');
      this.$generateButton = $field.find('.generate-content');
      this.$attachEntitiesButton = $field.find('.attach-entities');
      this.options = {};
      let optionsVal = $field.find('.options-value').val();
      if(typeof optionsVal === 'string') {
          optionsVal = JSON.parse(optionsVal);
      }
      this.options = optionsVal;
      this.events();
    };

    ClaudePrompt.prototype = $.extend(ClaudePrompt.prototype, {
        events() {
            let t = this;
            this.$field.on('insertExamples', (e, uuids) => {
                if(uuids && uuids.length > 0) {
                    t.insertExamples(uuids);
                    t.$attachEntitiesButton.trigger('mousedown');
                }
            });
            this.$field.on('insertContexts', (e, uuids, elementId, wrapInXml) => {
                if(uuids && uuids.length > 0) {
                    t.insertContexts(uuids, wrapInXml);
                    t.$attachEntitiesButton.trigger('mousedown');
                }
            });
            this.$field.on('mediaLibraryInsert', (e, ids) => {
                if(ids && ids.length > 0) {
                    t.insertDocuments(ids);
                    t.$attachEntitiesButton.trigger('mousedown');
                }
            });
            this.$textarea.on('focus blur change keyup click mouseup', (e) => {
                let cursorPos = t.getCursorPos(t.$textarea);
                t.$textarea.data('cursorPos', cursorPos);
            });

            this.$field.on('claude3GenerateContentResponse', (e, response) => {
                console.log('claude3GenerateContentResponse', {
                    response,
                    $generateButton: t.$generateButton,
                });
                this.$generateButton.trigger('click');
            });

            this.$field.on('insertContent', (e, content, options) => {
                t.options = options;
                t.insertContent(content);
            });
            this.$field.on('insertReferences', function(e, references) {
                t.insertReferences(references);
            });
            this.$field.on('openReferenceModal', function(e) {
                t.openReferenceModal();
            });
            this.$textarea.on('input', function(e) {
                t.addHighlightMarkup();
                t.scrollHighlightMarkup();
                window.setTimeout(function() {
                    t.$attachEntitiesButton.trigger('mousedown');
                },300);
            });
            this.$textarea.on('scroll', function(e) {
                t.scrollHighlightMarkup();
            });
            this.$field.on('claude3AttachEntities', function(e) {
                window.setTimeout(function() {
                    t.addHighlightMarkup();
                    t.scrollHighlightMarkup();
                },300);
            });
            this.$field.on('mouseover', '.attached-entities-wrap li a', function() {
                let $a = $(this);
                let uuid =  $a.attr('data-entity-uuid');
                t.$textarea.closest('div').find('mark[data-uuid="'+uuid+'"]').addClass('hover');
            });
            this.$field.on('mouseout','.attached-entities-wrap li a', function() {
                let $a = $(this);
                let uuid =  $a.attr('data-entity-uuid');
                t.$textarea.closest('div').find('mark[data-uuid="'+uuid+'"]').removeClass('hover');
            });
            if(this.$field.find('.attached-entities-wrap li a').length > 0) {
                t.addHighlightMarkup();
                t.scrollHighlightMarkup();
            }
        },
        insertAtCursorPos(insertStr) {
            let cursorPos = $(this.$textarea).data('cursorPos');
            if(cursorPos) {
                let str = this.$textarea.val() || '';
                let start = str.substring(0, cursorPos.end); //just use end so we aren't deleting text
                if(start.length > 0) {
                    start = start + "\n";
                }
                let end = str.substring(cursorPos.end);
                if(end.length > 0) {
                    end = "\n" + end;
                }
                let newStr = start + insertStr + end;
                this.$textarea.val(newStr);
            }
            else {
                let str = this.$textarea.val() || '';
                let newStr = str + "\n" + insertStr;
                this.$textarea.val(newStr);
            }
        },
        insertDocuments(mediaIds) {
            let documents = [];
            $.each(mediaIds, (idx, id) => {
                documents.push(this.createDocument(id));
            });
            let documentStr = documents.join("\n");
            this.insertAtCursorPos(documentStr);
        },
        createDocument(id) {
           let str = '<document>';
           str += "\n  <source>[sprowt_ai:document_source:"+id+"]</source>";
           //str += "\n  <document_content>[sprowt_ai:document_content:"+id+"]</source>";
           str += "\n</document>";
           return str;
        },
        insertExamples(uuids) {
            let examples = [];
            $.each(uuids, (idx, uuid) => {
                examples.push(this.createExample(uuid));
            });
            let exampleStr = examples.join("\n");
            this.insertAtCursorPos(exampleStr);
        },
        createExample(uuid) {
            let str = '<example>';
            let interior = ['Text: [sprowt_ai:example_prompt:' + uuid + ']', 'Result: [sprowt_ai:example_result:' + uuid + ']'];
            let indent = '  ';
            str += "\n" + indent + interior.join("\n" + indent) + "\n" + '</example>';
            return str;
        },
        insertReferences(references)
        {
            let referenceStr = [];
            $.each(references, (idx, reference) => {
                referenceStr.push('[sprowt_ai:reference:' + reference + ']');
            });
            this.insertAtCursorPos(referenceStr.join("\n"));
        },
        insertContexts(uuids, wrapInXml) {
            let contexts = [];
            $.each(uuids, (idx, uuid) => {
                contexts.push(this.createContext(uuid, wrapInXml));
            });
            let contextStr = contexts.join("\n");
            this.insertAtCursorPos(contextStr);
        },
        createContext(uuid, wrapInXml = true) {
            let str = '<context>';
            let interior = ['[sprowt_ai:context:' + uuid + ']'];
            let indent = '  ';
            if(wrapInXml) {
                str += "\n" + indent + interior.join("\n" + indent) + "\n" + '</context>';
                return str;
            }
            return interior.join("\n" + indent);
        },
        getCursorPos($textarea) {
            return {
                start: $textarea[0].selectionStart,
                end: $textarea[0].selectionEnd
            };
        },
        //from https://stackoverflow.com/questions/15458876/check-if-a-string-is-html-or-not
        isHTML(str) {
            var doc = new DOMParser().parseFromString(str, "text/html");
            return Array.from(doc.body.childNodes).some(node => node.nodeType === 1);
        },
        insertContent(content) {
            let options = this.options;
            let insertSelector = options.insertSelector;
            let $insertField = $(insertSelector);
            if($insertField.length === 0) {
                return;
            }
            //let isHtml = !!options.isHtml;
            let hasCkEditor = this.fieldHasCkEditor($insertField);
            if(hasCkEditor) {
                let ckEditor = this.getCkEditorInstance($insertField);
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
        addHighlightMarkup() {
            let $wrap = this.$textarea.closest('div');
            if(!$wrap.hasClass('highlight-wrap')) {
                $wrap.addClass('highlight-wrap');
            }

            if(!this.$highlight || this.$highlight.length < 0) {
                let $highlight = $('<div class="highlight-markup"></div>');
                $wrap.append($highlight);
                this.$highlight = $highlight;
            }

            let $attachedEntitiesList = this.$field.find('.attached-entities-wrap li a');
            let promptText = this.$textarea.val().replace(/[&<>'"]/g,
                function(tag) {
                    return {
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        "'": '&#39;',
                        '"': '&quot;'
                    }[tag];
                });
            $attachedEntitiesList.each(function() {
                let $a = $(this);
                let uuid = $a.attr('data-entity-uuid');
                promptText = promptText.replaceAll(uuid, '<mark data-uuid="'+uuid+'">'+uuid+'</mark>');
            });

            this.$highlight.html(promptText);
        },
        scrollHighlightMarkup() {
            let scrollTop = this.$textarea.scrollTop();
            this.$highlight.scrollTop(scrollTop);

            // Chrome and Safari won't break long strings of spaces, which can cause
            // horizontal scrolling, this compensates by shifting highlights by the
            // horizontally scrolled amount to keep things aligned
            let scrollLeft = this.$textarea.scrollLeft();
            if (scrollLeft > 0) {
                this.$highlight.css({transform: 'translateX(' + -scrollLeft + 'px)'});
            }
            else {
                this.$highlight.css({transform: ''});
            }
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
        openReferenceModal() {
            let modalSettings = {
                url: '/sprowt-ai/reference-select',
                dialogType: 'modal',
                dialog: {
                    width: 700,
                    height: 400,
                    autoResize: false,
                    resizable: true,
                    draggable: true,
                    classes: {
                        'ui-dialog': 'ui-corner-all ui-widget ui-widget-content ui-front ui-dialog-buttons select-references-dialog'
                    }
                }
            };
            let modal = Drupal.ajax(modalSettings);
            modal.execute();
        },
    });

    Drupal.behaviors.sprowtAiPromptElement = {
        prompts: [],
        attach(context, settings) {
            let t = this;
            $(once('sprowtAiPromptElements', '.claude-prompt.expanded', context)).each(function() {
                t.prompts.push(new ClaudePrompt($(this)));
            });
        }
    };

})(jQuery, Drupal, once);
