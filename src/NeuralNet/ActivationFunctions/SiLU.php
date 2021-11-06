<?php

namespace Rubix\ML\NeuralNet\ActivationFunctions;

use Tensor\Matrix;

/**
 * SiLU
 *
 * *Sigmoid-weighted Linear Unit* (SiLU) is a smooth and non-monotonic rectified activation function. The inputs
 * are weighted by the [Sigmoid](sigmoid.md) activation function acting as a self-gating mechanism. In addition,
 * an inherent global minimum functions as an implicit regularizer.
 *
 * References:
 * [1] S. Elwing et al. (2017). Sigmoid-Weighted Linear Units for Neural Network Function
 * Approximation in Reinforcement Learning.
 * [2] P. Ramachandran er al. (2017). Swish: A Self-gated Activation Function.
 *
 * @category    Machine Learning
 * @package     Rubix/ML
 * @author      Andrew DalPino
 */
class SiLU implements ActivationFunction
{
    /**
     * The sigmoid activation function.
     *
     * @var \Rubix\ML\NeuralNet\ActivationFunctions\Sigmoid
     */
    protected $sigmoid;

    public function __construct()
    {
        $this->sigmoid = new Sigmoid();
    }

    /**
     * Compute the output value.
     *
     * @param \Tensor\Matrix $z
     * @return \Tensor\Matrix
     */
    public function compute(Matrix $z) : Matrix
    {
        return $this->sigmoid->compute($z)->multiply($z);
    }

    /**
     * Calculate the derivative of the activation function at a given output.
     *
     * @param \Tensor\Matrix $z
     * @param \Tensor\Matrix $computed
     * @return \Tensor\Matrix
     */
    public function differentiate(Matrix $z, Matrix $computed) : Matrix
    {
        $ones = Matrix::ones(...$computed->shape());

        return $computed->divide($z)
            ->multiply($ones->subtract($computed))
            ->add($computed);
    }

    /**
     * Return the string representation of the object.
     *
     * @return string
     */
    public function __toString() : string
    {
        return 'SiLU';
    }
}
