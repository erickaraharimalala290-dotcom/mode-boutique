<?php

namespace App\Repository;

use App\Entity\Payment;
use App\Entity\Product;
use App\Entity\ProductVariant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * Compte le nombre de produits actifs.
     */
    public function countActiveProducts(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Récupère les produits les plus vendus.
     *
     * Le classement repose sur la quantité vendue
     * dans les commandes payées et non annulées.
     *
     * @return Product[]
     */
    public function findBestSellingProducts(int $limit = 4): array
    {
        $paidPaymentSubQuery = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('payment.id')
            ->from(Payment::class, 'payment')
            ->where('payment.customerOrder = o')
            ->andWhere('payment.status = :paid');

        $qb = $this->createQueryBuilder('p');

        return $qb
            ->select('p')
            ->addSelect('SUM(oi.quantity) AS HIDDEN totalSold')
            ->innerJoin('p.orderItems', 'oi')
            ->innerJoin('oi.customerOrder', 'o')
            ->andWhere('p.isActive = :active')
            ->andWhere('o.status != :cancelled')
            ->andWhere(
                $qb->expr()->exists($paidPaymentSubQuery->getDQL())
            )
            ->setParameter('active', true)
            ->setParameter('cancelled', 'cancelled')
            ->setParameter('paid', 'paid')
            ->groupBy('p.id')
            ->orderBy('totalSold', 'DESC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère la couleur du variant le plus vendu
     * pour chaque produit donné.
     *
     * @param int[] $productIds
     *
     * @return array<int, string|null>
     */
    public function findBestSellingVariantColors(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $paidPaymentSubQuery = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('payment.id')
            ->from(Payment::class, 'payment')
            ->where('payment.customerOrder = o')
            ->andWhere('payment.status = :paid');

        $results = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('IDENTITY(v.product) AS productId')
            ->addSelect('v.color AS color')
            ->addSelect('SUM(oi.quantity) AS totalSold')
            ->from(ProductVariant::class, 'v')
            ->innerJoin('v.orderItems', 'oi')
            ->innerJoin('oi.customerOrder', 'o')
            ->andWhere('IDENTITY(v.product) IN (:productIds)')
            ->andWhere('o.status != :cancelled')
            ->andWhere(
                $this->getEntityManager()
                    ->createQueryBuilder()
                    ->expr()
                    ->exists($paidPaymentSubQuery->getDQL())
            )
            ->groupBy('v.id')
            ->orderBy('totalSold', 'DESC')
            ->setParameter('productIds', $productIds)
            ->setParameter('cancelled', 'cancelled')
            ->setParameter('paid', 'paid')
            ->getQuery()
            ->getArrayResult();

        $bestColors = [];

        foreach ($results as $result) {
            $productId = (int) $result['productId'];

            // Ny variant voalohany hita no manana ventes ambony indrindra.
            if (!array_key_exists($productId, $bestColors)) {
                $bestColors[$productId] = $result['color'];
            }
        }

        return $bestColors;
    }
}
