(function($, Drupal, once) {

    let TokenTableFilter = function($table) {
        this.$table = $table;
        this.$totalWrap = $table.closest('.token-tree-wrap');
        this.$filterForm = this.$totalWrap.find('form');
        this.$filterWrap = this.$totalWrap.find('.token-tree-filter-wrap');
        this.$filterInput = this.$totalWrap.find('.token-tree-filter');
        this.rows = this.$filterWrap.data('rows') || [];
        if(typeof this.rows === 'string') {
            this.rows = JSON.parse(this.rows);
        }
        this.filterVal  = this.$filterInput.val().toLowerCase().trim();
        this.filtering = false;
        this.events();
        this.filter();
    };

    TokenTableFilter.prototype = $.extend(TokenTableFilter.prototype, {
        filter() {
            this.filtering = true;
            this.filterVal  = this.$filterInput.val().toLowerCase().trim();
            console.log('filterVal', {
                input: this.$filterInput,
                inputRaw: this.$filterInput.val(),
                filter: this.filterVal
            });
            this.foundParents = [];
            this.expandRows = [];
            let rowNodes = this.$table[0].querySelectorAll('tbody tr');

            this.$table.find('tbody tr').removeClass('filtered-out');

            if(!this.filterVal) {
                this.$table.treetable('collapseAll');
                return;
            }
            else {
                this.$table.treetable('expandAll');
            }

            let t = this;
            for(let rowNode of rowNodes) {
                let $row = $(rowNode);
                let rowObj = t.rows[$row.attr('data-tt-id')];
                let found = t.filterRow(rowObj);
                if(found) {
                    t.expandRows.push($row.attr('data-tt-id'));
                    if(rowObj['data-tt-parent-id']) {
                        let parent = t.rows[rowObj['data-tt-parent-id']];
                        while(parent) {
                            t.foundParents.push(parent['data-tt-id']);
                            parent = t.rows[parent['data-tt-parent-id'] || '<none>'] || null;
                        }
                    }
                }
                else {
                    $row.addClass('filtered-out');
                }
            }

            for(let rowNode of rowNodes) {
                let $row = $(rowNode);
                let id = $row.attr('data-tt-id');
                if(t.foundParents.includes(id)) {
                    $row.removeClass('filtered-out');
                }
            }
            this.filtering = false;
        },
        filterRow(rowObj) {
            let filter = this.filterVal;
            if(!filter) {
                return true;
            }

            let found = false;
            let filterColumn = function(columnVal) {
                columnVal = columnVal.toString().trim().toLowerCase();
                if(!columnVal) {
                    return false;
                }
                let foundInColumn = true;
                let words = filter.split();
                for(let word of words) {
                    foundInColumn = foundInColumn && columnVal.includes(word);
                }
                return foundInColumn;
            };

            for(let columnName in rowObj.columns) {
                let columnVal = rowObj.columns[columnName];
                if(columnName === 'token') {
                    found = found || filterColumn(columnVal);
                }
            }
            if(found
                && rowObj['data-tt-parent-id']
                && !this.foundParents.includes(rowObj['data-tt-parent-id'])
            ) {
                this.foundParents.push(rowObj['data-tt-parent-id']);
            }
            return found;
        },
        events() {
            let t = this;
            this.$filterForm.on('submit', function(e) {
                e.preventDefault();
            });
            this.$filterInput.on('keyup', function(e) {
                let $input = $(this);
                $input.data('current-val', $input.val());
                window.setTimeout(function() {
                    if($input.val() === $input.data('current-val')) {
                        t.filter();
                        console.log('filtered');
                    }
                }, 300);
            });
        }
    });

    Drupal.behaviors.sprowtAdminOverrideTokenFilter = {
        tables: [],
        attach(context, settings) {
            let t = this;
            $(once('sprowtAdminOverrideTokenFilter', 'table.token-tree', context)).each(function(){
                let $table = $(this);
                t.tables.push(new TokenTableFilter($table));
            });
        }
    };


})(jQuery, Drupal, once);
