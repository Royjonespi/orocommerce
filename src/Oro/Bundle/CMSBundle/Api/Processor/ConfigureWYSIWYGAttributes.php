<?php

namespace Oro\Bundle\CMSBundle\Api\Processor;

use Oro\Bundle\ApiBundle\Config\EntityDefinitionConfig;
use Oro\Bundle\ApiBundle\Config\EntityDefinitionFieldConfig;
use Oro\Bundle\ApiBundle\Processor\GetConfig\ConfigContext;
use Oro\Bundle\CMSBundle\Provider\WYSIWYGFieldsProvider;
use Oro\Component\ChainProcessor\ContextInterface;
use Oro\Component\ChainProcessor\ProcessorInterface;

/**
 * Marks WYSIWYG attributes as excluded and adds them to "depends_on" option of a specific attributes collection.
 */
class ConfigureWYSIWYGAttributes implements ProcessorInterface
{
    /** @var WYSIWYGFieldsProvider */
    private $wysiwygFieldsProvider;

    /** @var string */
    private $attributesFieldName;

    /**
     * @param WYSIWYGFieldsProvider $wysiwygFieldsProvider
     * @param string                $attributesFieldName
     */
    public function __construct(WYSIWYGFieldsProvider $wysiwygFieldsProvider, string $attributesFieldName)
    {
        $this->wysiwygFieldsProvider = $wysiwygFieldsProvider;
        $this->attributesFieldName = $attributesFieldName;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContextInterface $context)
    {
        /** @var ConfigContext $context */

        $definition = $context->getResult();

        $attributesField = $definition->getField($this->attributesFieldName);
        if (null === $attributesField) {
            return;
        }

        $wysiwygFields = ConfigureWYSIWYGFields::getWysiwygFields($context);
        if (empty($wysiwygFields)) {
            return;
        }

        $entityClass = $context->getClassName();
        foreach ($wysiwygFields as $fieldName) {
            if (!$this->wysiwygFieldsProvider->isWysiwygAttribute($entityClass, $fieldName)) {
                continue;
            }
            $field = $this->getWysiwygField($definition, $fieldName);
            if (null === $field) {
                continue;
            }
            $field->setExcluded();
            $fieldDependsOn = $field->getDependsOn() ?? [];
            foreach ($fieldDependsOn as $dependsOnFieldName) {
                $attributesField->addDependsOn($dependsOnFieldName);
            }
        }
    }

    /**
     * @param EntityDefinitionConfig $definition
     * @param string                 $propertyPath
     *
     * @return EntityDefinitionFieldConfig|null
     */
    private function getWysiwygField(
        EntityDefinitionConfig $definition,
        string $propertyPath
    ): ?EntityDefinitionFieldConfig {
        $fieldName = $definition->findFieldNameByPropertyPath($propertyPath);
        if (0 === strncmp($fieldName, '_', 1)) {
            $fieldName = substr($fieldName, 1);
        }

        return $definition->getField($fieldName);
    }
}
