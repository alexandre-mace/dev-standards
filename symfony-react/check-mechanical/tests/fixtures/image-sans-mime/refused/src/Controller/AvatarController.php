<?php
public function upload(#[MapUploadedFile([new Assert\Image(maxSize: '5Mi', maxSizeMessage: 'Fichier trop gros (5 Mo).')])] UploadedFile $file) {}
