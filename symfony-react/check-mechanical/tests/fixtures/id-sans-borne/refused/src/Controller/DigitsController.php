<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final class DigitsController
{
    #[Route('/annonces/{id}', requirements: ['id' => Requirement::DIGITS])]
    public function show(int $id): void
    {
    }
}
