<?php

declare(strict_types=1);

$lengths = array_map(strlen(...), $words);
$trace = mb_substr($trace, 0, 1500)."\n... (trace tronquée)";
$this->logger->warning('Client session lost, creating new client...');
