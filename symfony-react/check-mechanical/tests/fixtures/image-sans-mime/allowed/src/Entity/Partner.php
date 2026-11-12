<?php
final class Partner
{
    #[Assert\Image(
        maxSize: '5M',
        mimeTypes: UploadedImage::MIME_TYPES,
        mimeTypesMessage: 'Une image JPEG, PNG ou WebP (pas de SVG).',
    )]
    private ?File $logoFile = null;

    #[Assert\Image(['maxSize' => '5M', 'mimeTypes' => ['image/jpeg', 'image/png']])]
    private ?File $photoFile = null;

    // #[Assert\Image(maxSize: '5M')] dans un commentaire
    private UploadedImage $image;
}
