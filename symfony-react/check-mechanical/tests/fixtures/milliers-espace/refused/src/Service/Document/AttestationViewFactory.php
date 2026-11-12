<?php

declare(strict_types=1);

$total = number_format($amount, 2, ',', ' ');
$parts = number_format(Cent::round($a * ($b + 1)), 0, ",", " ").' parts';
