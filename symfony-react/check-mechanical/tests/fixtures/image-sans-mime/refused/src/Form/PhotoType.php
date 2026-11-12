<?php
$builder->add('photo', FileType::class, [
    'constraints' => [
        new Image(
            maxSize: '2M',
        ),
    ],
]);
