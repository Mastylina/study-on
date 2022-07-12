<?php

namespace App\Dto\Response;

use JMS\Serializer\Annotation as Serializer;

class CurrentUserDto
{
    /**
     * @Serializer\Type("string")
     */
    public string $username;

    /**
     * @Serializer\Type("array<string>")
     */
    public array $roles;

    /**
     * @Serializer\Type("float")
     */
    public float $balance;
}