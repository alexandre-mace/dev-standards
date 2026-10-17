<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class MonthInput
{
    #[Assert\Regex(pattern: '/^\d{4}-\d{2}$/')]
    public string $month;

    #[Assert\Regex(pattern: '/^[1-9]\d{0,8}$/')]
    public string $diagId;
}
