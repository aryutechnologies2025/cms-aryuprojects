<?php

namespace Drupal\sprowt_theme;

use Drupal\block_content\Entity\BlockContent;
use Drupal\layout_builder\Section;
use Drupal\node\Entity\Node;

class HomePageThemer extends ThemerBase
{

    public function updateSection($type, Section $section) {
        switch($type) {
            case 'masthead':
                return $this->updateMasthead($section);
            case 'services':
                return $this->updateServices($section);
        }
        return $section;
    }

    public function updateMasthead(Section $section) {
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
        $section = Section::fromArray($sectionArray);
        $dividerStyle = 'wave';
        if($this->theme == 'wave') {
            $section = $this->updateDivider($dividerStyle, $section);
        }
        else {
            $section = $this->removeDividerFromSection($section);
        }

        return $section;
    }

    public function updateServices(Section $section) {
        switch($this->theme) {
            case 'diagonal':
                $dividerStyle = 'diagonal';
                $serviceStyle = 'altd';
                break;
            case 'round':
                $dividerStyle = 'curve';
                $serviceStyle = 'alta';
                break;
            case 'wave':
                $dividerStyle = 'wave';
                $serviceStyle = 'alte';
                break;
            case 'fun':
                $dividerStyle = 'curve';
                $serviceStyle = 'alte';
                break;
            default:
                $dividerStyle = null;
                $serviceStyle = 'altb';
        }
        $section = $this->updateDivider($dividerStyle, $section);
        $sectionArray = $section->toArray();
        $components = $sectionArray['components'];
        foreach($components as $component) {
            $config = $component['configuration'];
            if($config['id'] == 'inline_block:services') {
                $block = $this->loadInlineBlock($component);
                $block->set('field_display_service', [
                    'value' => $serviceStyle
                ]);
                $section = $this->saveInlineBlock($block, $section);
            }
        }
        return $section;
    }

}
