<?php

declare(strict_types=1);

namespace Drupal\sprowt_ai\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'PromptItemFormatter' formatter.
 *
 * @FieldFormatter(
 *   id = "sprowt_ai_prompt_item_formatter",
 *   label = @Translation("Prompt Formatter"),
 *   field_types = {"sprowt_ai_prompt"},
 * )
 */
final class PromptItemFormatter extends FormatterBase
{

    /**
     * {@inheritdoc}
     */
    public function viewElements(FieldItemListInterface $items, $langcode): array
    {
        $element = [];
        foreach ($items as $delta => $item) {
            $element[$delta] = [
                '#markup' => $item->value,
            ];
        }
        return $element;
    }

}
