<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\Routing\Attribute\Route;

final class NonZeroTenDigitsController
{
    #[Route('/fermes/{id<[1-9]\d{0,9}>}')]
    public function show(int $id): void
    {
    }
}
