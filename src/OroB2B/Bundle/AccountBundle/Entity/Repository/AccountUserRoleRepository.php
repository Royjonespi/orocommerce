<?php

namespace OroB2B\Bundle\AccountBundle\Entity\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

use OroB2B\Bundle\AccountBundle\Entity\AccountUser;
use OroB2B\Bundle\AccountBundle\Entity\AccountUserRole;
use OroB2B\Bundle\WebsiteBundle\Entity\Website;

class AccountUserRoleRepository extends EntityRepository
{
    /**
     * @param Website $website
     * @return AccountUserRole|null
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getDefaultAccountUserRoleByWebsite(Website $website)
    {
        $qb = $this->createQueryBuilder('accountUserRole');
        return $qb
            ->innerJoin('accountUserRole.websites', 'website')
            ->andWhere($qb->expr()->eq('website', ':website'))
            ->setParameter('website', $website)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Checks is role default for website
     *
     * @param AccountUserRole $role
     * @return bool
     */
    public function isDefaultForWebsite(AccountUserRole $role)
    {
        $qb = $this->createQueryBuilder('accountUserRole');
        $findResult = $qb
            ->select('accountUserRole.id')
            ->innerJoin('accountUserRole.websites', 'website')
            ->where($qb->expr()->eq('accountUserRole', ':accountUserRole'))
            ->setParameter('accountUserRole', $role)
            ->setMaxResults(1)
            ->getQuery()
            ->getArrayResult();

        return !empty($findResult);
    }

    /**
     * Checks if there are at least one user assigned to the given role
     *
     * @param AccountUserRole $role
     * @return bool
     */
    public function hasAssignedUsers(AccountUserRole $role)
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $findResult = $qb
            ->select('accountUser.id')
            ->from('OroB2BAccountBundle:AccountUser', 'accountUser')
            ->innerJoin('accountUser.roles', 'accountUserRole')
            ->where($qb->expr()->eq('accountUserRole', ':accountUserRole'))
            ->setParameter('accountUserRole', $role)
            ->setMaxResults(1)
            ->getQuery()
            ->getArrayResult();

        return !empty($findResult);
    }

    /**
     * @param AccountUser $accountUser
     * @return QueryBuilder
     */
    public function getAvailableRolesByAccountUserQueryBuilder(AccountUser $accountUser)
    {
        $qb = $this->createQueryBuilder('accountUserRole');
        $qb->where($qb->expr()->orX(
            $qb->expr()->isNull('accountUserRole.account'),
            $qb->expr()->andX(
                $qb->expr()->eq('accountUserRole.account', ':account'),
                $qb->expr()->eq('accountUserRole.organization', ':organization')
            )
        ));
        $qb->setParameter('account', $accountUser->getAccount());
        $qb->setParameter('organization', $accountUser->getOrganization());
        return $qb;
    }
}
