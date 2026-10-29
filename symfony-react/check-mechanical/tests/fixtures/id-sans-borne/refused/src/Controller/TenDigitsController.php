<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\Routing\Attribute\Route;

final class TenDigitsController
{
    #[Route('/souscriptions/{id}', requirements: ['id' => '\d{1,10}'])]
    public function show(int $id): void
    {
    }
}
