<?php

namespace Kunstmaan\AdminBundle\Helper\Security\OAuth;

use Doctrine\ORM\EntityManagerInterface;
use Kunstmaan\AdminBundle\Entity\Group;
use Kunstmaan\AdminBundle\Entity\GroupInterface;
use Kunstmaan\AdminBundle\Entity\User;

class OAuthUserCreator implements OAuthUserCreatorInterface
{
    /** @var EntityManagerInterface */
    private $em;

    /** @var array */
    private $hostedDomains;

    /** @var string */
    private $userClass;

    /** @var OAuthUserFinderInterface */
    private $userFinder;

    /**
     * @param array  $hostedDomains
     * @param string $userClass
     */
    public function __construct(EntityManagerInterface $em, $hostedDomains, $userClass, OAuthUserFinderInterface $userFinder)
    {
        $this->em = $em;
        $this->hostedDomains = $hostedDomains;
        $this->userClass = $userClass;
        $this->userFinder = $userFinder;
    }

    public function getOrCreateUser($email, $googleId)
    {
        if ($this->isConfiguredDomain($email)) {
            $user = $this->userFinder->findUserByGoogleSignInData($email, $googleId);

            if (!$user instanceof $this->userClass) {
                //User not present in database, create new one
                /** @var User $user */
                $user = new $this->userClass();
                $user->setUsername($email);
                $user->setEmail($email);
                $user->setPlainPassword($googleId . $email . time());
                $user->setEnabled(true);
                $user->setAdminLocale('en');
                $user->setPasswordChanged(true);
            }

            foreach ($this->getAccessLevels($email) as $accessLevel) {
                /** @var GroupInterface $group */
                $group = $this->em->getRepository(Group::class)->findOneBy(['name' => $accessLevel]);
                if (null !== $group) {
                    $user->addGroup($group);
                }
            }
            $user->setGoogleId($googleId);

            // Persist
            $this->em->persist($user);
            $this->em->flush();
        }

        return isset($user) ? $user : null;
    }

    /**
     * This method returns the access level coupled with the domain of the given email
     * If the given domain name has not been configured this function will return null
     *
     * @param string $email
     *
     * @return string[]|null
     */
    private function getAccessLevels($email): ?array
    {
        foreach ($this->hostedDomains as $hostedDomain) {
            if (preg_match('/' . $hostedDomain['domain_name'] . '$/', $email)) {
                return $hostedDomain['access_levels'];
            }
        }

        return null;
    }

    /**
     * This method returns wether a domain for the given email has been configured
     *
     * @param string $email
     */
    private function isConfiguredDomain($email): bool
    {
        foreach ($this->hostedDomains as $hostedDomain) {
            if (preg_match('/' . $hostedDomain['domain_name'] . '$/', $email)) {
                return true;
            }
        }

        return false;
    }
}
