<?php

namespace App\Twig;

use App\Catalog\ImageFinder;
use Twig\Attribute\AsTwigFilter;

final class CommonsFilter
{
    /** {{ model.imageUrl|commons_width(500) }} : la vignette Commons à cette largeur */
    #[AsTwigFilter('commons_width')]
    public function width(string $url, int $width): string
    {
        return ImageFinder::resize($url, $width);
    }
}
