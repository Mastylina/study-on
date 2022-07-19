<?php

namespace App\Model;

use JMS\Serializer\Annotation as Serializer;

class UserDto
{

    /**
     * @var string
     * @Serializer\Type("string")
     */
    public string $username;

    /**
     * @var string
     * @Serializer\Type("string")
     */
    public string $password;

    /**
     * @var array
     * @Serializer\Type("array")
     */
    private array $roles;

    /**
     * @var float
     * @Serializer\Type("float")
     */
    public float $balance;

    /**
     * @var string
     * @Serializer\Type("string")
     */
    public string $token;

    /**
     * @Serializer\Type("string")
     */
    public $refreshToken;

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): void
    {
        $this->username = $username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(?string $password): void
    {
        $this->password = $password;
    }

    public function getRoles(): ?array
    {
        return $this->roles;
    }

    public function setRoles(?array $roles): void
    {
        $this->roles = $roles;
    }

    public function getBalance(): ?float
    {
        return $this->balance;
    }

    public function setBalance(?float $balance): void
    {
        $this->balance = $balance;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(?string $token): void
    {
        $this->token = $token;
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function setRefreshToken(?string $refreshToken): void
    {
        $this->refreshToken = $refreshToken;
    }
}