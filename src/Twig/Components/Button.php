<?php

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Button
{
    public string $variant = 'primary';

    public string $size = 'md';

    public ?string $href = null;

    public string $type = 'button';

    public bool $disabled = false;
}