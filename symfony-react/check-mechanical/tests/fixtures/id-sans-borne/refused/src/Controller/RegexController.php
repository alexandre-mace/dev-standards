<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\Routing\Attribute\Route;

final class RegexController
{
    #[Route('/admin/user/{userId}', requirements: ['userId' => '\d+'])]
    public function reset(int $userId): void
    {
    }
}
