<?php

namespace App\Service;

use App\Util\Rot13Transformer;
// ...

class TwitterClient
{
    private $transformer;

    public function __construct(Rot13Transformer $transformer)
    {
        $this->transformer = $transformer;
    }

    public function tweet(string $status)
    {
        $transformedStatus = $this->transformer->transform($status);

        return $transformedStatus;
        // ... connect to Twitter and send the encoded status
    }
}