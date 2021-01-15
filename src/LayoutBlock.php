<?php

namespace AdrHumphreys\LayoutBlock;

use DNADesign\Elemental\Extensions\ElementalAreasExtension;
use DNADesign\Elemental\Forms\ElementalAreaField;
use DNADesign\Elemental\Models\BaseElement;
use DNADesign\Elemental\Models\ElementalArea;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;

/**
 * @method ElementalArea LayoutArea()
 * @property int LayoutAreaID
 * @mixin ElementalAreasExtension
 */
class LayoutBlock extends BaseElement
{
    private static $icon = 'font-icon-block-file-list';

    private static $table_name = 'LayoutBlock';

    private static $title = 'Layout group';

    private static $description = 'Allows you to layout blocks in a dynamic manner';

    private static $singular_name = 'layout';

    private static $plural_name = 'layouts';

    private static $has_one = [
        'LayoutArea' => ElementalArea::class
    ];

    private static $owns = [
        'LayoutArea'
    ];

    private static $cascade_deletes = [
        'LayoutArea'
    ];

    private static $cascade_duplicates = [
        'LayoutArea'
    ];

    public function getType()
    {
        return _t(__CLASS__ . '.BlockType', 'Layout');
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->removeByName('LayoutAreaID');

        $editor = LayoutAreaField::create(
            'LayoutAreaID',
            $this->LayoutArea(),
            $this->getElementalTypes()
        );

        $fields->addFieldToTab('Root.Main', $editor);

        return $fields;
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        if (!$this->owner->LayoutAreaID) {
            $area = ElementalArea::create();
            $area->OwnerClassName = get_class($this->owner);
            $area->write();
            $this->owner->LayoutAreaID = $area->ID;
        }
    }

    public function getElementalTypes()
    {
        $config = $this->owner->config();

        if (is_array($config->get('allowed_elements'))) {
            if ($config->get('stop_element_inheritance')) {
                $availableClasses = $config->get('allowed_elements', Config::UNINHERITED);
            } else {
                $availableClasses = $config->get('allowed_elements');
            }
        } else {
            $availableClasses = ClassInfo::subclassesFor(BaseElement::class);
        }

        if ($config->get('stop_element_inheritance')) {
            $disallowedElements = (array) $config->get('disallowed_elements', Config::UNINHERITED);
        } else {
            $disallowedElements = (array) $config->get('disallowed_elements');
        }
        $list = [];

        foreach ($availableClasses as $availableClass) {
            /** @var BaseElement $inst */
            $inst = singleton($availableClass);

            if (!in_array($availableClass, $disallowedElements) && $inst->canCreate()) {
                if ($inst->hasMethod('canCreateElement') && !$inst->canCreateElement()) {
                    continue;
                }

                $list[$availableClass] = $inst->getType();
            }
        }

        if ($config->get('sort_types_alphabetically') !== false) {
            asort($list);
        }

        if (isset($list[BaseElement::class])) {
            unset($list[BaseElement::class]);
        }

        $class = get_class($this->owner);
        $this->owner->invokeWithExtensions('updateAvailableTypesForClass', $class, $list);

        return $list;
    }
}
