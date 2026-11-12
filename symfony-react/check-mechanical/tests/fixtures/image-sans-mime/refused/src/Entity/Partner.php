<?php
final class Partner
{
    #[Assert\Image(maxSize: '5M')]
    private ?File $logoFile = null;
}
