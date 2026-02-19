<?php

class VectorMath
{
    private static ?\FFI $ffi = null;

    public function __construct()
    {
        if (self::$ffi === null) {
            self::$ffi = \FFI::cdef("
                float cblas_sdot(const int N, const float *X, const int incX, const float *Y, const int incY);
                float cblas_snrm2(const int N, const float *X, const int incX);
            ", "libopenblas.so.3");
        }
    }

    public function dotProductBinary(string $vecA, string $vecB, int $len = 3072): float
    {
        // incX/incY = 1 means we process every element consecutively
        return self::$ffi->cblas_sdot($len, $vecA, 1, $vecB, 1);
    }

    public function fullCosineSimilarityBinary(string $vecA, string $vecB, int $len = 3072): float
    {
        $dot = self::$ffi->cblas_sdot($len, $vecA, 1, $vecB, 1);

        // snrm2 returns sqrt(sum(x_i^2)), which is exactly what we need for the denominator
        $normA = self::$ffi->cblas_snrm2($len, $vecA, 1);
        $normB = self::$ffi->cblas_snrm2($len, $vecB, 1);

        $denom = $normA * $normB;

        return $denom == 0.0 ? 0.0 : $dot / $denom;
    }

    public function cosineSimilarityMixed(array $vecA, string $vecB): float
    {
        $binA = pack("f*", ...$vecA);
        return $this->dotProductBinary($binA, $vecB, count($vecA));
    }

    public function fullCosineSimilarityMixed(array $vecA, string $vecB): float
    {
        $binA = pack("f*", ...$vecA);
        return $this->fullCosineSimilarityBinary($binA, $vecB, count($vecA));
    }
}