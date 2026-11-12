<?php

declare(strict_types=1);

// Le statut « signé » ne revient pas en arrière.
$this->addFlash('danger', sprintf("Passage de «\u{a0}%s\u{a0}» impossible.", $from));
$this->addFlash('danger', sprintf('Passage de « %s » impossible.', $from));
$this->logger->info(sprintf('Passage de « %s » refusé', $from));
$sql = <<<'SQL'
    -- Étape « identifié » du funnel, déduite et non tracée.
    SELECT 1
    SQL;
