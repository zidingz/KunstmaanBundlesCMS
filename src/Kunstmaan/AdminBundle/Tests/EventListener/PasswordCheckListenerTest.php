<?php

namespace Kunstmaan\AdminBundle\Tests\EventListener;

use Kunstmaan\AdminBundle\Entity\User;
use Kunstmaan\AdminBundle\EventListener\PasswordCheckListener;
use Kunstmaan\AdminBundle\Helper\AdminRouteHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Translation\Translator;

class PasswordCheckListenerTest extends TestCase
{
    /**
     * @dataProvider requestDataProvider
     */
    public function testListener($uri, $shouldPerformCheck, $tokenStorageCallCount)
    {
        $request = new Request([], [], ['_route' => 'example_route'], [], [], ['REQUEST_URI' => $uri]);
        $auth = $this->createMock(AuthorizationCheckerInterface::class);
        $storage = $this->createMock(TokenStorageInterface::class);
        $token = $this->createMock(UsernamePasswordToken::class);
        $user = $this->createMock(User::class);
        $router = $this->createMock(RouterInterface::class);
        $session = $this->createMock(Session::class);
        $flash = $this->createMock(FlashBag::class);
        $trans = $this->createMock(Translator::class);
        $adminRouteHelper = $this->createMock(AdminRouteHelper::class);
        $kernel = $this->createMock(KernelInterface::class);
        $event = class_exists(RequestEvent::class) ? new RequestEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST) : new GetResponseEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST);

        $storage->expects($this->exactly($tokenStorageCallCount))->method('getToken')->willReturn($token);
        $auth->expects($this->exactly($shouldPerformCheck ? 1 : 0))->method('isGranted')->willReturn(true);
        $token->expects($this->exactly($shouldPerformCheck ? 1 : 0))->method('getUser')->willReturn($user);
        $user->expects($this->exactly($shouldPerformCheck ? 1 : 0))->method('isPasswordChanged')->willReturn(false);
        $router->expects($this->exactly($shouldPerformCheck ? 1 : 0))->method('generate')->willReturn(true);
        $session->expects($this->exactly($shouldPerformCheck ? 1 : 0))->method('getFlashBag')->willReturn($flash);
        $flash->expects($this->exactly($shouldPerformCheck ? 1 : 0))->method('add')->willReturn(true);
        $trans->expects($this->exactly($shouldPerformCheck ? 1 : 0))->method('trans')->willReturn(true);
        $adminRouteHelper->method('isAdminRoute')->willReturn($shouldPerformCheck);

        $listener = new PasswordCheckListener($auth, $storage, $router, $session, $trans, $adminRouteHelper);
        $listener->onKernelRequest($event);
    }

    public function requestDataProvider()
    {
        return [
            ['/en/random', false, 0],
            ['/en/admin/', true, 2],
            ['/en/admin/preview/', true, 2],
        ];
    }
}
