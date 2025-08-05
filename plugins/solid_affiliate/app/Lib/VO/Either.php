<?php

namespace SolidAffiliate\Lib\VO;

/**
 * @template T
 * 
 * @psalm-type Left = array<string>
 * @psalm-type Right = mixed
 * 
 */
class Either
{

    /** @var array<string> */
    public $left;

    /** @var T */
    public $right;

    /** @var bool */
    public $isRight;

    /** @var bool */
    public $isLeft;

    /**
     * @param array<string> $left
     * @param T $right
     * @param bool $isRight
     */
    public function __construct($left, $right, $isRight)
    {
        $this->left = $left;
        $this->right = $right;
        $this->isRight = $isRight;
        $this->isLeft = !$isRight;
    }
}
