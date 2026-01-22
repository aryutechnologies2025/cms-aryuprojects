(function($, Drupal, once, Sprowt){

    Drupal.behaviors.claude3Tester = {
        attach(context, settings) {
            $(once('claude3Tester', '.claude-prompt', context)).each(function() {
                let $promptField = $(this);
                let $systemField = $('#edit-system');
                let $tokenField = $('#edit-maxtokens');
                let $usedTokensWrap = $('.tokens-used');
                let $tempField = $('#edit-temperature');

                let $optionsField = $promptField.find('.options-value');

                let saveOptions = (options) => {
                    $optionsField.val(JSON.stringify(options));
                };

                let getOptions = () => {
                    let optionsVal = $optionsField.val();
                    if (typeof optionsVal === 'string') {
                        optionsVal = JSON.parse(optionsVal);
                    }
                    return optionsVal;
                };

                $systemField.on('change', () => {
                    let options = getOptions();
                    options.systemId = Sprowt.selectValue($systemField);
                    saveOptions(options);
                });
                $tokenField.on('input', () => {
                    let options = getOptions();
                    options.max_tokens = $tokenField.val();
                    saveOptions(options);
                });
                $tempField.on('input', () => {
                    let options = getOptions();
                    options.temperature = $tempField.val();
                    saveOptions(options);
                });

                $promptField.on('claude3GenerateContentResponse', (e, response) => {
                    let usage = response.usage;
                    let $inputTokens = $usedTokensWrap.find('.input-tokens');
                    let $outputTokens = $usedTokensWrap.find('.output-tokens');
                    $inputTokens.val(usage.input_tokens);
                    $outputTokens.val(usage.output_tokens);
                    $usedTokensWrap.show();
                });
            });
        }
    };

})(jQuery, Drupal, once, Sprowt);
