<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\Routing\Attribute\Route;

final class NineDigitsController
{
    #[Route('/souscriptions/{id}', requirements: ['id' => '\d{1,9}'])]
    public function show(int $id): void
    {
    }

    #[Route('/fermes/{id<[1-9]\d{0,8}>}')]
    public function farm(int $id): void
    {
    }
}
