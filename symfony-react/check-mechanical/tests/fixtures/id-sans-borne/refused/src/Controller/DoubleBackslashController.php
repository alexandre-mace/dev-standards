<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\Routing\Attribute\Route;

final class DoubleBackslashController
{
    #[Route('/dispositif/groupes/{id}', requirements: ['id' => '\\d+'])]
    public function show(int $id): void
    {
    }
}
