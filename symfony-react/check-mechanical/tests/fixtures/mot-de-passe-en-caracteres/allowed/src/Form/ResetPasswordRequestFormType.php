<?php
$builder
    ->add('email', EmailType::class, [
        'constraints' => [new Length(max: 180)],
    ])
    ->add('newPassword', PasswordType::class, [
        'constraints' => [new Length(max: 4096, countUnit: Length::COUNT_BYTES)],
    ]);
