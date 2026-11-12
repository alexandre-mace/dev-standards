<?php

declare(strict_types=1);

$total = number_format($amount, 2, ',', "\u{202f}");
$csv = number_format($amount, 2, '.', '');
$brut = number_format($amount, 2);
// number_format($amount, 2, ',', ' ') coupait le nombre en fin de ligne.
