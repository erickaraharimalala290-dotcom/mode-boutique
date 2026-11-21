<?php

namespace App\Repository;

use App\Entity\NewsletterSubscriber;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NewsletterSubscriber>
 */
class NewsletterSubscriberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NewsletterSubscriber::class);
    }

    /**
     * Recherche un abonné à partir de son adresse e-mail.
     */
    public function findOneByEmail(string $email): ?NewsletterSubscriber
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.email = :email')
            ->setParameter('email', mb_strtolower(trim($email)))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Récupère tous les abonnés actifs.
     *
     * @return NewsletterSubscriber[]
     */
    public function findActiveSubscribers(): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('n.subscribedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}