<?php

namespace Oro\Component\ExpressionLanguage\Tests;

use Doctrine\Common\Collections\ArrayCollection;
use Oro\Bundle\OrderBundle\Entity\OrderLineItem;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\Entity\ProductUnit;
use Oro\Bundle\ProductBundle\Entity\ProductUnitPrecision;
use Oro\Component\ExpressionLanguage\Lexer;
use Oro\Component\ExpressionLanguage\Parser;
use Oro\Component\Testing\Unit\EntityTrait;

class ParserTests extends \PHPUnit_Framework_TestCase
{
    use EntityTrait;

    /**
     * @var Parser
     */
    protected $parser;

    /**
     * @dataProvider parseDataProvider
     *
     * @param string $expression
     * @param array $values
     * @param bool $expectedResult
     */
    public function testParse($expression, array $values, $expectedResult)
    {
        $functions = [
            'count' => [
                'compiler' => function ($field) {
                    return sprintf('count(%s)', $field);
                },
                'evaluator' => function ($arguments, $field) {
                    return count($field);
                }
            ]
        ];
        $this->parser = new Parser($functions);
        $lexer = new Lexer();
        $tokens = $lexer->tokenize($expression);
        $nodes = $this->parser->parse($tokens, array_keys($values));
        $this->assertEquals($expectedResult, $nodes->evaluate($functions, $values));
    }

    /**
     * @return array
     */
    public function parseDataProvider()
    {
        //TODO: remove dependencies to bundles
        return [
            'simple' => [
                'expression' => 'product.status = \'enabled\''
                ,
                'values' => [
                    'product' => $this->getEntity(Product::class, [
                        'status' => 'enabled',
                    ])
                ],
                'expectedResult' => true,
            ],
            'extended' => [
                'expression' => <<<EXPR
lineItems.all(
    lineItem.product.status in ['enabled'] 
    and lineItem.product.unitPrecisions.any(
        unitPrecision.unit.code = 'set'
    )
)
and 
count(lineItems) > 1
EXPR
                ,
                'values' => [
                    'lineItems' => new ArrayCollection([
                        $this->getEntity(OrderLineItem::class, [
                            'product' => $this->getEntity(Product::class, [
                                'sku' => 'QWE122',
                                'status' => Product::STATUS_ENABLED,
                                'unitPrecisions' => new ArrayCollection([
                                    $this->getEntity(ProductUnitPrecision::class, [
                                        'unit' => $this->getEntity(ProductUnit::class, [
                                            'code' => 'set',
                                        ]),
                                    ]),
                                    $this->getEntity(ProductUnitPrecision::class, [
                                        'unit' => $this->getEntity(ProductUnit::class, [
                                            'code' => 'item',
                                        ]),
                                    ])
                                ])
                            ]),
                        ]),
                        $this->getEntity(OrderLineItem::class, [
                            'product' => $this->getEntity(Product::class, [
                                'sku' => 'QWE123',
                                'status' => Product::STATUS_ENABLED,
                                'unitPrecisions' => new ArrayCollection([
                                    $this->getEntity(ProductUnitPrecision::class, [
                                        'unit' => $this->getEntity(ProductUnit::class, [
                                            'code' => 'set',
                                        ]),
                                    ])
                                ])
                            ])
                        ]),
                    ]),
                ],
                'expectedResult' => true,
            ]
        ];
    }
}
