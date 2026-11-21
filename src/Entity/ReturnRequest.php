<?php

namespace App\Entity;

use App\Repository\ReturnRequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReturnRequestRepository::class)]
#[ORM\UniqueConstraint(
    name: 'UNIQ_RETURN_REQUEST_NUMBER',
    fields: ['requestNumber']
)]
class ReturnRequest
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_COMPLETED = 'completed';

    public const ALLOWED_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_RECEIVED,
        self::STATUS_REJECTED,
        self::STATUS_COMPLETED,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30)]
    private ?string $requestNumber = null;

    #[ORM\Column(length: 20)]
    private ?string $type = 'exchange';

    #[ORM\Column(length: 100)]
    private ?string $reason = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private ?int $quantity = 1;

    #[ORM\Column(length: 20)]
    private ?string $status = self::STATUS_PENDING;

    #[ORM\Column]
    private ?\DateTimeImmutable $requestedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $receivedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $adminNote = null;

    // Produit et variante souhaités pour le remplacement.
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Product $replacementProduct = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ProductVariant $replacementVariant = null;

    // Prix unitaire du produit de remplacement au moment de la demande.
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $replacementUnitPrice = null;

    // Différence totale de prix calculée pour la quantité demandée.
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $priceDifference = null;

    // Frais de livraison supplémentaires liés à l'échange.
    #[ORM\Column(
        type: Types::DECIMAL,
        precision: 12,
        scale: 2,
        options: ['default' => '0.00']
    )]
    private string $exchangeDeliveryFee = '0.00';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?OrderItem $orderItem = null;

    public function __construct()
    {
        $this->requestedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRequestNumber(): ?string
    {
        return $this->requestNumber;
    }

    public function setRequestNumber(string $requestNumber): static
    {
        $this->requestNumber = $requestNumber;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(string $reason): static
    {
        $this->reason = $reason;

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

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        if ($quantity < 1) {
            throw new \InvalidArgumentException(
                'La quantité doit être supérieure ou égale à 1.'
            );
        }

        $this->quantity = $quantity;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new \InvalidArgumentException(
                'Le statut de la demande est invalide.'
            );
        }

        $this->status = $status;

        return $this;
    }

    public function getRequestedAt(): ?\DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function setRequestedAt(\DateTimeImmutable $requestedAt): static
    {
        $this->requestedAt = $requestedAt;

        return $this;
    }

    public function getReceivedAt(): ?\DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function setReceivedAt(?\DateTimeImmutable $receivedAt): static
    {
        $this->receivedAt = $receivedAt;

        return $this;
    }

    public function getProcessedAt(): ?\DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function setProcessedAt(?\DateTimeImmutable $processedAt): static
    {
        $this->processedAt = $processedAt;

        return $this;
    }

    public function getAdminNote(): ?string
    {
        return $this->adminNote;
    }

    public function setAdminNote(?string $adminNote): static
    {
        $this->adminNote = $adminNote;

        return $this;
    }

    public function getReplacementProduct(): ?Product
    {
        return $this->replacementProduct;
    }

    public function setReplacementProduct(?Product $replacementProduct): static
    {
        $this->replacementProduct = $replacementProduct;

        return $this;
    }

    public function getReplacementVariant(): ?ProductVariant
    {
        return $this->replacementVariant;
    }

    public function setReplacementVariant(?ProductVariant $replacementVariant): static
    {
        $this->replacementVariant = $replacementVariant;

        return $this;
    }

    public function getReplacementUnitPrice(): ?string
    {
        return $this->replacementUnitPrice;
    }

    public function setReplacementUnitPrice(?string $replacementUnitPrice): static
    {
        $this->replacementUnitPrice = $replacementUnitPrice;

        return $this;
    }

    public function getPriceDifference(): ?string
    {
        return $this->priceDifference;
    }

    public function setPriceDifference(?string $priceDifference): static
    {
        $this->priceDifference = $priceDifference;

        return $this;
    }

    public function getExchangeDeliveryFee(): string
    {
        return $this->exchangeDeliveryFee;
    }

    public function setExchangeDeliveryFee(string $exchangeDeliveryFee): static
    {
        $this->exchangeDeliveryFee = $exchangeDeliveryFee;

        return $this;
    }

    public function getOrderItem(): ?OrderItem
    {
        return $this->orderItem;
    }

    public function setOrderItem(OrderItem $orderItem): static
    {
        $this->orderItem = $orderItem;

        return $this;
    }
}
