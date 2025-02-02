<?php

namespace Kunstmaan\AdminBundle\Security;

use Kunstmaan\AdminBundle\FlashMessages\FlashTypes;
use Kunstmaan\AdminBundle\Helper\Security\OAuth\OAuthUserCreator;
use Kunstmaan\AdminBundle\Helper\Security\OAuth\OAuthUserCreatorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Symfony\Component\Translation\TranslatorInterface as LegacyTranslatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class OAuthAuthenticator extends AbstractGuardAuthenticator
{
    /** @var RouterInterface */
    private $router;

    /** @var SessionInterface */
    private $session;

    /** @var TranslatorInterface|LegacyTranslatorInterface */
    private $translator;

    /** @var OAuthUserCreator */
    private $oAuthUserCreator;

    /** @var string */
    private $clientId;

    /** @var string */
    private $clientSecret;

    public function __construct(RouterInterface $router, SessionInterface $session, /* TranslatorInterface */ $translator, OAuthUserCreatorInterface $oAuthUserCreator, $clientId, $clientSecret)
    {
        if (null !== $translator && (!$translator instanceof LegacyTranslatorInterface && !$translator instanceof TranslatorInterface)) {
            throw new \InvalidArgumentException(sprintf('Argument 3 passed to "%s" must be of the type "%s" or "%s", "%s" given', __METHOD__, LegacyTranslatorInterface::class, TranslatorInterface::class, get_class($translator)));
        }

        $this->router = $router;
        $this->session = $session;
        $this->translator = $translator;
        $this->oAuthUserCreator = $oAuthUserCreator;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
    }

    /**
     * @return bool
     */
    public function supports(Request $request)
    {
        return $request->attributes->get('_route') == 'KunstmaanAdminBundle_oauth_signin' || $request->request->has('_google_id_token');
    }

    /**
     * Returns a response that directs the user to authenticate.
     *
     * This is called when an anonymous request accesses a resource that
     * requires authentication. The job of this method is to return some
     * response that "helps" the user start into the authentication process.
     *
     * Examples:
     *  A) For a form login, you might redirect to the login page
     *      return new RedirectResponse('/login');
     *  B) For an API token authentication system, you return a 401 response
     *      return new Response('Auth header required', 401);
     *
     * @param Request                 $request       The request that resulted in an AuthenticationException
     * @param AuthenticationException $authException The exception that started the authentication process
     *
     * @return RedirectResponse
     */
    public function start(Request $request, AuthenticationException $authException = null)
    {
        return new RedirectResponse($this->router->generate('kunstmaan_admin_login'));
    }

    /**
     * Get the authentication credentials from the request and return them
     * as any type (e.g. an associate array).
     *
     * Whatever value you return here will be passed to getUser() and checkCredentials()
     *
     * For example, for a form login, you might:
     *
     *      return array(
     *          'username' => $request->request->get('_username'),
     *          'password' => $request->request->get('_password'),
     *      );
     *
     * Or for an API token that's on a header, you might use:
     *
     *      return array('api_key' => $request->headers->get('X-API-TOKEN'));
     *
     * @return array
     */
    public function getCredentials(Request $request)
    {
        $token = $request->request->get('_google_id_token');

        return [
            'token' => $token,
        ];
    }

    /**
     * Return a UserInterface object based on the credentials.
     *
     * The *credentials* are the return value from getCredentials()
     *
     * You may throw an AuthenticationException if you wish. If you return
     * null, then a UsernameNotFoundException is thrown for you.
     *
     * @param mixed $credentials
     *
     * @throws AuthenticationException
     *
     * @return UserInterface|null
     */
    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        $idToken = $credentials['token'];

        $gc = new \Google_Client();
        $gc->setClientId($this->clientId);
        $gc->setClientSecret($this->clientSecret);
        $ticket = $gc->verifyIdToken($idToken);
        if (!$ticket instanceof \Google_LoginTicket) {
            return null;
        }

        $data = $ticket->getAttributes()['payload'];
        $email = $data['email'];
        $googleId = $data['sub'];

        return $this->oAuthUserCreator->getOrCreateUser($email, $googleId);
    }

    /**
     * Returns true if the credentials are valid.
     *
     * If any value other than true is returned, authentication will
     * fail. You may also throw an AuthenticationException if you wish
     * to cause authentication to fail.
     *
     * The *credentials* are the return value from getCredentials()
     *
     * @param mixed $credentials
     *
     * @return bool
     *
     * @throws AuthenticationException
     */
    public function checkCredentials($credentials, UserInterface $user)
    {
        return true;
    }

    /**
     * Called when authentication executed, but failed (e.g. wrong username password).
     *
     * This should return the Response sent back to the user, like a
     * RedirectResponse to the login page or a 403 response.
     *
     * If you return null, the request will continue, but the user will
     * not be authenticated. This is probably not what you want to do.
     *
     * @return RedirectResponse
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        $this->session->getFlashBag()->add(FlashTypes::DANGER, $this->translator->trans('errors.oauth.invalid'));

        return new RedirectResponse($this->router->generate('kunstmaan_admin_login'));
    }

    /**
     * Called when authentication executed and was successful!
     *
     * This should return the Response sent back to the user, like a
     * RedirectResponse to the last page they visited.
     *
     * If you return null, the current request will continue, and the user
     * will be authenticated. This makes sense, for example, with an API.
     *
     * @param string $providerKey The provider (i.e. firewall) key
     *
     * @return RedirectResponse
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        $targetPath = $request->getSession()->get(
            sprintf('_security.%s.target_path', $providerKey),
            $this->router->generate('KunstmaanAdminBundle_homepage')
        );

        return new RedirectResponse($targetPath);
    }

    /**
     * Does this method support remember me cookies?
     *
     * Remember me cookie will be set if *all* of the following are met:
     *  A) This method returns true
     *  B) The remember_me key under your firewall is configured
     *  C) The "remember me" functionality is activated. This is usually
     *      done by having a _remember_me checkbox in your form, but
     *      can be configured by the "always_remember_me" and "remember_me_parameter"
     *      parameters under the "remember_me" firewall key
     *
     * @return bool
     */
    public function supportsRememberMe()
    {
        return false;
    }
}
