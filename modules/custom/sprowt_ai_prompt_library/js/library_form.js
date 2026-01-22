(function($, Drupal, once, Sprowt){

    const SPLFPager = function($form) {
        this.$form = $form;
        this.$pager = $form.find('.pager');
        this.$table = $form.find('table');
        this.currentPage = this.$table.attr('data-current-page') || 1;
        this.totalPages = 999;
        this.inModal = $form.closest('.ui-dialog').length > 0;
        this.events();
    };

    $.extend(SPLFPager.prototype, {
        chunkSize: 10,
        resetPager() {
            this.$pager.find('.pager__item--number').remove();
            let t = this;
            let $rows = this.$table.find('tbody tr').not('.hidden');
            $rows.hide();
            for(let cpage = 1; cpage <= this.totalPages; ++cpage) {
                $rows.filter('.page-' + cpage).removeClass('page-' + cpage);
            }
            let pages = 0;
            let totalPages = 0;
            for (let i = 0; i < $rows.length; i += this.chunkSize) {
                ++pages;
                let page = pages;
                totalPages = pages;
                let chunk = $rows.toArray().slice(i, i + this.chunkSize);
                $.each(chunk, function(index, row) {
                    let $row = $(row);
                    $row.addClass('page-' + page);
                    if(page === parseInt(t.currentPage)) {
                        $row.show();
                    }
                });
                t.addPageItem(page);
            }
            this.totalPages = totalPages;
            if(this.currentPage > totalPages) {
                this.currentPage = totalPages;
            }
            this.switchPage(this.currentPage);
        },
        addPageItem(page) {
            let active = page === this.currentPage;
            let $item = $('<li class="pager__item pager__item--number"></li>');
            let $a = $('<a class="pager__link" href="?page=' + page + '">' + page + '</a>');
            if(active) {
                $item.addClass('pager__item--active');
                $a.addClass('is-active');
            }
            $item.append($a);
            this.$pager.find('.pager__item--next').before($item);
        },
        switchPage(page) {
            if(!this.inModal) {
                let url = new URL(window.location);
                url.searchParams.set('page', page);
                window.history.pushState(null, null, url.toString());
            }
            this.$table.find('tbody tr').not('.hidden').hide();
            this.$table.find('tbody tr.page-' + page).not('.hidden').show();
            this.$table.data('current-page', page);
            this.currentPage = page;
            this.$pager.find('li.pager__item--action').hide();
            this.$pager.find('.pager__item--active').removeClass('pager__item--active');
            this.$pager.find('.is-active').removeClass('is-active');
            this.$pager.find('li.pager__item--ellipsis').remove();
            let $li = this.$pager.find('a[href="?page=' + page + '"]').closest('li.pager__item');
            $li.addClass('pager__item--active');
            $li.find('a').addClass('is-active');
            let total = this.totalPages;
            if(page > 1) {
                this.$pager.find('li.pager__item--previous').show();
                this.$pager.find('li.pager__item--first').show();
            }
            if(page < total) {
                this.$pager.find('li.pager__item--next').show();
                this.$pager.find('li.pager__item--last').show();
            }
            let $numItems = this.$pager.find('.pager__item--number');
            $numItems.show();
            if(total > 9) {
                let chunkSize = 9;
                for (let i = 0; i < $numItems.length; i += chunkSize) {
                    let chunk = $numItems.toArray().slice(i, i + chunkSize);
                    let inChunk = false;
                    $.each(chunk, function(idx, item) {
                        let $item = $(item);
                        if($item.find('a').attr('href') === '?page=' + page) {
                            inChunk = true;
                        }
                    });
                    if(!inChunk) {
                        $.each(chunk, function(idx, item) {
                            let $item = $(item);
                            $item.hide();
                        });
                        if(i === 0) {
                            let $first = $(chunk[0]);
                            $first.before('<li class="pager__item pager__item--ellipsis">...</li>');
                        }
                        if(i >= ($numItems.length - chunkSize)) {
                            let $last = $(chunk[chunk.length - 1]);
                            $last.after('<li class="pager__item pager__item--ellipsis">...</li>');
                        }
                    }
                }
            }
        },
        events() {
            let t = this;
            this.$pager.on('click', 'a', function(e) {
                e.preventDefault();
                let query = $(this).attr('href');
                let q = new URLSearchParams(query);
                let currentPage = t.currentPage;
                let page = q.get('page');
                if(page === 'first') {
                    page = 1;
                }
                if(page === 'last') {
                    page = t.totalPages;
                }
                if (page === 'previous') {
                    page = currentPage - 1;
                    if(page < 1) {
                        page = 1;
                    }
                }
                if(page === 'next') {
                    page = currentPage + 1;
                    if (page > t.totalPages) {
                        page = t.totalPages;
                    }
                }
                t.switchPage(page);
            });
        }
    });


    const SPLFFilters = function($form) {
        this.$form = $form;
        this.inModal = $form.closest('.ui-dialog').length > 0;
        this.$table = $form.find('table');
        this.$sourceFilter = $form.find('select[data-drupal-selector="edit-filterbysource"]');
        this.$labelFilter = $form.find('input[data-drupal-selector="edit-filterbylabel"]');
        this.$descriptionFilter = $form.find('input[data-drupal-selector="edit-filterbydescription"]');
        this.$tagsFilter = $form.find('select[data-drupal-selector="edit-filterbytag"]');
        if(!this.inModal) {
            let url = new URL(window.location);
            let filterStr = url.searchParams.get('filter') || '{}';
            this.filters = JSON.parse(filterStr);
        }
        else {
            this.filters = {};
        }
        this.setFilters(this.filters);
        //this.pager = new SPLFPager($form);
        this.events();
        this.$sourceFilter.trigger('change');
        // let t = this;
        // window.setTimeout(() => {
        //     t.$sourceFilter.trigger('change');
        // }, 1000);
    };

    $.extend(SPLFFilters.prototype, {
        getFilters() {
            let filters = {};
            let source =  Sprowt.selectValue(this.$sourceFilter);
            let tag =  Sprowt.selectValue(this.$tagsFilter);
            if (source) {
                filters.source = source;
            }
            if(tag) {
                filters.tag = tag;
            }
            if (this.$labelFilter.val()) {
                filters.label = this.$labelFilter.val();
            }
            if (this.$descriptionFilter.val()) {
                filters.description = this.$descriptionFilter.val();
            }
            this.filters = filters;
        },
        setFilters(filters) {
            if (filters.source) {
                this.$sourceFilter.val(filters.source);
            }
            if (filters.label) {
                this.$labelFilter.val(filters.label);
            }
            if (filters.description) {
                this.$descriptionFilter.val(filters.description);
            }
            if(filters.tag) {
                this.$tagsFilter.val(filters.tag);
            }
        },
        filter() {
            this.getFilters();
            this.$table.find('tbody tr').removeClass('hidden');
            this.$table.find('tbody tr').show();
            this.$table.find('tbody tr').attr('style', '');
            let $rows = this.$table.find('tbody tr');
            let t = this;
            $rows.each(function (idx, row) {
                let $row = $(row);
                let $cells = $row.find('td');
                let inRow = true;
                $.each(t.filters, function (key, value) {
                    if(value) {
                        value = value.toLowerCase();
                        let $cell = $cells.filter('[data-filter-key="' + key + '"]');
                        let cellValue = $cell.text().trim().toLowerCase();
                        if (key === 'source') {
                            if (cellValue !== value) {
                                inRow = false;
                            }
                        }
                        else if(key === 'tag') {
                            let tags = cellValue.split(', ');
                            let inTag = value === '_none';
                            $.each(tags, function (idx, tag) {
                                if (tag.trim().toLowerCase() === value && value !== '_none') {
                                    inTag = true;
                                }
                            });
                            if (!inTag) {
                                inRow = false;
                            }
                        }
                        else {
                            cellValue = $cell.find('span').attr('title').toLowerCase();
                            let words = value.split(' ');
                            let inCell = true;
                            $.each(words, function (idx, word) {
                                if (cellValue.indexOf(word) < 0) {
                                    inCell = false;
                                }
                            });
                            if (!inCell) {
                                inRow = false;
                            }
                        }
                    }

                });
                if (!inRow) {
                    $row.addClass('hidden');
                }
            });
            if(!t.inModal) {
                let filterStr = JSON.stringify(this.filters);
                let url = new URL(window.location);
                url.searchParams.set('filter', filterStr);
                window.history.pushState({}, '', url.href);
            }
            //this.pager.resetPager();
        },
        events() {
            let t= this;
            this.$sourceFilter.change(function() {
                t.filter();
            });
            this.$tagsFilter.change(function() {
                t.filter();
            });
            this.$labelFilter.keyup(function () {
                window.setTimeout(function () {
                    t.filter();
                }, 1000);
            });
            this.$descriptionFilter.keyup(function () {
                window.setTimeout(function () {
                    t.filter();
                }, 1000);
            });
        },
    });

    Drupal.behaviors.sprowt_ai_prompt_libary_form = {
        formObjs: [],
        insertAtCursorPos(insertStr, $field) {
            let cursorPos = $field.data('cursorPos');
            if(cursorPos) {
                let str = $field.val() || '';
                let start = str.substring(0, cursorPos.end); //just use end so we aren't deleting text
                if(start.length > 0) {
                    start = start + "\n";
                }
                let end = str.substring(cursorPos.end);
                if(end.length > 0) {
                    end = "\n" + end;
                }
                let newStr = start + insertStr + end;
                $field.val(newStr);
            }
            else {
                let str = $field.val() || '';
                let newStr = str + "\n" + insertStr;
                $field.val(newStr);
            }
        },
        attach(context, settings) {
            let t = this;
            let $form = $('form.sprowt-ai-prompt-library-prompt-library', context);
            if($form.length <= 0) {
                window.setTimeout(function () {
                    t.attach(context, settings);
                }, 300);
                return;
            }
            $(once('sprowt_ai_prompt_libary_form', 'form.sprowt-ai-prompt-library-prompt-library', context)).each(function() {
                let $form = $(this);
                let obj = new SPLFFilters($form);
                t.formObjs.push(obj);

                let $elementSelectorInput= $form.find('.element-selector');
                if($elementSelectorInput.length > 0) {
                    let elementSelector = $elementSelectorInput.val();
                    let $field = $(elementSelector);
                    $field.on('applyPrompt', function(e, applyAsToken, source, uuid, promptText) {
                        if(applyAsToken) {
                            promptText = '[sprowt_ai:prompt:'+source+':'+uuid+']';
                            t.insertAtCursorPos(promptText, $field.find('.claude-prompt-textarea'));
                        }
                        else {
                            $field.find('.claude-prompt-textarea').val(promptText);
                        }

                        $field.find('.attach-entities').trigger('mousedown');
                    });
                    $field.on('refreshPromptLibrary', function(e) {
                        window.setTimeout(function () {
                            $field.find('.prompt-library-button').trigger('click');
                        }, 300);
                    });
                }
            });
        }
    };

    $(document).ready(function() {
        var openDialogFunction = Drupal.AjaxCommands.prototype.openDialog;
        var newOpenDialogFunction = function(ajax, response, status) {
            openDialogFunction.call(Drupal.AjaxCommands.prototype, ajax, response, status);
            Drupal.behaviors.sprowt_ai_prompt_libary_form.attach(document, Drupal.settings);
        };

        Drupal.AjaxCommands.prototype.openDialog = newOpenDialogFunction;
    });

})(jQuery, Drupal, once, Sprowt);
