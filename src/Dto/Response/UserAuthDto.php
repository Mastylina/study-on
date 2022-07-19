<?php

namespace App\Dto\Response;

use JMS\Serializer\Annotation as Serializer;

class UserAuthDto
{

    /**
     * Serializer\Type("string")
     */
    public string $token;
    /**
     * Serializer\Type("string")
     */
    public string $refreshToken;
    /**
     * @Serializer\Type("array<string>")
     */
    public array $roles;
}