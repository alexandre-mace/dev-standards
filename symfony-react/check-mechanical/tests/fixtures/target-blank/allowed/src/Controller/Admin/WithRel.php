<?php

declare(strict_types=1);

$field->setHtmlAttributes(['target' => '_blank', 'rel' => 'noopener noreferrer']);
// Un lien target="_blank" sans rel laissait la page ouverte agir sur celle-ci.
return sprintf('<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>', $url, $label);
