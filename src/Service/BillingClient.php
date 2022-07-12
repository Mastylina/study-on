<?php

namespace App\Service;

use App\Dto\Response\CurrentUserDto;
use App\Dto\Response\Transformer\UserAuthDtoTransformer;
use App\Dto\Response\UserAuthDto;
use App\Exception\BillingUnavailableException;
use JMS\Serializer\SerializerInterface;

class BillingClient
{
    private string $apiUrl;
    protected $serializer;

    public function __construct(SerializerInterface $serializer)
    {
        $this->apiUrl = $_ENV['BILLING_URL'];
        $this->serializer = $serializer;
    }

    public function auth($credentials)
    {
        $query = curl_init($this->apiUrl . '/api/v1/auth');
        $options = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $credentials,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($credentials),
            ]
        ];
        curl_setopt_array($query, $options);
        $response = curl_exec($query);

        if ($response === false) {
            throw new BillingUnavailableException('Ошибка на стороне сервиса авторизации');
        }
        curl_close($query);

        $result = json_decode($response, true);
        if (isset($result['code'])) {
            if ($result['code'] === 401) {
                throw new BillingUnavailableException('Проверьте правильность введённого логина и пароля');
            }
        }

        $userDto = $this->serializer->deserialize($response, UserAuthDto::class, 'json');
        return (new UserAuthDtoTransformer())->transformToObject($userDto);
    }

    public function getUser($token)
    {
        $query = curl_init($this->apiUrl . '/api/v1/users/current');
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bearer ' . $token
            ]
        ];
        curl_setopt_array($query, $options);
        $response = curl_exec($query);
        if ($response === false) {
            throw new BillingUnavailableException('Ошибка на стороне сервиса авторизации');
        }
        curl_close($query);

        $result = json_decode($response, true);
        if (isset($result['errors'])) {
            throw new BillingUnavailableException('Ошибка на стороне сервера.');
        }

        return $this->serializer->deserialize($response, CurrentUserDto::class, 'json');
    }


    public function refreshToken(string $refreshToken): UserDto
    {
        // Запрос в сервис биллинг
        $query = curl_init($this->apiUrl . '/api/v1/token/refresh');
        curl_setopt($query , CURLOPT_POST, 1);
        curl_setopt($query , CURLOPT_POSTFIELDS, $refreshToken);
        curl_setopt($query, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($query, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($refreshToken)
        ]);
        $response = curl_exec($query);
        // Ошибка с биллинга
        if ($response === false) {
            throw new BillingUnavailableException('Сервис временно недоступен. 
            Попробуйте авторизоваться позднее');
        }

        /** @var UserDto $userDto */
        $userDto = $this->serializer->deserialize($response, UserDto::class, 'json');

        return $userDto;
    }
    public function register($registerRequest)
    {

        $api = new ApiService(
            '/api/v1/register',
            'POST',
            $registerRequest,
            null,
            null,
            'Сервис регистрации недоступен. Попробуйте зарегистрироваться позже.');
        $response = $api->exec();
        $result = json_decode($response, true);
        if (isset($result['errors'])) {
            throw new BillingUnavailableException(json_encode($result['errors']));
        }
        $userDto = $this->serializer->deserialize($response, UserAuthDto::class, 'json');
        return (new UserAuthDtoTransformer())->transformToObject($userDto);
    }

}