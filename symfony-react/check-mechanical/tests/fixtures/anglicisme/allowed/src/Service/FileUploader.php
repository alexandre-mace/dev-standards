<?php

declare(strict_types=1);

// l'upload passe par VichUploader ; un fichier uploadé reste privé.
$path = $this->uploader->upload($file, 'uploads/');
$this->logger->info('Document uploadé');
