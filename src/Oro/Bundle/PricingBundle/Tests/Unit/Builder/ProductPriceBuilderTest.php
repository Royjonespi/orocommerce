<?php

namespace Oro\Bundle\PricingBundle\Tests\Unit\Builder;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\PricingBundle\Async\Topics;
use Oro\Bundle\PricingBundle\Builder\ProductPriceBuilder;
use Oro\Bundle\PricingBundle\Compiler\PriceListRuleCompiler;
use Oro\Bundle\PricingBundle\Entity\PriceList;
use Oro\Bundle\PricingBundle\Entity\PriceRule;
use Oro\Bundle\PricingBundle\Entity\ProductPrice;
use Oro\Bundle\PricingBundle\Entity\Repository\ProductPriceRepository;
use Oro\Bundle\PricingBundle\Model\PriceListTriggerHandler;
use Oro\Bundle\PricingBundle\ORM\InsertFromSelectShardQueryExecutor;
use Oro\Bundle\PricingBundle\Sharding\ShardManager;
use Oro\Bundle\ProductBundle\Entity\Product;

class ProductPriceBuilderTest extends \PHPUnit\Framework\TestCase
{
    /** @var ShardManager|\PHPUnit\Framework\MockObject\MockObject */
    private $shardManager;

    /** @var ManagerRegistry|\PHPUnit\Framework\MockObject\MockObject */
    private $registry;

    /** @var InsertFromSelectShardQueryExecutor|\PHPUnit\Framework\MockObject\MockObject */
    private $insertFromSelectQueryExecutor;

    /** @var PriceListRuleCompiler|\PHPUnit\Framework\MockObject\MockObject */
    private $ruleCompiler;

    /** @var PriceListTriggerHandler|\PHPUnit\Framework\MockObject\MockObject */
    private $priceListTriggerHandler;

    /** @var ProductPriceBuilder */
    private $productPriceBuilder;

    protected function setUp()
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->insertFromSelectQueryExecutor = $this->createMock(InsertFromSelectShardQueryExecutor::class);
        $this->ruleCompiler = $this->createMock(PriceListRuleCompiler::class);
        $this->priceListTriggerHandler = $this->createMock(PriceListTriggerHandler::class);
        $this->shardManager = $this->createMock(ShardManager::class);

