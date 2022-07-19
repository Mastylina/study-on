<?php

namespace App\Security;

use App\Model\UserDto;
use App\Service\DecodingJwt;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    private $email;

    private $roles = [];

    private $apiToken;

    private $password;

    private $refreshToken;

    public static function fromDto(UserDto $userDto, DecodingJwt $decodingJwt): self
    {
        $user = new self();

        $decodingJwt->decode($userDto->getToken());

        $user->setEmail($decodingJwt->getUsername());
        $user->setRoles($decodingJwt->getRoles());
        $user->setApiToken($userDto->getToken());
        $user->setRefreshToken($userDto->getRefreshToken());

        return $user;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string)$this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
// guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function getApiToken(): string
    {
        return $this->apiToken;
    }

    public function setApiToken(string $apiToken): self
    {
        $this->apiToken = $apiToken;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials()
    {
// If you store any temporary, sensitive data on the user, clear it here
// $this->plainPassword = null;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function getSalt()
    {
// TODO: Implement getSalt() method.
    }

    public function getUsername()
    {
// TODO: Implement getUsername() method.
    }
    /**
     * @return mixed
     */
    public function getRefreshToken(): string
    {
        return $this->refreshToken;
    }

    /**
     * @param mixed $refreshToken
     */
    public function setRefreshToken(string $refreshToken): void
    {
        $this->refreshToken = $refreshToken;
    }
}