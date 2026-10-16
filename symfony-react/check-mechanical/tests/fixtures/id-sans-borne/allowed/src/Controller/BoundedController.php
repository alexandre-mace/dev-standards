<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final class BoundedController
{
    // Requirement::DIGITS laissait passer un id au-delà d'int4.
    #[Route('/annonces/{id}', requirements: ['id' => '[1-9][0-9]{0,8}'])]
    public function show(int $id): void
    {
    }

    #[Route('/jeton/{token}', requirements: ['token' => Requirement::UUID, 'code' => '\d{6}'])]
    public function token(string $token): void
    {
    }
}
