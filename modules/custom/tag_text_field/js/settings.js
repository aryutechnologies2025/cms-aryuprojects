(function ($){
    let select2Value = function($select) {
        if($select.hasClass('select2-hidden-accessible')) {
            let data = $select.select2('data');
            if(!data) {
                return null;
            }
            let datum = data.shift();
            return datum ? datum.id : null;
        }
        else {
            return $select.val();
        }
    };

    $(document).ready(function() {
        let $textArea = $('.html-tag-text-field-tag-list');
        let $defaultTag = $('.html-tag-text-field-tag-default-value');
        let $tagField = $('.html-tag-text-field-tag-field');
        let select2Exists = $tagField.select2 || false;

        let fillTagField = function() {
            let str = $textArea.val();
            let arr = str.split("\n");
            let defaultVal = $defaultTag.val() || 'div';
            let currentVal = select2Value($tagField) || defaultVal;
            if(select2Exists) {
                if($tagField.hasClass('select2-hidden-accessible')) {
                    $tagField.select2('destroy');
                }
            }
            $tagField.html('');
            $.each(arr, function(ids, arrStr) {
                if(arrStr && arrStr.indexOf('|') > -1) {
                    let arrStrArray = arrStr.split('|');
                    let key = arrStrArray.shift().trim();
                    let text = arrStrArray.join('|').trim();
                    let $option = $('<option></option>').attr('value', key).text(text);
                    $tagField.append($option);
                }
            });
            $tagField.val(currentVal);
            if(select2Exists) {
                $tagField.select2();
            }
        };
        fillTagField();
        $(document).on('keyup', '#' + $textArea.attr('id'), fillTagField);
    });
})(jQuery)
