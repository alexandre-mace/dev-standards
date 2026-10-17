<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class ImportInput
{
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d+$/')]
    public string $diagId;
}
