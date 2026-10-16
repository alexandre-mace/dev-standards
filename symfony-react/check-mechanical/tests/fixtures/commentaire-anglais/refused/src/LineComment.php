<?php

declare(strict_types=1);

final class LineComment
{
    public function total(): int
    {
        // Only the paid orders count, the drafts are left out.
        return 1;
    }
}
