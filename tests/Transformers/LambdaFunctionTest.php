<?php

namespace Rubix\ML\Tests\Transformers;

use Rubix\ML\Datasets\Unlabeled;
use Rubix\ML\Transformers\Transformer;
use Rubix\ML\Transformers\LambdaFunction;
use PHPUnit\Framework\TestCase;

/**
 * @group Transformers
 * @covers \Rubix\ML\Transformers\LambdaFunction
 */
class LambdaFunctionTest extends TestCase
{
    /**
     * @var \Rubix\ML\Transformers\LambdaFunction
     */
    protected $transformer;

    /**
     * @before
     */
    protected function setUp() : void
    {
        $callback = function (&$sample, $index, $context) {
            $sample = [$index, array_sum($sample), $context];
        };

        $this->transformer = new LambdaFunction($callback, 'context');
    }

    /**
     * @test
     */
    public function build() : void
    {
        $this->assertInstanceOf(LambdaFunction::class, $this->transformer);
        $this->assertInstanceOf(Transformer::class, $this->transformer);
    }

    /**
     * @test
     */
    public function transform() : void
    {
        $dataset = new Unlabeled([
            [1, 2, 3, 4],
            [40, 20, 30, 10],
            [100, 300, 200, 400],
        ]);

        $dataset->apply($this->transformer);

        $expected = [
            [0, 10, 'context'], [1, 100, 'context'], [2, 1000, 'context'],
        ];

        $this->assertEquals($expected, $dataset->samples());
    }
}
