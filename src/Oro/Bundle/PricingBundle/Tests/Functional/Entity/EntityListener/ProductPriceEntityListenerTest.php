<?php

namespace Oro\Bundle\PricingBundle\Tests\Functional\Entity\EntityListener;

use Oro\Bundle\CurrencyBundle\Entity\Price;
use Oro\Bundle\MessageQueueBundle\Test\Functional\MessageQueueExtension;
use Oro\Bundle\PricingBundle\Async\Topics;
use Oro\Bundle\PricingBundle\Entity\PriceList;
use Oro\Bundle\PricingBundle\Entity\ProductPrice;
use Oro\Bundle\PricingBundle\Entity\Repository\ProductPriceRepository;
use Oro\Bundle\PricingBundle\Manager\PriceManager;
use Oro\Bundle\PricingBundle\Sharding\ShardManager;
use Oro\Bundle\PricingBundle\Tests\Functional\DataFixtures\LoadPriceLists;
use Oro\Bundle\PricingBundle\Tests\Functional\DataFixtures\LoadPriceRuleLexemes;
use Oro\Bundle\PricingBundle\Tests\Functional\DataFixtures\LoadProductPrices;
use Oro\Bundle\PricingBundle\Tests\Functional\ProductPriceReference;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\Tests\Functional\DataFixtures\LoadProductData;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;

class ProductPriceEntityListenerTest extends WebTestCase
{
    use MessageQueueExtension,
        ProductPriceReference;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->initClient();
        $this->loadFixtures([
            LoadProductPrices::class,
            LoadPriceRuleLexemes::class
        ]);
        $this->enableMessageBuffering();
    }

    /**
     * @return PriceManager
     */
    private function getPriceManager(): PriceManager
    {
        return $this->getContainer()->get('oro_pricing.manager.price_manager');
    }

    /**
     * @return ShardManager
     */
    private function getShardManager(): ShardManager
    {
        return $this->getContainer()->get('oro_pricing.shard_manager');
    }

    public function testPostPersist()
    {
        /** @var PriceList $priceList */
        $priceList = $this->getReference(LoadPriceLists::PRICE_LIST_2);

        /** @var Product $product */
        $product = $this->getReference(LoadProductData::PRODUCT_4);

        $price = new ProductPrice();
        $price->setProduct($product)
            ->setPriceList($priceList)
            ->setQuantity(1)
            ->setUnit($this->getReference('product_unit.box'))
            ->setPrice(Price::create(42, 'USD'));

        $priceManager = $this->getPriceManager();
        $priceManager->persist($price);
        $priceManager->flush();

        self::assertMessageSent(
            Topics::RESOLVE_PRICE_RULES,
            [
                'product' => [
                    $this->getReference(LoadPriceLists::PRICE_LIST_2)->getId() => [
                        $product->getId()
                    ]
                ]
            ]
        );
    }

    public function testPreUpdate()
    {
        $priceManager = $this->getPriceManager();
        $em = $priceManager->getEntityManager();
        /** @var ProductPriceRepository $repository */
        $repository = $em->getRepository(ProductPrice::class);

        /** @var Product $product */
        $product = $this->getReference(LoadProductData::PRODUCT_1);
        $priceList = $this->getReference(LoadPriceLists::PRICE_LIST_1);
        $prices = $repository->findByPriceList(
            $this->getShardManager(),
            $priceList,
            ['product' => $product, 'priceList' => $priceList, 'currency' => 'USD', 'quantity' => 10]
        );
        /** @var ProductPrice $price */
        $price = $prices[0];
        $price->setPrice(Price::create(12.2, 'USD'));
        $price->setQuantity(20);

        $priceManager->persist($price);
        $em->flush();

        $priceList = $this->getReference(LoadPriceLists::PRICE_LIST_2);
        self::assertMessageSent(
            Topics::RESOLVE_PRICE_RULES,
            [
                'product' => [
                    $priceList->getId() => [
                        $product->getId()
                    ]
                ]
            ]
        );
    }

    public function testPreRemove()
    {
        /** @var Product $product */
        $product = $this->getReference(LoadProductData::PRODUCT_1);

        /** @var ProductPrice $price */
        $price = $this->getPriceByReference('product_price.2');

        $priceManager = $this->getPriceManager();
        $priceManager->remove($price);
        $priceManager->flush();

        self::assertMessageSent(
            Topics::RESOLVE_PRICE_RULES,
            [
                'product' => [
                    $this->getReference(LoadPriceLists::PRICE_LIST_2)->getId() => [
                        $product->getId()
                    ]
                ]
            ]
        );
    }
}
