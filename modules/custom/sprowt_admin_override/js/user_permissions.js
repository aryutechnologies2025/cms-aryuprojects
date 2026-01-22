(function($,Drupal){
    Drupal.behaviors.sprowtFilterPermissions = {
        filter: function($input) {
            let query = $input.val();
            let tableSelector = $input.data('table');
            let $table = $(tableSelector);
            let $rows = $table.find('tbody tr');
            $rows.removeClass('sprowt-hidden');
            if(!query) {
                this.setParamVal(null);
                return true;
            }
            let moduleRowShow = false;
            let $currentModuleRow = null;
            $rows.each(function() {
                let tests = [];
                let $moduleCell = $(this).find('td.module');
                if($moduleCell.length) {
                    if($currentModuleRow && !moduleRowShow) {
                        $currentModuleRow.addClass('sprowt-hidden');
                    }
                    $currentModuleRow = $(this);
                    moduleRowShow = false;
                    //don't filter module name
                    return null;
                }
                else {
                    let description = $(this).find('.permission').text().toLowerCase();
                    tests.push(description);
                }
                let found = true;
                $.each(query.split(' '), function (idx, word) {
                    let passed = false;
                    $.each(tests, function(tdx, test) {
                        passed = passed || test.indexOf(word) >= 0;
                    });
                    found = found && passed;
                });
                if(!found) {
                    $(this).addClass('sprowt-hidden');
                }
                else {
                    moduleRowShow = true;
                }
            });
            //one last time for the last row
            if($currentModuleRow && !moduleRowShow) {
                $currentModuleRow.addClass('sprowt-hidden');
            }
            this.setParamVal(query);
        },
        getParamVal: function() {
            let url = new URL(window.location.href);
            return url.searchParams.get('sprowt-filter');
        },
        setParamVal: function(query) {
            let current = this.getParamVal();
            if(current === query) {
                return null;
            }
            let url = new URL(window.location.href);
            if(query) {
                url.searchParams.set('sprowt-filter', query);
            }
            else {
                url.searchParams.delete('sprowt-filter');
            }
            window.history.replaceState({}, null, url.toString());
        },
        attach: function (context) {
            let t = this;
            $(once('sprowtFilterPermissions', '.table-sprowt-filter-text', context)).each(function() {
                let $input = $(this);
                let $filterWrap = $('.table-filter');
                if($filterWrap.length) {
                    //move to module filter container at the top
                    $filterWrap.append($input.closest('.form-item'));
                }

                $input.keyup(function() {
                    t.filter($input);
                });
                let currentQuery = t.getParamVal();
                if(currentQuery) {
                    $input.val(currentQuery);
                    t.filter($input);
                }
            });
        }
    };
})(jQuery, Drupal);
