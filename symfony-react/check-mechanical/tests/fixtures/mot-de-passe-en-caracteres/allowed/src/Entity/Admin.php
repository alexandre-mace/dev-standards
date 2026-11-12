<?php
final class Admin
{
    #[Assert\Length(min: 12, minMessage: 'Mot de passe : 12 caractères minimum.')]
    #[Assert\Length(max: PasswordHasherInterface::MAX_PASSWORD_LENGTH, countUnit: Assert\Length::COUNT_BYTES)]
    private ?string $plainPassword = null;

    #[Assert\Length(max: 180)]
    private string $email = '';
}
