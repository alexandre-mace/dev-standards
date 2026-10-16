<?php

declare(strict_types=1);

final class Code
{
    // `$this->isOpen() && $this->hasPrice()`
    // https://symfony.com/doc/current/the-routing-component-and-the-best-practices.html
    // @phpstan-ignore-next-line the type is checked by the caller and is not nullable
    public int $a = 0;

    // Bundle, payload, webhook.
    public int $b = 0;
}
