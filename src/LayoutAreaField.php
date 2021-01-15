<?php

namespace AdrHumphreys\LayoutBlock;

use DNADesign\Elemental\Controllers\ElementalAreaController;
use DNADesign\Elemental\Forms\ElementalAreaField;
use DNADesign\Elemental\Models\BaseElement;
use DNADesign\Elemental\Services\ElementTypeRegistry;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\ORM\DataObjectInterface;

class LayoutAreaField extends ElementalAreaField
{
    protected $schemaComponent = 'ElementEditor';

    protected $schemaDataType = FormField::SCHEMA_DATA_TYPE_CUSTOM;

    public function getSchemaDataDefaults()
    {
        $schemaData = parent::getSchemaDataDefaults();

        $schemaData['fieldName'] = $this->getName();
        $schemaData['areaId'] = $schemaData['elemental-area-id'];
        $schemaData['allowedElements'] = $schemaData['allowed-elements'];
        $schemaData['elementTypes'] = ElementTypeRegistry::generate()->getDefinitions();

        return $schemaData;
    }

    public function setSubmittedValue($value, $data = null)
    {
        // When the field is nested then the content will come through as an array,
        // this is because it gets decoded by the parent which will run the json_decode
        // from below
        if (is_array($value)) {
            return $this->setValue($value);
        }

        // Content comes through as a JSON encoded list through a hidden field.
        return $this->setValue(json_decode($value, true));
    }

    public function saveInto(DataObjectInterface $dataObject)
    {
        GridField::saveInto($dataObject);

        $elementData = $this->Value();
        $idPrefixLength = strlen(sprintf(ElementalAreaController::FORM_NAME_TEMPLATE, ''));

        // The layout block will be passed through here and be empty :shrug:
        if (!$elementData || !is_array($elementData)) {
            return;
        }

        foreach ($elementData as $form => $data) {
            // Extract the ID
            $elementId = (int) substr($form, $idPrefixLength);

            /** @var BaseElement $element */
            $element = $this->getElement($elementId);

            if (!$element) {
                // Ignore invalid elements
                continue;
            }

            $data = ElementalAreaController::removeNamespacesFromFields($data, $element->ID);

            $element->updateFromFormData($data);
            $element->write();
        }
    }

    public function getElement(int $elementId): ?BaseElement
    {
        $element = $this->getArea()->Elements()->byID($elementId);

        if ($element) {
            return $element;
        }

        // The element could be invalid because it's a layout block
        $subareas = $this->getArea()->Elements()->filter('ClassName', LayoutBlock::class);

        /** @var LayoutBlock $subarea */
        foreach ($subareas as $subarea) {
            $element = $subarea->LayoutArea()->Elements()->byID($elementId);

            if ($element) {
                return $element;
            }
        }

        return null;
    }
}
