<?php

namespace App\Twig;

use App\Repository\StoreSettingRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class StoreSettingExtension extends AbstractExtension
{
    public function __construct(
        private StoreSettingRepository $storeSettingRepository
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('store_setting', [$this, 'getStoreSetting']),
        ];
    }

    public function getStoreSetting()
    {
        return $this->storeSettingRepository->findOneBy([], ['id' => 'ASC']);
    }
}
