<?php

declare(strict_types=1);

namespace Tests\Integration\Interceptors\Mocks;

use Maduser\Argon\Container\Interceptors\Post\Contracts\ValidationInterface;

class BlogPostRequest implements ValidationInterface
{
    public bool $wasValidated = false;

    public function __construct(protected Request $request)
    {
    }

    public function validate(): void
    {
        $this->wasValidated = true;

        if (empty($this->request->get('title'))) {
            throw new \InvalidArgumentException('The title is required.');
        }

        if (strlen($this->request->get('title')) < 3) {
            throw new \InvalidArgumentException('The title must be at least 3 characters.');
        }
    }
}
