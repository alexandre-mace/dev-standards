<?php
$builder
    ->add('currentPassword', PasswordType::class, [
        'constraints' => [
            new NotBlank(message: 'Saisis ton mot de passe actuel.'),
            new Length(max: 4096),
        ],
    ]);
