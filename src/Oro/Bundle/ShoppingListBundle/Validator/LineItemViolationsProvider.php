<?php

namespace Oro\Bundle\ShoppingListBundle\Validator;

use Doctrine\Common\Collections\ArrayCollection;
use Oro\Bundle\ShoppingListBundle\Entity\LineItem;
use Oro\Bundle\ShoppingListBundle\Validator\Constraints\LineItemCollection as LineItemCollectionConstraint;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Provides list of errors and warnings for the given line items.
 */
class LineItemViolationsProvider
{
    /**
     * @var ValidatorInterface
     */
    protected $validator;

    /**
     * @var ConstraintViolationListInterface
     */
    protected $violations;

    /**
     * @param ValidatorInterface $validator
     */
    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    /**
     * @param LineItem[] $lineItems
     * @param mixed $additionalContext
     *
     * @return array
     */
    public function getLineItemErrors($lineItems, $additionalContext = null)
    {
        $violations = $this->getLineItemViolations($lineItems, $additionalContext);
        $indexedLineItemErrors = [];
        foreach ($violations as $violation) {
            if ($violation->getCause() !== 'warning') {
                $indexedLineItemErrors[$violation->getPropertyPath()][] = $violation;
            }
        }

        return $indexedLineItemErrors;
    }

    /**
     * @param LineItem[] $lineItems
     * @param mixed $additionalContext
     *
     * @return array
     */
    public function getLineItemWarnings($lineItems, $additionalContext = null)
    {
        $violations = $this->getLineItemViolations($lineItems, $additionalContext);
        $indexedLineItemWarnings = [];
        foreach ($violations as $violation) {
            if ($violation->getCause() === 'warning') {
                $indexedLineItemWarnings[$violation->getPropertyPath()][] = $violation;
            }
        }

        return $indexedLineItemWarnings;
    }

    /**
     * @param LineItem[] $lineItems
     * @param mixed $additionalContext
     *
     * @return array
     */
    public function getLineItemViolationLists($lineItems, $additionalContext = null): array
    {
        $indexedViolations = [];
        foreach ($this->getLineItemViolations($lineItems, $additionalContext) as $violation) {
            $indexedViolations[$violation->getPropertyPath()][] = $violation;
        }

        return $indexedViolations;
    }

    /**
     * @param LineItem[] $lineItems
     * @param mixed $additionalContext
     *
     * @return ConstraintViolationListInterface
     */
    protected function getLineItemViolations($lineItems, $additionalContext = null)
    {
        if (!$this->violations) {
            $constraint = new LineItemCollectionConstraint();
            $constraint->setAdditionalContext($additionalContext);

            if (is_array($lineItems)) {
                $lineItems = new ArrayCollection($lineItems);
            }
            $this->violations = $this->validator->validate($lineItems, [$constraint]);
        }

        return $this->violations;
    }

    /**
     * @param LineItem[] $lineItems
     *
     * @return bool
     */
    public function isLineItemListValid($lineItems)
    {
        if (is_array($lineItems)) {
            $lineItems = new ArrayCollection($lineItems);
        }

        return count($this->validator->validate($lineItems, [new LineItemCollectionConstraint()])) == 0;
    }
}
