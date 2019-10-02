<?php

namespace App\Extension\Messaging;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use App\Entity\Messaging\Delivery;
use App\Entity\Messaging\FreeOnMessage;
use App\Entity\Messaging\OptionSet;
use App\Entity\Organisation\IndividualMember;
use App\Entity\Messaging\Message;
use App\Entity\Organisation\Organisation;
use App\Security\JWTUser;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Security;

final class CurrentOrganisationExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    private $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null)
    {
        $this->addWhere($queryBuilder, $resourceClass);
    }

    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, string $operationName = null, array $context = [])
    {
        $this->addWhere($queryBuilder, $resourceClass);
    }

    private function addWhere(QueryBuilder $queryBuilder, string $resourceClass): void
    {

        $supported = $this->supportClass($resourceClass);

        if (!$supported || $this->security->isGranted('ROLE_ADMIN')) {
            return;
        }

        /** @var JWTUser $user */
        $user = $this->security->getUser();

        if (empty($user) || null === $objectUuid = $user->getOrgUuid()) {
            throw new UnauthorizedHttpException('Please login');
        }

        if ($resourceClass === Delivery::class) {
            $rootAlias = $queryBuilder->getRootAliases()[0];
            $expr = $queryBuilder->expr();
            $queryBuilder->join($rootAlias.'.message', 'message');
            $queryBuilder->join('message.organisation', 'organisation');
            $queryBuilder->andWhere($expr->like('organisation.uuid', $expr->literal($objectUuid)));
//            $queryBuilder->setParameter('current_object', $objectUuid);
        } else {
            $rootAlias = $queryBuilder->getRootAliases()[0];
            $expr = $queryBuilder->expr();
            $queryBuilder->join($rootAlias.'.organisation', 'organisation');
            $queryBuilder->andWhere($expr->like('organisation.uuid', $expr->literal($objectUuid)));
        }

//        echo $queryBuilder->getQuery()->getSQL();
    }

    private function supportClass($class)
    {
        return in_array($class, [Delivery::class, Message::class, OptionSet::class, FreeOnMessage::class]);
    }
}
