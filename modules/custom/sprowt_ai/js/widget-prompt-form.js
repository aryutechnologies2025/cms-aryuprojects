(function($, Drupal, once, SprowtToolTip, clipboard) {


    Drupal.behaviors.sprowt_ai_widget_prompt_form = {
        //from https://stackoverflow.com/questions/15458876/check-if-a-string-is-html-or-not
        isHTML(str) {
            var doc = new DOMParser().parseFromString(str, "text/html");
            return Array.from(doc.body.childNodes).some(node => node.nodeType === 1);
        },
        fallbackCopyTextToClipboard(text) {
            let textArea = document.createElement("textarea");
            textArea.value = text;

            // Avoid scrolling to bottom
            textArea.style.top = "0";
            textArea.style.left = "0";
            textArea.style.position = "fixed";

            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();

            try {
                let successful = document.execCommand('copy');
                let msg = successful ? 'successful' : 'unsuccessful';
            } catch (err) {
                console.error('Fallback: Oops, unable to copy', err);
            }

            document.body.removeChild(textArea);
        },
        fallbackCopyHTMLToClipboard(html) {
            let container = document.createElement("div");
            container.innerHTML = html;
            // Hide element
            container.style.position = 'fixed';
            container.style.pointerEvents = 'none';
            container.style.opacity = 0;

            document.body.appendChild(container);
            // Copy to clipboard
            window.getSelection().removeAllRanges();

            var range = document.createRange();
            range.selectNode(container);
            window.getSelection().addRange(range);

            try {
                let successful = document.execCommand('copy');
                let msg = successful ? 'successful' : 'unsuccessful';
            } catch (err) {
                console.error('Fallback: Oops, unable to copy', err);
            }

            document.body.removeChild(container);
        },
        copyContentToClipboard(content) {
            let t = this;
            return new Promise(function(resolve, reject) {
                try {
                    if (t.isHTML(content)) {
                        if (clipboard) {
                            let item = new ClipboardItem({
                                'text/html': new Blob([content], {type: 'text/html'}),
                                'text/plain': new Blob([content], {type: 'text/plain'})
                            });
                            clipboard.write([item]).then(resolve).catch(reject);
                        } else {
                            t.fallbackCopyHTMLToClipboard(content);
                            resolve();
                        }
                    } else {
                        if (clipboard) {
                            clipboard.writeText(content).then(resolve).catch(reject);
                        } else {
                            t.fallbackCopyTextToClipboard(content);
                            resolve();
                        }
                    }
                }
                catch (e) {
                    reject(e);
                }
            });
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
        insertContent(content) {
            let t = this;
            console.log({content});
            return new Promise(function(resolve, reject) {
                try {
                    let $insertField = $('.sprowt-ai-insert-container');
                    if ($insertField.length > 0 && !$insertField.is('input[type="text"]') && !$insertField.is('textarea')) {
                        if ($insertField.find('textarea').length > 0) {
                            $insertField = $insertField.find('textarea');
                        } else {
                            $insertField = $insertField.find('input[type="text"]');
                        }
                    }
                    console.log({$insertField});
                    if ($insertField.length === 0) {
                        return;
                    }
                    //let isHtml = !!options.isHtml;
                    let hasCkEditor = t.fieldHasCkEditor($insertField);
                    if (hasCkEditor) {
                        let ckEditor = t.getCkEditorInstance($insertField);
                        if (ckEditor) {
                            if (!t.isHTML(content)) {
                                content = content.replace(/\n/g, '<br>');
                            }
                            ckEditor.setData(content);
                        }
                    } else {
                        $insertField.val(content);
                    }
                    resolve();
                }
                catch (error) {
                    console.error(error);
                    reject(error);
                }
            });
        },
        attach: function(context, settings) {
            let t = this;
            $(once('sprowt_ai_widget_prompt_form', 'form.widget-prompt-form')).each(function (){
                let $form = $(this);
                $form.on('click', '.use-button', function(e) {
                    e.preventDefault();
                    let $button = $(this);
                    let key = $button.attr('data-generated-content-key');
                    let $generatedContent = $form.find('.generated-content-content[data-generated-content="' + key + '"]');
                    let content = $generatedContent.html();
                    if($generatedContent.children('pre').length > 0) {
                        content = $generatedContent.children('pre').html();
                    }
                    // t.copyContentToClipboard(content).then(function () {
                    //     SprowtToolTip.show($button[0], 'Copied to clipboard!');
                    // });
                    t.insertContent(content).then(function() {
                        SprowtToolTip.show($button[0], 'Content used!');
                    });
                });
                $form.on('click', '.remove-button', function(e) {
                    e.preventDefault();
                    let $button = $(this);
                    let key = $button.attr('data-generated-content-key');
                    let $removeButton = $form.find('.remove-generated-content-submit');
                    let $keyValue = $form.find('.remove-generated-content-key');
                    $keyValue.val(key);
                    $removeButton.trigger('click');
                });
            });
        }
    };

})(jQuery, Drupal, once, SprowtToolTip, navigator.clipboard);
