<?php

namespace Oro\Bundle\ShoppingListBundle\Tests\Unit\Stub;

use Oro\Bundle\ShoppingListBundle\Entity\LineItem;

class LineItemStub extends LineItem
{
    /**
     * @param int|null $id
     * @return self
     */
    public function setId(?int $id): self
    {
        $this->id = $id;

        return $this;
    }
}
