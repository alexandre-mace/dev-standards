<?php
$io->success(sprintf('%s.', Accord::number($n, 'jeton supprimé', 'jetons supprimés')));
// Rendue en href côté React : http(s) seulement.
$io->text('Options (s) et (x) : voir --help');
