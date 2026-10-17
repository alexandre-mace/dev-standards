<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Validator\Constraints\Regex;

final class PhoneType
{
    public function constraints(): array
    {
        // Hors DTO, un code tout en chiffres n'est pas un identifiant de base.
        return [new Regex(pattern: '/^\d+$/')];
    }
}
