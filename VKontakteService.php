<?php

namespace AdminBundle\Services;

namespace AdminBundle\Services;

use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManager;
use AdminBundle\Entity\Card;
use AdminBundle\Entity\User;
use AdminBundle\Entity\UserCard;
use AdminBundle\Helper\GenerationUUIDHelper;
use JMS\Serializer\Tests\Fixtures\Discriminator\Car;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Acl\Exception\Exception;
use Symfony\Component\Debug\Exception\ClassNotFoundException;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\View\View;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\Annotations\Version;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\Validator\Mapping\CascadingStrategy;
use Symfony\Component\DependencyInjection\Container;
use AdminBundle\Entity\UserAdminSync;

class VKontakteService
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

        $user->setVkAccount($socialToken);
        $user->setVkUserId($socialUserId);
        $user->setUdid($userUdid);
        $user->setUuid($userUuid);
        $user->setSocialType($socialType);
        $app_id = $socialUserId;
        $return_friends = true;
        $user_token = $socialToken;
        $request_params = [
            'app_id' => 5629715,
            'access_token' => $socialToken,
            'return_friends' => $return_friends,
            'fields' => 'photo',
            'v' => '5.53'
        ];

        $get_params = http_build_query($request_params);
        $personalInfo = json_decode(file_get_contents('https://api.vk.com/method/users.get?' . $get_params . '&access_token='. $socialToken));
        $profilesPerson = $personalInfo->response;
        
        foreach ($profilesPerson as $person) {
            $firstName = $person->first_name;
            $userName = $user->setVkName($firstName);
            $userFileVk = $user->setFileVk($person->photo);
            $friendArr[] = $user;
            $this->em->persist($user);
        }
        
        $this->em->persist($user);
        $this->em->flush();

        if ($firstSync) {
            $syncForNewUserDatas = $this->em->getRepository(UserAdminSync::class)
                ->findAllNewDistinctData();

            foreach ($syncForNewUserDatas as $key => $value) {
                $syncEntity = new UserAdminSync();
                $cloneSync = clone $syncEntity;
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

    public function  getFriends($userUuid,
                                $socialType,
                                $socialUserId,
                                $socialToken,
                                $cards)
    {
        return $this->getFriendsService->vkGetFriends($userUuid,
            $socialType,
            $socialUserId,
            $socialToken,
            $cards);
    }

    public function getFriendsForUpdate(
        $userUuid,
        $socialType,
        $socialUserId,
        $socialToken,
        $cardUuid
    )
    {
        return $this->getFriendsService->vkGetFriendsForUpdate(
            $userUuid,
            $socialType,
            $socialUserId,
            $socialToken,
            $cardUuid
        );
    }
}
