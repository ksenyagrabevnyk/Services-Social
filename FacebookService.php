<?php

namespace AdminBundle\Services;

namespace AdminBundle\Services;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManager;
use AdminBundle\Entity\User;
use JMS\Serializer\Tests\Fixtures\Discriminator\Car;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Acl\Exception\Exception;
use Symfony\Component\Debug\Exception\ClassNotFoundException;
use Facebook\Facebook;
use Facebook\FacebookApp;
use Facebook\FacebookRequest;
use Facebook\Exceptions\FacebookResponseException;
use Facebook\Exceptions\FacebookSDKException;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\DependencyInjection\Container;
use AdminBundle\Entity\UserAdminSync;

class FacebookService
{
    private $container;
    private $em;
    private $getFriendsService;

    public function __construct(Container $container, EntityManager $em, GetFriendsService $getFriendsService)
    {
        $this->container = $container;
        $this->em = $em;
        $this->getFriendsService = $getFriendsService;
    }

    public function getContainer()
    {
        return $this->container;
    }

    public function addUser(
        $userUdid = null,
        $userUuid = null,
        $socialType,
        $socialUserId,
        $socialToken
    )
    {
        $firstSync = false;
        $user = $this->em->getRepository(User::class)
            ->findOneBy([
                'uuid' => $userUuid
            ])
        ;

        if (!$user) {
            $user = new User();
            $firstSync = true;
        }

        $user->setFbAccount($socialToken);
        $user->setFbUserId($socialUserId);
        $user->setUdid($userUdid);
        $user->setUuid($userUuid);
        $user->setSocialType($socialType);

        $fb = new Facebook([
            'app_id' => $socialUserId,
            'app_secret' => '1f09fb8993440456f6dd83e94d1efd44',
            'default_graph_version' => 'v2.7',
        ]);

        try {
            $userResponse = $fb->get('/me?fields=first_name,name, picture', $socialToken);
        } catch (FacebookResponseException $e) {
            echo 'Graph returned an error: ' . $e->getMessage();
            exit;
        } catch (FacebookSDKException $e) {
            echo 'Facebook SDK returned an error: ' . $e->getMessage();
            exit;
        }

        $graphObjectUser = $userResponse->getGraphUser();
        $userInfo['first_name'] = $graphObjectUser->getFirstName();
        $userInfo['photo'] = $graphObjectUser->getPicture()->getUrl();
        $user->setFbName($userInfo['first_name']);
        $user->setFileFb($userInfo['photo']);
        $this->em->persist($user);
        $this->em->flush();

      if ($firstSync) {
            $syncForNewUserDatas = $this->em->getRepository(UserAdminSync::class)
                ->findAllNewDistinctData();

            foreach ($syncForNewUserDatas as $key => $value) {
                $cloneSync = new UserAdminSync();
//              $cloneSync = clone $syncEntity;
                $cloneSync->setUserId($user);
                $cloneSync->setEntityType($value['entityType']);
                $cloneSync->setEntityId($value['entityId']);
                $cloneSync->setAction($value['action']);
                $cloneSync->setSync(0);
                $this->em->persist($cloneSync);
                $this->em->flush();
            }
      }
        
        return $user;
    }

    public function getFriends(
        $userUuid,
        $socialType,
        $socialUserId,
        $socialToken,
        $cards
    )
    {
        return $this->getFriendsService->fbGetFriends(
                $userUuid,
                $socialType,
                $socialUserId,
                $socialToken,
                $cards
        );
    }

    public function getFriendsForUpdate(
        $userUuid,
        $socialType,
        $socialUserId,
        $socialToken,
        $cardUuid
    )
    {
        return $this->getFriendsService->fbGetFriendsForUpdate(
            $userUuid,
            $socialType,
            $socialUserId,
            $socialToken,
            $cardUuid
        );
    }
}
