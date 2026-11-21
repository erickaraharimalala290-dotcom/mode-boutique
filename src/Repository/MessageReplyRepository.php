<?php

namespace App\Repository;

use App\Entity\MessageReply;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MessageReply>
 */
class MessageReplyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MessageReply::class);
    }

    /**
     * Récupère les réponses non lues des conversations d'un client.
     *
     * @return MessageReply[]
     */
    public function findUnreadRepliesForUser(User $user): array
    {
        return $this->createQueryBuilder('reply')
            ->innerJoin('reply.contactMessage', 'message')
            ->addSelect('message')
            ->andWhere('message.user = :user')
            ->andWhere('reply.isReadByClient = :isRead')
            ->setParameter('user', $user)
            ->setParameter('isRead', false)
            ->orderBy('reply.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
