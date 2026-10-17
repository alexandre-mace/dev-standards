<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Support\Database\Int4;
use Symfony\Component\Routing\Attribute\Route;

final class Int4Controller
{
    // `\d+` laissait passer `99999999999` jusqu'à PostgreSQL.
    #[Route('/structures/{id}', requirements: ['id' => Int4::ID])]
    public function show(int $id): void
    {
    }

    #[Route('/groupes/{id}', requirements: ['id' => '[1-9]\d{0,8}'])]
    public function group(int $id): void
    {
    }

    #[Route('/fermes/{id<[1-9]\d{0,8}>}')]
    public function farm(int $id): void
    {
    }
}
