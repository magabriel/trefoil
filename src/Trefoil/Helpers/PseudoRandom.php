<?php

declare(strict_types=1);

namespace Trefoil\Helpers;

/**
 * Generate a pseudo-random sequence of integers.
 */
class PseudoRandom {

    protected int $randomSeed = 0;

    /**
     * Set the integer to be used a seed for the next generation.
     * 
     * @param int $seed
     * @return void
     */
    public function setRandomSeed(int $seed): void {
        $this->randomSeed = abs($seed) % 9999999 + 1;
        ;
        $this->getRandomInt();
    }

    /**
     * Get a random integeger between a certain range.
     * 
     * @param int $min
     * @param int $max
     * @return int
     */
    public function getRandomInt(int $min = 0, int $max = 9999999): int {
        if ($this->randomSeed == 0) {
            $this->setRandomSeed(mt_rand());
        }
        $this->randomSeed = ($this->randomSeed * 125) % 2796203;

        if ($min > $max) {
            $min = $max;
        }

        return $this->randomSeed % ($max - $min + 1) + $min;
    }
    
    public function getRandomBool(): bool {
        return $this->getRandomInt() === 0;
    }

}
