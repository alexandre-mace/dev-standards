<?php

declare(strict_types=1);

$qb->select('SUM(p.amount) - SUM(p.paid)');
$dql = 'SELECT a FROM App\Entity\A a WHERE a.total - a.paid > 0';
$format = 'd/m/Y - H:i';
$this->logger->info('Building target state - processing user batch');
$label = 'V1 – Salaire animateur';
// Un libellé « V1 - Salaire » prenait un trait d'union pour tiret.
