<?php

class GaussianWaveStatistics
{

    private const SQUARE_ROOT_2 = 1.4142135623730951;

    private array $data;
    private float $mean, $stdDev;

    public function __construct(array $data = [])
    {
        $this->data = $data;
        $mean = 0.0;
        $meanSquared = 0.0;

        foreach ($data as $value) {
            $mean += $value;
            $meanSquared += $value * $value;
        }
        $this->mean = $mean / sizeof($data);
        $this->stdDev = sqrt($meanSquared / sizeof($data));
    }

    public function getMean(): float
    {
        return $this->mean;
    }

    public function getStdDev(): float
    {
        return $this->stdDev;
    }

    public function getZScore(mixed $value, bool $key = false): float
    {
        return (($key ? $this->data[$value] : $value) - $this->mean) / $this->stdDev;
    }

    private static function erf(float $x): float
    {
        $t = 1.0 / (1.0 + 0.5 * abs($x));
        $tau = $t * exp(-($x * $x) - 1.26551223 +
                $t * (1.00002368 + $t * (0.37409196 + $t * (0.09678418 +
                            $t * (-0.18628806 + $t * (0.27886807 + $t * (-1.13520398 +
                                        $t * (1.48851587 + $t * (-0.82215223 + $t * (0.17087277))))))))));
        return $x >= 0 ? 1 - $tau : $tau - 1;
    }

    public function getCumulativeProbability(float $zScore): float
    {
        return 0.5 * (1 + self::erf($zScore / self::SQUARE_ROOT_2));
    }

    public function getComplementaryCumulativeProbability(float $zScore): float
    {
        return 1.0 - $this->getCumulativeProbability($zScore);
    }

}
