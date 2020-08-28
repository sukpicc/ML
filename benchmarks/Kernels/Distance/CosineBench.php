<?php

namespace Rubix\ML\Benchmarks\Kernels\Distance;

use Tensor\Matrix;
use Rubix\ML\Kernels\Distance\Cosine;

/**
 * @Groups({"DistanceKernels"})
 * @BeforeMethods({"setUp"})
 */
class CosineBench
{
    protected const NUM_SAMPLES = 10000;

    /**
     * @var array[]
     */
    protected $aSamples;

    /**
     * @var array[]
     */
    protected $bSamples;

    /**
     * @var \Rubix\ML\Kernels\Distance\Cosine
     */
    protected $kernel;

    public function setUpDense() : void
    {
        $this->aSamples = Matrix::gaussian(self::NUM_SAMPLES, 8)->asArray();
        $this->bSamples = Matrix::gaussian(self::NUM_SAMPLES, 8)->asArray();

        $this->kernel = new Cosine();
    }

    /**
     * @Subject
     * @Iterations(5)
     * @BeforeMethods({"setUpDense"})
     * @OutputTimeUnit("milliseconds", precision=3)
     */
    public function computeDense() : void
    {
        array_map([$this->kernel, 'compute'], $this->aSamples, $this->bSamples);
    }

    public function setUpSparse() : void
    {
        $mask = Matrix::rand(self::NUM_SAMPLES, 8)
            ->greater(0.5);

        $this->aSamples = Matrix::gaussian(self::NUM_SAMPLES, 8)
            ->multiply($mask)
            ->asArray();

        $mask = Matrix::rand(self::NUM_SAMPLES, 8)
            ->greater(0.5);

        $this->bSamples = Matrix::gaussian(self::NUM_SAMPLES, 8)
            ->multiply($mask)
            ->asArray();

        $this->kernel = new Cosine();
    }

    /**
     * @Subject
     * @Iterations(5)
     * @BeforeMethods({"setUpSparse"})
     * @OutputTimeUnit("milliseconds", precision=3)
     */
    public function computeSparse() : void
    {
        array_map([$this->kernel, 'compute'], $this->aSamples, $this->bSamples);
    }

    public function setUpVerySparse() : void
    {
        $mask = Matrix::rand(self::NUM_SAMPLES, 8)
            ->greater(0.9);

        $this->aSamples = Matrix::gaussian(self::NUM_SAMPLES, 8)
            ->multiply($mask)
            ->asArray();

        $mask = Matrix::rand(self::NUM_SAMPLES, 8)
            ->greater(0.9);

        $this->bSamples = Matrix::gaussian(self::NUM_SAMPLES, 8)
            ->multiply($mask)
            ->asArray();

        $this->kernel = new Cosine();
    }

    /**
     * @Subject
     * @Iterations(5)
     * @BeforeMethods({"setUpVerySparse"})
     * @OutputTimeUnit("milliseconds", precision=3)
     */
    public function computeVerySparse() : void
    {
        array_map([$this->kernel, 'compute'], $this->aSamples, $this->bSamples);
    }
}
