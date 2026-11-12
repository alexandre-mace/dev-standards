<?php
enum Categorie: string
{
    public function textColor(): string
    {
        return match ($this) {
            self::Sol => '#2f6b43',
        };
    }
}
