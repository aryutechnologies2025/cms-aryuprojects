<?php

namespace Drupal\sprowt_theme;

use Drupal\block_content\Entity\BlockContent;
use Drupal\layout_builder\Section;

class LandingThemer extends ThemerBase
{

    public function updateSection($type, Section $section)
    {
        if($type == 'masthead') {
            return $this->updateHero($section);
        }
        if($type == 'contact' || $type == 'reviews') {
            return $this->addDividers($section);
        }
        return $section;
    }

    public function updateHero(Section $section) {
        switch($this->theme) {
            case 'diagonal':
                $style = 'diagonal';
                break;
            case 'round':
                $style = 'round';
                break;
            case 'wave':
                $style = 'gradient';
                break;
            case 'fun':
                $style = 'round-edge';
                break;
            default:
                $style = 'gradient';
        }
        $sectionArray = $section->toArray();
        $components = $sectionArray['components'];
        foreach($components as &$componentArray) {
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

        return $section;
    }

    public function addDividers(Section $section) {
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
