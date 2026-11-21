<?php

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class ProductCard
{
    public string $name = 'Nom du produit';

    public string $category = 'Nouvelle collection';

    public string $price = '85 000 Ar';

    public ?string $oldPrice = null;

    public ?string $badge = null;

    public string $image = '';

    public string $href = '#';
}