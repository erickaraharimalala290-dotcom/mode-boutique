<?php

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class CategoryCard
{
    /**
     * Nom de la catégorie (ex: "Femme", "Homme", "Chaussures")
     */
    public string $name = 'Catégorie';

    /**
     * URL de l'image de fond (Unsplash ou locale)
     */
    public string $image = '';

    /**
     * URL vers laquelle le clic mène (ex: path('app_category'))
     */
    public string $url = '#';

    /**
     * Petite description sous le nom (optionnelle)
     */
    public ?string $description = null;
}