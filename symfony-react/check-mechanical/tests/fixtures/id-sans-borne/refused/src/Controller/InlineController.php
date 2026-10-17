<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\Routing\Attribute\Route;

final class InlineController
{
    #[Route('/fermes/{id<[1-9][0-9]*>}')]
    public function show(int $id): void
    {
    }
}
