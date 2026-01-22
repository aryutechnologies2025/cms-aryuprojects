<?php

namespace Drupal\sprowt_theme;

use Drupal\block_content\Entity\BlockContent;
use Drupal\layout_builder\Section;

class ServiceThemer extends ThemerBase
{

    public function updateSection($type, Section $section)
    {
        if($type == 'info-concerns') {
            return $this->updateBlurb($section);
        }
        if($type == 'masthead') {
            return $this->updateHero($section);
        }
        if($type == 'special-offer') {
            return $this->removeDividerFromSection($section);
        }
        return $section;
    }

    public function updateHero(Section $section) {
        $style = 'overlay';
        $sectionArray = $section->toArray();
        $components = $sectionArray['components'];
        foreach($components as $componentArray) {
            $config = $componentArray['configuration'];
            if(isset($config['id']) && $config['id'] == 'inline_block:hero') {
                /** @var BlockContent $block */
                $block = $this->loadInlineBlock($componentArray);
                if(!empty($style)) {
                    $block->set('field_hero_style', [
                        'value' => $style
                    ]);
                }
                else {
                    $block->set('field_hero_style', null);
                }
                $section = $this->saveInlineBlock($block, $section);
            }
        }
        return $this->removeDividerFromSection($section);
    }

    public function updateBlurb(Section $section) {
        switch($this->theme) {
            case 'diagonal':
                $dividerStyle = 'diagonal';
                break;
            case 'round':
                $dividerStyle = 'curve';
                break;
            case 'wave':
                $dividerStyle = 'wave';
                break;
            case 'fun':
                $dividerStyle = 'curve';
                break;
            default:
                $dividerStyle = null;
        }
        return $this->updateDivider($dividerStyle, $section);
    }
}
