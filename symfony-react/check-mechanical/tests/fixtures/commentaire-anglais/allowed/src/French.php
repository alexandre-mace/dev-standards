<?php

declare(strict_types=1);

/**
 * Envoie le rappel aux membres dont l'essai se termine cette semaine.
 *
 * @param list<int> $ids
 *
 * @return array{before: string, after: string}
 */
final class French
{
    #[OA\Property(description: 'Results per page: the value falls back to 30 when it is not allowed')]
    public int $perPage = 30;

    public function message(int $id): string
    {
        // Le message cite Airtable tel quel : "Date from Fermes (from the farms table)".
        return \sprintf('result #%d: no response for this email, and it should be checked', $id);
    }
}
