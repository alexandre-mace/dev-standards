<?php
enum Categorie: string
{
    // La couleur vient du jeton : text-success, jamais #2f6b43.
    public function textClass(): string
    {
        return 'text-success';
    }
}
