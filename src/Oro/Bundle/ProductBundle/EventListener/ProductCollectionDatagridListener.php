<?php

namespace Oro\Bundle\ProductBundle\EventListener;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use Oro\Bundle\DataGridBundle\Datagrid\DatagridInterface;
use Oro\Bundle\DataGridBundle\Datagrid\NameStrategyInterface;
use Oro\Bundle\DataGridBundle\Datasource\Orm\OrmDatasource;
use Oro\Bundle\DataGridBundle\Event\BuildAfter;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\Service\ProductCollectionDefinitionConverter;
use Oro\Bundle\SegmentBundle\Entity\Manager\SegmentManager;
use Oro\Bundle\SegmentBundle\Entity\Segment;
use Oro\Bundle\SegmentBundle\Entity\SegmentType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Filter Product Collection datagrid by segment definition from request
 */
class ProductCollectionDatagridListener
{
    const SEGMENT_DEFINITION_PARAMETER_KEY = 'sd_';
    const DEFINITION_KEY = 'definition';
    const INCLUDED_KEY = 'included';
    const EXCLUDED_KEY = 'excluded';

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var SegmentManager
     */
    private $segmentManager;

    /**
     * @var ManagerRegistry
     */
    private $registry;

    /**
     * @var NameStrategyInterface
     */
    private $nameStrategy;

    /**
     * @var ProductCollectionDefinitionConverter
     */
    private $definitionConverter;

    /**
     * @param RequestStack $requestStack
     * @param SegmentManager $segmentManager
     * @param ManagerRegistry $registry
     * @param NameStrategyInterface $nameStrategy
     * @param ProductCollectionDefinitionConverter $definitionConverter
     */
    public function __construct(
        RequestStack $requestStack,
        SegmentManager $segmentManager,
        ManagerRegistry $registry,
        NameStrategyInterface $nameStrategy,
        ProductCollectionDefinitionConverter $definitionConverter
    ) {
        $this->requestStack = $requestStack;
        $this->segmentManager = $segmentManager;
        $this->registry = $registry;
        $this->nameStrategy = $nameStrategy;
        $this->definitionConverter = $definitionConverter;
    }

    /**
     * @param BuildAfter $event
     */
    public function onBuildAfter(BuildAfter $event)
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        $dataGrid = $event->getDatagrid();
        $dataSource = $dataGrid->getDatasource();
        if (!$dataSource instanceof OrmDatasource) {
            return;
        }

        $requestData = $this->getRequestData($dataGrid, $request);
        $dataGridQueryBuilder = $dataSource->getQueryBuilder();

        $definition = json_decode($requestData[self::DEFINITION_KEY], true);
        if (empty($definition['filters']) && !$requestData[self::INCLUDED_KEY]) {
            $dataGridQueryBuilder->andWhere('1 = 0');
            return;
        }

        $segmentDefinition = $this->getSegmentDefinition($requestData);
        if ($segmentDefinition) {
            $this->addFilterBySegment($dataGridQueryBuilder, $segmentDefinition);
        }
    }

    /**
     * @param QueryBuilder $dataGridQueryBuilder
     * @param string $segmentDefinition
     */
    private function addFilterBySegment(QueryBuilder $dataGridQueryBuilder, $segmentDefinition)
    {
        /** @var EntityManager $em */
        $em = $this->registry->getManagerForClass(SegmentType::class);
        $dynamicSegmentType = $em->getReference(SegmentType::class, SegmentType::TYPE_DYNAMIC);

        $productSegment = new Segment();
        $productSegment->setDefinition($segmentDefinition)
            ->setEntity(Product::class)
            ->setType($dynamicSegmentType);

        $this->segmentManager->filterBySegment($dataGridQueryBuilder, $productSegment);
    }

    /**
     * @param array $requestData
     *
     * @return null|string
     */
    private function getSegmentDefinition(array $requestData)
    {
        return $this->definitionConverter->putConditionsInDefinition(
            $requestData[self::DEFINITION_KEY],
            $requestData[self::EXCLUDED_KEY],
            $requestData[self::INCLUDED_KEY]
        );
    }


    /**
     * @param DatagridInterface $dataGrid
     * @param Request $request
     *
     * @return array
     */
    private function getRequestData(DatagridInterface $dataGrid, Request $request): array
    {
        $scope = $dataGrid->getScope();
        $gridFullName = $this->nameStrategy->buildGridFullName($dataGrid->getName(), $scope);
        $dataParameterName = self::SEGMENT_DEFINITION_PARAMETER_KEY . $gridFullName;

        return [
            self::DEFINITION_KEY => $request->get($dataParameterName),
            self::INCLUDED_KEY => $request->get($dataParameterName . ':incl'),
            self::EXCLUDED_KEY => $request->get($dataParameterName . ':excl'),
        ];
    }
}
