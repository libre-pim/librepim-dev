<?php

declare(strict_types=1);

/*
 * This file is part of the FOSOAuthServerBundle package.
 *
 * (c) FriendsOfSymfony <http://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FOS\OAuthServerBundle\Security\Authentication\Authenticator;

use FOS\OAuthServerBundle\Security\Authentication\Token\OAuthToken;
use OAuth2\OAuth2;
use OAuth2\OAuth2AuthenticateException;
use OAuth2\OAuth2ServerException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class OAuthAuthenticator implements AuthenticatorInterface
{
    public function __construct(
        private OAuth2 $oauthService,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate(Request $request): Passport
    {
        $tokenString = $this->oauthService->getBearerToken($request, true);

        try {
            $accessToken = $this->oauthService->verifyAccessToken($tokenString);
        } catch (OAuth2AuthenticateException $e) {
            throw new AuthenticationException('OAuth2 authentication failed', 0, $e);
        }

        $scope = $accessToken->getScope();
        $user = $accessToken->getUser();
        $username = $user instanceof UserInterface ? $user->getUserIdentifier() : '';

        $userBadge = new UserBadge($username);

        return new SelfValidatingPassport($userBadge);
    }

    /**
     * @TODO should be removed when Symfony > 6.0
     * {@inheritdoc}
     */
    public function createAuthenticatedToken(PassportInterface $passport, string $firewallName): TokenInterface
    {
        if (!$passport instanceof Passport) {
            throw new \RuntimeException(sprintf('Cannot create a OAuth2 authenticated token. $passport should be a %s', Passport::class));
        }

        $token = $this->createToken($passport, $firewallName);
        $token->setAuthenticated(true);

        return $token;
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        $roles = $passport->getUser()->getRoles();

        $token = new OAuthToken($roles);
        $token->setUser($passport->getUser());

        /** @TODO should be removed when Symfony > 6.0 */
        if (method_exists(AuthenticatorInterface::class, 'createAuthenticatedToken') && !method_exists(AuthenticatorInterface::class, 'createToken')) {
            $token->setAuthenticated(true, false);
        }

        return $token;
    }

    /**
     * {@inheritdoc}
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        if ($exception instanceof OAuth2ServerException) {
            return new Response($exception->getMessage(), (int) $exception->getHttpCode(), $exception->getResponseHeaders());
        }

        $responseException = new OAuth2AuthenticateException(
            (string) Response::HTTP_UNAUTHORIZED,
            OAuth2::TOKEN_TYPE_BEARER,
            $this->oauthService->getVariable(OAuth2::CONFIG_WWW_REALM),
            'access_denied',
            $exception->getMessage()
        );

        return $responseException->getHttpResponse();
    }

    /**
     * {@inheritdoc}
     */
    public function supports(Request $request): ?bool
    {
        $tokenString = $this->oauthService->getBearerToken($request);

        return is_string($tokenString) && !empty($tokenString);
    }
}
