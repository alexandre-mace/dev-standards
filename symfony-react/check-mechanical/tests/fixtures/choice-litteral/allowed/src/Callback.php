<?php
declare(strict_types=1);
#[Assert\Choice(callback: [Status::class, 'values'])]
public string $a;
