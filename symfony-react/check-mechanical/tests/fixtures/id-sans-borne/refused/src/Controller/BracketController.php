<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\Routing\Attribute\Route;

final class BracketController
{
    #[Route('/etape/{stepId}', requirements: ['stepId' => '[0-9]+'])]
    public function step(int $stepId): void
    {
    }
}
