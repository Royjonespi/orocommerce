<?php

namespace Oro\Bundle\CMSBundle\WYSIWYG;

/**
 * DTO model for processing entity with wysiwyg fields
 */
class WYSIWYGProcessedDTO
{
    /** @var WYSIWYGProcessedEntityDTO */
    private $processedEntity;

    /** @var WYSIWYGProcessedEntityDTO */
    private $ownerEntity;

    /**
     * @param WYSIWYGProcessedEntityDTO $processedEntity
     * @param WYSIWYGProcessedEntityDTO|null $ownerEntity
     */
    public function __construct(
        WYSIWYGProcessedEntityDTO $processedEntity,
        ?WYSIWYGProcessedEntityDTO $ownerEntity = null
    ) {
        $this->processedEntity = $processedEntity;
        $this->ownerEntity = $ownerEntity ?? $processedEntity;
    }

    /**
     * @return WYSIWYGProcessedEntityDTO
     */
    public function getProcessedEntity(): WYSIWYGProcessedEntityDTO
    {
        return $this->processedEntity;
    }

    /**
     * @return WYSIWYGProcessedEntityDTO
     */
    public function getOwnerEntity(): WYSIWYGProcessedEntityDTO
    {
        return $this->ownerEntity;
    }

    /**
     * @return bool
     */
    public function isSelfOwner(): bool
    {
        return $this->ownerEntity === $this->processedEntity;
    }

    /**
     * @return string
     */
    public function requireOwnerEntityClass(): string
    {
        $ownerEntityClass = $this->getOwnerEntity()->getMetadata()->getName();
        if (!$ownerEntityClass) {
            throw new \RuntimeException('Owner entity must have class name');
        }

        return $ownerEntityClass;
    }

    /**
     * @return int|string
     */
    public function requireOwnerEntityId()
    {
        $ownerEntityId = $this->getOwnerEntity()->getEntityId();
        if (!$ownerEntityId) {
            throw new \RuntimeException('Owner entity must have identifier');
        }

        return $ownerEntityId;
    }

    /**
     * @return string
     */
    public function requireOwnerEntityFieldName(): string
    {
        $ownerFieldName = $this->getOwnerEntity()->getFieldName();
        if (!$ownerFieldName) {
            throw new \RuntimeException('Owner entity must have field name');
        }

        return $ownerFieldName;
    }

    /**
     * @param string $fieldName
     * @param string|null $fieldType
     * @return WYSIWYGProcessedDTO
     */
    public function withProcessedEntityField(string $fieldName, ?string $fieldType = null): WYSIWYGProcessedDTO
    {
        $dto = clone $this;
        $dto->processedEntity = $this->getProcessedEntity()->withField($fieldName, $fieldType);

        if ($this->isSelfOwner()) {
            $dto->ownerEntity = $this->getOwnerEntity()->withField($fieldName, $fieldType);
        }

        return $dto;
    }
}
