<?php

namespace Rubix\ML\Tests\Classifiers;

use Rubix\ML\Estimator;
use Rubix\ML\Persistable;
use Rubix\ML\Datasets\Unlabeled;
use Rubix\ML\Datasets\Generators\Blob;
use Rubix\ML\Classifiers\DummyClassifier;
use Rubix\ML\Datasets\Generators\Agglomerate;
use Rubix\ML\Other\Strategies\PopularityContest;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;
use RuntimeException;

class DummyClassifierTest extends TestCase
{
    const TRAIN_SIZE = 100;
    const TEST_SIZE = 5;

    protected $estimator;

    protected $generator;

    public function setUp()
    {
        $this->generator = new Agglomerate([
            'red' => new Blob([255, 0, 0], 3.),
            'green' => new Blob([0, 128, 0], 1.),
            'blue' => new Blob([0, 0, 255], 2.),
        ], [0.7, 0.1, 0.2]);

        $this->estimator = new DummyClassifier(new PopularityContest());
    }

    public function test_build_classifier()
    {
        $this->assertInstanceOf(DummyClassifier::class, $this->estimator);
        $this->assertInstanceOf(Estimator::class, $this->estimator);
        $this->assertInstanceOf(Persistable::class, $this->estimator);
    }

    public function test_estimator_type()
    {
        $this->assertEquals(Estimator::CLASSIFIER, $this->estimator->type());
    }

    public function test_train_predict()
    {
        $testing = $this->generator->generate(self::TEST_SIZE);

        $this->estimator->train($this->generator->generate(self::TRAIN_SIZE));

        foreach ($this->estimator->predict($testing) as $i => $prediction) {
            $this->assertContains($prediction, $testing->possibleOutcomes());
        }
    }
}
