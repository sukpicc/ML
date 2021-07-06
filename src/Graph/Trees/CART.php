<?php

namespace Rubix\ML\Graph\Trees;

use Rubix\ML\Helpers\Stats;
use Rubix\ML\Datasets\Labeled;
use Rubix\ML\Datasets\Dataset;
use Rubix\ML\Graph\Nodes\Split;
use Rubix\ML\Graph\Nodes\Outcome;
use Rubix\ML\Graph\Nodes\Decision;
use Rubix\ML\Exceptions\InvalidArgumentException;
use Rubix\ML\Exceptions\RuntimeException;
use IteratorAggregate;
use Generator;

use function Rubix\ML\linspace;
use function count;
use function log;
use function sqrt;
use function array_slice;
use function array_pop;
use function array_unique;
use function array_rand;
use function is_string;
use function is_int;

/**
 * CART
 *
 * *Classification and Regression Tree* or CART is a binary search tree that
 * uses *decision* nodes at every split in the training data to locate a
 * purified leaf node.
 *
 * @category    Machine Learning
 * @package     Rubix/ML
 * @author      Andrew DalPino
 *
 * @implements IteratorAggregate<int,\Rubix\ML\Graph\Nodes\Decision>
 */
abstract class CART implements IteratorAggregate
{
    /**
     * The glyph that denotes a branch of the tree.
     *
     * @var string
     */
    protected const BRANCH_INDENTER = '├───';

    /**
     * The maximum depth of a branch before it is forced to terminate.
     *
     * @var int
     */
    protected int $maxHeight;

    /**
     * The maximum number of samples that a leaf node can contain.
     *
     * @var int
     */
    protected int $maxLeafSize;

    /**
     * The minimum increase in purity necessary for a node not to be post pruned.
     *
     * @var float
     */
    protected float $minPurityIncrease;

    /**
     * The maximum number of features to consider when determining a split.
     *
     * @var int|null
     */
    protected ?int $maxFeatures = null;

    /**
     * The root node of the tree.
     *
     * @var \Rubix\ML\Graph\Nodes\Split|null
     */
    protected ?\Rubix\ML\Graph\Nodes\Split $root = null;

    /**
     * The number of feature columns in the training set.
     *
     * @var int
     */
    protected ?int $featureCount = null;

    /**
     * @internal
     *
     * @param int $maxHeight
     * @param int $maxLeafSize
     * @param int|null $maxFeatures
     * @param float $minPurityIncrease
     * @throws \InvalidArgumentException
     */
    public function __construct(
        int $maxHeight,
        int $maxLeafSize,
        float $minPurityIncrease,
        ?int $maxFeatures
    ) {
        if ($maxHeight < 1) {
            throw new InvalidArgumentException('Tree must have'
                . " depth greater than 0, $maxHeight given.");
        }

        if ($maxLeafSize < 1) {
            throw new InvalidArgumentException('At least one sample is'
                . " required to form a leaf node, $maxLeafSize given.");
        }

        if ($minPurityIncrease < 0.0) {
            throw new InvalidArgumentException('Min purity increase'
                . " must be greater than 0, $minPurityIncrease given.");
        }

        if (isset($maxFeatures) and $maxFeatures < 1) {
            throw new InvalidArgumentException('Tree must consider at least 1'
                . " feature to determine a split, $maxFeatures given.");
        }

        $this->maxHeight = $maxHeight;
        $this->maxLeafSize = $maxLeafSize;
        $this->minPurityIncrease = $minPurityIncrease;
        $this->maxFeatures = $maxFeatures;
    }

    /**
     * Return the number of levels in the tree.
     *
     * @return int|null
     */
    public function height() : ?int
    {
        return $this->root ? $this->root->height() : null;
    }

    /**
     * Return a factor that quantifies the skewness of the distribution of nodes in the tree.
     *
     * @return int|null
     */
    public function balance() : ?int
    {
        return $this->root ? $this->root->balance() : null;
    }

    /**
     * Is the tree bare?
     *
     * @internal
     *
     * @return bool
     */
    public function bare() : bool
    {
        return !$this->root;
    }

    /**
     * Insert a root node and recursively split the dataset a terminating condition is met.
     *
     * @internal
     *
     * @param \Rubix\ML\Datasets\Labeled $dataset
     * @throws \InvalidArgumentException
     */
    public function grow(Labeled $dataset) : void
    {
        $n = $dataset->numFeatures();

        $this->featureCount = $n;

        $this->root = $this->split($dataset);

        $stack = [[$this->root, 1]];

        while ([$current, $depth] = array_pop($stack)) {
            [$left, $right] = $current->groups();

            $current->cleanup();

            ++$depth;

            if ($left->empty() or $right->empty()) {
                $node = $this->terminate($left->merge($right));

                $current->attachLeft($node);
                $current->attachRight($node);

                continue;
            }

            if ($depth >= $this->maxHeight) {
                $current->attachLeft($this->terminate($left));
                $current->attachRight($this->terminate($right));

                continue;
            }

            if ($left->numSamples() > $this->maxLeafSize) {
                $leftNode = $this->split($left);
            } else {
                $leftNode = $this->terminate($left);
            }

            if ($right->numSamples() > $this->maxLeafSize) {
                $rightNode = $this->split($right);
            } else {
                $rightNode = $this->terminate($right);
            }

            $current->attachLeft($leftNode);
            $current->attachRight($rightNode);

            if ($current->purityIncrease() >= $this->minPurityIncrease) {
                if ($leftNode instanceof Split) {
                    $stack[] = [$leftNode, $depth];
                }

                if ($rightNode instanceof Split) {
                    $stack[] = [$rightNode, $depth];
                }
            } else {
                if ($leftNode instanceof Split) {
                    $current->attachLeft($this->terminate($left));
                }

                if ($rightNode instanceof Split) {
                    $current->attachRight($this->terminate($right));
                }
            }
        }
    }

