<?php

namespace App\Twig;

use App\Repository\ContactMessageRepository;
use App\Repository\CustomerOrderRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AdminOrderExtension extends AbstractExtension
{
    public function __construct(
        private CustomerOrderRepository $customerOrderRepository,
        private ContactMessageRepository $contactMessageRepository
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'admin_pending_orders_count',
                [$this, 'getPendingOrdersCount']
            ),
            new TwigFunction(
                'admin_unread_messages_count',
                [$this, 'getUnreadMessagesCount']
            ),
        ];
    }

    public function getPendingOrdersCount(): int
    {
        return $this->customerOrderRepository->count([
            'status' => 'pending',
        ]);
    }

    public function getUnreadMessagesCount(): int
    {
        return $this->contactMessageRepository->count([
            'isRead' => false,
        ]);
    }
}
