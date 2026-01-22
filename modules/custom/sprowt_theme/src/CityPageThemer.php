<?php

namespace Drupal\sprowt_theme;

use Drupal\block_content\Entity\BlockContent;
use Drupal\layout_builder\Section;

class CityPageThemer extends ThemerBase
{

    public function updateSection($type, Section $section)
    {
        if($this->isLastBlurb($section)) {
            return $this->updateLastBlurb($section);
        }
        if($type == 'hero') {
            return $this->updateHero($section);
        }
        return $section;
    }

    public function isLastBlurb(Section $section) {
        $nextSection =  $this->nextSection($section);
        if(!empty($nextSection) && $this->sectionType($nextSection) == 'special-offers') {
            return true;
        }
        return false;
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

    public function updateLastBlurb(Section $section) {
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
