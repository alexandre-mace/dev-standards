<?php
final class Admin
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 4096)]
    // Le mot de passe en clair, le temps du formulaire.
    private ?string $plainPassword = null;
}