    /**
     * Search the decision tree for a leaf node and return it.
     *
     * @internal
     *
     * @param list<string|int|float> $sample
     * @return \Rubix\ML\Graph\Nodes\Outcome|null
     */
    public function search(array $sample) : ?Outcome
    {
        $current = $this->root;

        while ($current) {
            if ($current instanceof Split) {
                $value = $current->value();

                if (is_string($value)) {
                    if ($sample[$current->column()] === $value) {
                        $current = $current->left();
                    } else {
                        $current = $current->right();
                    }
                } else {
                    if ($sample[$current->column()] < $value) {
                        $current = $current->left();
                    } else {
                        $current = $current->right();
                    }
                }

                continue;
            }

            if ($current instanceof Outcome) {
                return $current;
            }
        }

        return null;
    }

    /**
     * Return the importance scores of each feature column of the training set.
     *
     * @throws \RuntimeException
     * @return float[]
     */
    public function featureImportances() : array
    {
        if ($this->bare() or !$this->featureCount) {
            throw new RuntimeException('Tree has not been constructed.');
        }

        $importances = array_fill(0, $this->featureCount, 0.0);

        foreach ($this as $node) {
            if ($node instanceof Split) {
                $importances[$node->column()] += $node->purityIncrease();
            }
        }

        return $importances;
    }

    /**
     * Return a generator for all the nodes in the tree starting at the root and traversing depth first.
     *
     * @return \Generator<\Rubix\ML\Graph\Nodes\Decision>
     */
    public function getIterator() : Generator
    {
        $stack = [$this->root];

        while ($current = array_pop($stack)) {
            yield $current;

            foreach ($current->children() as $child) {
                if ($child instanceof Decision) {
                    $stack[] = $child;
                }
            }
        }
    }

    /**
     * Terminate a branch with an outcome node.
     *
     * @param \Rubix\ML\Datasets\Labeled $dataset
     * @return \Rubix\ML\Graph\Nodes\Outcome
     */
    abstract protected function terminate(Labeled $dataset);

    /**
     * Calculate the impurity of a set of labels.
     *
     * @param list<string|int> $labels
     * @return float
     */
    abstract protected function impurity(array $labels) : float;

    /**
     * Greedy algorithm to choose the best split point for a given dataset.
     *
     * @param \Rubix\ML\Datasets\Labeled $dataset
     * @return \Rubix\ML\Graph\Nodes\Split
     */
    protected function split(Labeled $dataset) : Split
    {
        [$m, $n] = $dataset->shape();

        $maxFeatures = $this->maxFeatures ?? (int) round(sqrt($n));

        $columns = array_fill(0, $dataset->numFeatures(), null);

        $columns = (array) array_rand($columns, min($maxFeatures, count($columns)));

        $bestColumn = $bestValue = $bestGroups = null;
        $bestImpurity = INF;

        foreach ($columns as $column) {
            $values = $dataset->feature($column);

            $type = $dataset->featureType($column);

            if ($type->isContinuous()) {
                if (!isset($q)) {
                    $bins = (int) round(3.0 + log($m, 2.0));

                    $q = linspace(0.0, 1.0, $bins);

                    $q = array_slice($q, 1, -1);
                }

                $values = Stats::quantiles($values, $q);
            } else {
                $values = array_unique($values);

                if (count($values) === 2) {
                    $values = array_slice($values, 0, 1);
                }
            }

            foreach ($values as $value) {
                $groups = $dataset->splitByFeature($column, $value);

                $impurity = $this->splitImpurity($groups);

                if ($impurity < $bestImpurity) {
                    $bestColumn = $column;
                    $bestValue = $value;
                    $bestGroups = $groups;
                    $bestImpurity = $impurity;
                }

                if ($impurity <= 0.0) {
                    break 2;
                }
            }
        }

        if (!is_int($bestColumn) or $bestValue === null or $bestGroups === null) {
            throw new RuntimeException('Could not split dataset.');
        }

        return new Split(
            $bestColumn,
            $bestValue,
            $bestGroups,
            $bestImpurity,
            $m
        );
    }

    /**
     * Calculate the impurity of a given split.
     *
     * @param array{\Rubix\ML\Datasets\Labeled,\Rubix\ML\Datasets\Labeled} $groups
     * @return float
     */
    protected function splitImpurity(array $groups) : float
    {
        $n = array_sum(array_map('count', $groups));

        $impurity = 0.0;

        foreach ($groups as $dataset) {
            $nHat = $dataset->numSamples();

            if ($nHat <= 1) {
                continue;
            }

            $impurity += ($nHat / $n) * $this->impurity($dataset->labels());
        }

        return $impurity;
    }
}
