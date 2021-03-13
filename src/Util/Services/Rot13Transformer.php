<?php

namespace App\Util\Services;

class Rot13Transformer
{
    public function transform(string $value): string
    {
        return str_rot13($value);
    }
}