        $this->productPriceBuilder = new ProductPriceBuilder(
            $this->registry,
            $this->insertFromSelectQueryExecutor,
            $this->ruleCompiler,
            $this->priceListTriggerHandler,
            $this->shardManager
        );
    }

    public function testBuildByPriceListNoRules()
    {
        $priceList = new PriceList();

        /** @var Product|\PHPUnit\Framework\MockObject\MockObject $product * */
        $productId = 1;

        $repo = $this->getRepositoryMock();
        $repo->expects($this->once())
            ->method('deleteGeneratedPrices')
            ->with($this->shardManager, $priceList, [$productId]);

        $this->insertFromSelectQueryExecutor->expects($this->never())
            ->method($this->anything());

        $this->priceListTriggerHandler->expects($this->once())
            ->method('handlePriceListTopic')
            ->with(Topics::RESOLVE_COMBINED_PRICES, $priceList, [$productId]);

        $this->productPriceBuilder->buildByPriceList($priceList, [$productId]);
    }

    public function testBuildByPriceListNoRulesWithoutProduct()
    {
        $priceList = new PriceList();

        $repo = $this->getRepositoryMock();
        $repo->expects($this->once())
            ->method('deleteGeneratedPrices')
            ->with($this->shardManager, $priceList, []);

        $this->insertFromSelectQueryExecutor->expects($this->never())
            ->method($this->anything());

        $this->priceListTriggerHandler->expects($this->once())
            ->method('handlePriceListTopic')
            ->with(Topics::RESOLVE_COMBINED_PRICES, $priceList, []);

        $this->productPriceBuilder->buildByPriceList($priceList);
    }

    public function testBuildByPriceList()
    {
        $priceList = new PriceList();

        /** @var Product|\PHPUnit\Framework\MockObject\MockObject $product * */
        $product = $this->createMock(Product::class);

        $rule1 = new PriceRule();
        $rule1->setPriority(10);
        $rule2 = new PriceRule();
        $rule2->setPriority(20);

        $priceList->setPriceRules(new ArrayCollection([$rule2, $rule1]));

        $fields = ['field1', 'field2'];

        $repo = $this->getRepositoryMock();
        $repo->expects($this->once())
            ->method('deleteGeneratedPrices')
            ->with($this->shardManager, $priceList, [$product]);

        $qb = $this->assertInsertCall($fields, [$rule1, $rule2], [$product]);
        $this->insertFromSelectQueryExecutor->expects($this->exactly(2))
            ->method('execute')
            ->with(
                ProductPrice::class,
                $fields,
                $qb
            );

        $this->priceListTriggerHandler->expects($this->once())
            ->method('handlePriceListTopic')
            ->with(Topics::RESOLVE_COMBINED_PRICES, $priceList, [$product]);

        $this->productPriceBuilder->buildByPriceList($priceList, [$product]);
    }

    public function testBuildByPriceListNoProductsProvided()
    {
        $priceList = new PriceList();

        $rule = new PriceRule();
        $rule->setPriority(10);

        $priceList->setPriceRules(new ArrayCollection([$rule]));

        $fields = ['field1', 'field2'];

        $repo = $this->getRepositoryMock();
        $repo->expects($this->once())
            ->method('deleteGeneratedPrices')
            ->with($this->shardManager, $priceList, []);
        $repo->expects($this->once())
            ->method('getProductsByPriceListAndVersion')
            ->with($this->shardManager, $priceList, $this->isType('int'))
            ->willReturn([[1], [2]]);

        $qb = $this->assertInsertCall($fields, [$rule], []);
        $qb->expects($this->once())
            ->method('expr')
            ->willReturn(new Expr());
        $qb->expects($this->once())
            ->method('addSelect');

        $this->insertFromSelectQueryExecutor->expects($this->once())
            ->method('execute')
            ->with(
                ProductPrice::class,
                array_merge($fields, ['version']),
                $qb
            );

        $this->priceListTriggerHandler->expects($this->exactly(2))
            ->method('handlePriceListTopic')
            ->withConsecutive(
                [Topics::RESOLVE_COMBINED_PRICES, $priceList, [1]],
                [Topics::RESOLVE_COMBINED_PRICES, $priceList, [2]]
            );

        $this->productPriceBuilder->buildByPriceList($priceList, []);
    }

    public function testBuildByPriceListWithoutTriggers()
    {
        $rule = new PriceRule();
        $rule->setPriority(10);

        $priceList = new PriceList();
        $priceList->setPriceRules(new ArrayCollection([$rule]));

        $repository = $this->getRepositoryMock();
        $repository->expects($this->once())
            ->method('deleteGeneratedPrices')
            ->with($this->shardManager, $priceList, []);

        $fields = ['field1', 'field2'];
        $queryBuilder = $this->assertInsertCall($fields, [$rule]);
        $this->insertFromSelectQueryExecutor->expects($this->once())
            ->method('execute')
            ->with(
                ProductPrice::class,
                $fields,
                $queryBuilder
            );

        $this->priceListTriggerHandler->expects($this->never())
            ->method('handlePriceListTopic');

        $this->productPriceBuilder->buildByPriceListWithoutTriggers($priceList);
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject|ProductPriceRepository
     */
    private function getRepositoryMock()
    {
        $repo = $this->createMock(ProductPriceRepository::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('getRepository')
            ->with(ProductPrice::class)
            ->willReturn($repo);

        $this->registry->expects($this->any())
            ->method('getManagerForClass')
            ->with(ProductPrice::class)
            ->willReturn($em);

        return $repo;
    }

    /**
     * @param array $fields
     * @param array $rules
     * @param int[]|Product[]|null $products
     * @return QueryBuilder|\PHPUnit\Framework\MockObject\MockObject
     */
    private function assertInsertCall(array $fields, array $rules, array $products = [])
    {
        $rulesCount = count($rules);

        $qb = $this->createMock(QueryBuilder::class);

        $this->ruleCompiler->expects($this->exactly($rulesCount))
            ->method('getOrderedFields')
            ->willReturn($fields);

        $this->ruleCompiler->expects($this->exactly($rulesCount))
            ->method('compile')
            ->willReturn($qb);

        $c = 1;
        foreach ($rules as $rule) {
            $this->ruleCompiler->expects($this->at($c))
                ->method('compile')
                ->with($rule, $products);
            $c += 2;
        }

        return $qb;
    }
}
