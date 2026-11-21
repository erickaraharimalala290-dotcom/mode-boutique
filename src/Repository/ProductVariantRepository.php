<?php

namespace App\Repository;

use App\Entity\ProductVariant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductVariant>
 */
class ProductVariantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductVariant::class);
    }

    /**
     * Récupère les variantes avec leurs produits et catégories.
     */
    public function findAllForStock(): array
    {
        return $this->createQueryBuilder('v')
            ->innerJoin('v.product', 'p')
            ->addSelect('p')
            ->leftJoin('p.category', 'c')
            ->addSelect('c')
            ->leftJoin('p.images', 'i')
            ->addSelect('i')
            ->andWhere('v.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('p.name', 'ASC')
            ->addOrderBy('v.color', 'ASC')
            ->addOrderBy('v.size', 'ASC')
            ->getQuery()
            ->getResult();
    }


    /**
     * Récupère les variantes disponibles pour un remplacement.
     *
     * @return ProductVariant[]
     */
    public function findAvailableForReplacement(): array
    {
        return $this->createQueryBuilder('v')
            ->innerJoin('v.product', 'p')
            ->addSelect('p')
            ->andWhere('v.isActive = :active')
            ->andWhere('p.isActive = :productActive')
            ->andWhere('v.stockQuantity > 0')
            ->setParameter('active', true)
            ->setParameter('productActive', true)
            ->orderBy('p.name', 'ASC')
            ->addOrderBy('v.color', 'ASC')
            ->addOrderBy('v.size', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les variantes actives dont le stock est faible.
     */
    public function countLowStockVariants(): int
    {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->innerJoin('v.product', 'p')
            ->andWhere('v.isActive = :active')
            ->andWhere('p.isActive = :productActive')
            ->andWhere('v.stockQuantity <= v.lowStockThreshold')
            ->setParameter('active', true)
            ->setParameter('productActive', true)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
