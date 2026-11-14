<?php

declare(strict_types=1);

$sql = <<<SQL
    SELECT id FROM farm
    LIMIT ?
    OFFSET ?
    SQL;
$other = 'SELECT id FROM farm LIMIT ?';
