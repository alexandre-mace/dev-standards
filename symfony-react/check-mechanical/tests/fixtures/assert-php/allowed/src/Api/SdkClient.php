<?php
// \assert($client instanceof SearchClient) ne vérifie rien sous zend.assertions = -1.
if (!$client instanceof SearchClient) {
    throw new \LogicException('Le SDK ne rend plus un SearchClient.');
}
$validator->assert($payload);
Assert::that($value)->notEmpty();
