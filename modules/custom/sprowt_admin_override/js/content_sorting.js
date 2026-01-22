(function($, Drupal){

    const sortTable = function() {
        let $table = $('.views-table');
        let $tBody = $table.find('tbody');
        let $rows = $tBody.find('tr');
        let rowOrder = [];
        let i = 0;
        $rows.each(function() {
            let $row = $(this);
            let title = $row.find('.views-field-title .tabledrag-cell-content__item').text();
            let orderObj = {
                title: title,
                $row: $row,
                originalPosition: i
            };
            rowOrder.push(orderObj);
            ++i;
        });
        rowOrder.sort(function(a, b) {
            let aVal = a.title.toLowerCase().replace(/[^a-z0-9]+/g, '');
            let bVal = b.title.toLowerCase().replace(/[^a-z0-9]+/g, '');
            let ret = aVal.localeCompare(bVal);
            return ret;
        });
        let setRow = function(position, $row, originalPosition) {
            let tableDrag = Drupal.tableDrag;
            let dragRow = new tableDrag.prototype.row($row[0], 'keyboard', Drupal.tableDrag.indentEnabled, Drupal.tableDrag.maxDepth, true);
            tableDrag.rowObject = dragRow;
            let swapRow = $rows[position];
            dragRow.swap('before', swapRow);
            $rows = $tBody.find('tr');
            if(position !== originalPosition) {
                tableDrag.safeBlur = true;
                let $handle = $($rows[position]).find('.tabledrag-handle');
                $handle.trigger('blur');
                tableDrag.rowObject.markChanged();
                console.log(tableDrag);
                if (!tableDrag.changed) {
                    $(Drupal.theme('tableDragChangedWarning')).insertBefore($table).hide().fadeIn('slow');
                    tableDrag.changed = true;
                }
            }
        };
        $.each(rowOrder, function(position, rowObj) {
            let $row = rowObj.$row;
            setRow(position, $row, rowObj.originalPosition);
        });
    };

    $(document).on('click', '.rearrange-button', function(e) {
        e.preventDefault();
        sortTable();
    });
})(jQuery, Drupal);
