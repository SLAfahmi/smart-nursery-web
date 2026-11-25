<?php

namespace App\Support;

/**
 * Akumulator rata-rata / min / maks untuk data yang dibaca baris per baris.
 * Nilai null (sensor error) tidak pernah ditambahkan.
 */
final class RunningStat
{
    private float $sum = 0;
    private int $count = 0;
    private ?float $min = null;
    private ?float $max = null;

    public function add(float $value): void
    {
        $this->sum += $value;
        $this->count++;
        $this->min = $this->min === null ? $value : min($this->min, $value);
        $this->max = $this->max === null ? $value : max($this->max, $value);
    }

    public function avg(int $digits): ?float
    {
        return $this->count ? round($this->sum / $this->count, $digits) : null;
    }

    /** @return array{avg: ?float, min: ?float, max: ?float} */
    public function toArray(int $digits): array
    {
        return [
            'avg' => $this->avg($digits),
            'min' => $this->min === null ? null : round($this->min, $digits),
            'max' => $this->max === null ? null : round($this->max, $digits),
        ];
    }
}
