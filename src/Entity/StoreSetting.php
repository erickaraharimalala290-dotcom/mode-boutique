<?php

namespace App\Entity;

use App\Repository\StoreSettingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StoreSettingRepository::class)]
class StoreSetting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $storeName = null;

    #[ORM\Column(length: 180)]
    private ?string $contactEmail = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 3, options: ['default' => 'MGA'])]
    private string $currency = 'MGA';

    #[ORM\Column(options: ['default' => 0])]
    private int $shippingFee = 0;

    #[ORM\Column(nullable: true)]
    private ?int $freeShippingThreshold = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $deliveryDelay = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStoreName(): ?string
    {
        return $this->storeName;
    }

    public function setStoreName(string $storeName): static
    {
        $this->storeName = $storeName;

        return $this;
    }

    public function getContactEmail(): ?string
    {
        return $this->contactEmail;
    }

    public function setContactEmail(string $contactEmail): static
    {
        $this->contactEmail = $contactEmail;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getShippingFee(): int
    {
        return $this->shippingFee;
    }

    public function setShippingFee(int $shippingFee): static
    {
        $this->shippingFee = $shippingFee;

        return $this;
    }

    public function getFreeShippingThreshold(): ?int
    {
        return $this->freeShippingThreshold;
    }

    public function setFreeShippingThreshold(?int $freeShippingThreshold): static
    {
        $this->freeShippingThreshold = $freeShippingThreshold;

        return $this;
    }

    public function getDeliveryDelay(): ?string
    {
        return $this->deliveryDelay;
    }

    public function setDeliveryDelay(?string $deliveryDelay): static
    {
        $this->deliveryDelay = $deliveryDelay;

        return $this;
    }
}
