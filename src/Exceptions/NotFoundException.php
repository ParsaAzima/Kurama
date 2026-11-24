<?php

declare(strict_types=1);

namespace Kurama\Exceptions;

final class NotFoundException extends ContainerException
{
    public function __construct(string $id)
    {
        parent::__construct("No entry found for [{$id}] in the container.");
    }
}
