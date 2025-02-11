<?php

namespace Rubix\ML\Benchmarks\Persisters\Serializers;

use Rubix\ML\Datasets\Generators\Blob;
use Rubix\ML\Classifiers\KNearestNeighbors;
use Rubix\ML\Datasets\Generators\Agglomerate;
use Rubix\ML\Serializers\RBX;

/**
 * @Groups({"Serializers"})
 * @BeforeMethods({"setUp"})
 */
class RBXBench
{
    protected const TRAINING_SIZE = 10000;

    /**
     * @var \Rubix\ML\Serializers\RBX
     */
    protected $serializer;

    /**
     * @var \Rubix\ML\Persistable
     */
    protected $persistable;

    public function setUp() : void
    {
        $generator = new Agglomerate([
            'Iris-setosa' => new Blob([5.0, 3.42, 1.46, 0.24], [0.35, 0.38, 0.17, 0.1]),
            'Iris-versicolor' => new Blob([5.94, 2.77, 4.26, 1.33], [0.51, 0.31, 0.47, 0.2]),
            'Iris-virginica' => new Blob([6.59, 2.97, 5.55, 2.03], [0.63, 0.32, 0.55, 0.27]),
        ]);

        $training = $generator->generate(self::TRAINING_SIZE);

        $estimator = new KNearestNeighbors(5, true);

        $estimator->train($training);

        $this->persistable = $estimator;

        $this->serializer = new RBX();
    }

    /**
     * @Subject
     * @revs(10)
     * @Iterations(5)
     * @OutputTimeUnit("milliseconds", precision=3)
     */
    public function serializeDeserialize() : void
    {
        $encoding = $this->serializer->serialize($this->persistable);

        $persistable = $this->serializer->deserialize($encoding);
    }
}
