<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final class PositiveIntController
{
    #[Route('/structures/{id}', requirements: ['id' => Requirement::POSITIVE_INT])]
    public function show(int $id): void
    {
    }
}
