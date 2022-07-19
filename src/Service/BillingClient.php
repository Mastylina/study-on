<?php

namespace App\Service;

use App\Dto\Response\CurrentUserDto;
use App\Dto\Response\Transformer\UserAuthDtoTransformer;
use App\Dto\Response\UserAuthDto;
use App\Exception\BillingUnavailableException;
use App\Exception\ClientException;
use App\Model\CourseDto;
use App\Model\PayDto;
use App\Model\UserDto;
use App\Security\User;
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
        curl_setopt($query, CURLOPT_POST, true);
        curl_setopt($query, CURLOPT_POSTFIELDS, $credentials);
        curl_setopt($query, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($query, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($credentials),
        ]);

        $authResponse = curl_exec($query);

        if (!$authResponse) {
            throw new BillingUnavailableException(
                'Сервис временно недоступен. Попробуйте авторизоваться позднее.'
            );
        }
        curl_close($query);

        $result = json_decode($authResponse, true);
        if (isset($result['code']) && $result['code'] === 401) {
            throw new BillingUnavailableException('Проверьте корректность введённых данных.');
        }


        /** @var UserDto $userDto */
        $userDto = $this->serializer->deserialize($authResponse, UserDto::class, 'json');



        return (new UserAuthDtoTransformer())->transformToObject($userDto);
    }

    public function getUser(User $user, DecodingJwt $decodingJwt)
    {
        $decodingJwt->decode($user->getApiToken());

        $query = curl_init($this->apiUrl . '/api/v1/users/current');
        curl_setopt($query, CURLOPT_HTTPGET, 1);
        curl_setopt($query, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($query, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $user->getApiToken()
        ]);
        $response = curl_exec($query);

        if (!$response) {
            throw new BillingUnavailableException(
                'Сервис временно недоступен. Повторите попытку позднее.'
            );
        }

        $result = json_decode($response, true);
        if (isset($result['code'])) {
            throw new BillingUnavailableException($result['message']);
        }
        return $result;
    }


    public function refreshToken(string $refreshToken): UserDto
    {

        $resp = json_encode(['refresh_token' => $refreshToken]);
// Запрос в сервис биллинг
        $query = curl_init($this->apiUrl . '/api/v1/token/refresh');
        curl_setopt($query, CURLOPT_POST, 1);
        curl_setopt($query, CURLOPT_POSTFIELDS, $resp);
        curl_setopt($query, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($query, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        $response = curl_exec($query);
// Ошибка с биллинга
        if ($response === false) {
            throw new BillingUnavailableException('Сервис временно недоступен.
Попробуйте авторизоваться позднее');
        }
        curl_close($query);
        /** @var UserDto $userDto */
        $userDto = $this->serializer->deserialize($response, UserDto::class, 'json');
        return $userDto;
    }

    /**
     * @throws BillingUnavailableException
     */
    public function getAllCourses(): array
    {

        // Запрос в сервис биллинг, получение данных
        $query = curl_init($this->apiUrl . '/api/v1/courses');
        curl_setopt($query, CURLOPT_HTTPGET, 1);
        curl_setopt($query, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($query, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        $response = curl_exec($query);

                // Ошибка с биллинга
        if ($response === false) {
            throw new BillingUnavailableException('Сервис временно недоступен. 
            Попробуйте позднее');
        }
        curl_close($query);

        // Ответа от сервиса
        $result = json_decode($response, true);
        if (isset($result['code'])) {
            throw new BillingUnavailableException($result['message']);
        }

        return $this->serializer->deserialize($response, 'array<App\Model\CourseDto>', 'json');
    }

    public function register(UserDto $user): UserDto
    {
// dd($user);
        $dataSerialize = json_encode($user, true);
// dd($dataSerialize);
        $query = curl_init($this->apiUrl . '/api/v1/register');
        curl_setopt($query, CURLOPT_POST, 1);
        curl_setopt($query, CURLOPT_POSTFIELDS, $dataSerialize);
        curl_setopt($query, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($query, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($dataSerialize),
        ]);
        $registerResponse = curl_exec($query);
        if (!$registerResponse) {
            throw new BillingUnavailableException(
                'Сервис временно недоступен. Попробуйте зарегистрироваться позднее.'
            );
        }

        $result = json_decode($registerResponse, true, 512, JSON_THROW_ON_ERROR);

        if (isset($result['code'])) {
            if ($result['code'] === 403) {
                throw new ClientException($result['message']);
            } else {
                throw new BillingUnavailableException(
                    'Сервис временно недоступен. Попробуйте зарегистрироваться позднее.'
                );
            }
        }
        $userDto = $this->serializer->deserialize($registerResponse, UserDto::class, 'json');

        return $userDto;
    }

    /**
     * @throws BillingUnavailableException
     */
    public function transactionsHistory(User $user, string $request = ''): array
    {
        // Запрос в сервис биллинг, получение данных
        $query = curl_init($this->apiUrl . '/api/v1/transactions/?' . $request);
        curl_setopt($query, CURLOPT_HTTPGET, 1);
        curl_setopt($query, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($query, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $user->getApiToken()
        ]);
        $response = curl_exec($query);
        // Ошибка с биллинга
        if ($response === false) {
            throw new BillingUnavailableException('Сервис временно недоступен. 
            Попробуйте позднее');
        }
        curl_close($query);

        // Ответа от сервиса
        $result = json_decode($response, true);
        if (isset($result['code'])) {
            throw new BillingUnavailableException($result['message']);
        }

        return $this->serializer->deserialize($response, 'array<App\Model\TransactionDto>', 'json');
    }

    /**
     * @throws BillingUnavailableException
     */
    public function getCourse(string $courseCode): CourseDto
    {

        // Запрос в сервис биллинг, получение данных
        $query = curl_init($this->apiUrl . '/api/v1/courses/' . $courseCode);
        curl_setopt($query, CURLOPT_HTTPGET, 1);
        curl_setopt($query, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($query, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
        $response = curl_exec($query);
        // Ошибка с биллинга

        if ($response === false) {
            throw new BillingUnavailableException('Сервис временно недоступен. 
            Попробуйте позднее');
        }
        curl_close($query);

        // Ответа от сервиса
        $result = json_decode($response, true);
        if (isset($result['code']) && $result['code'] === 404) {
            throw new BillingUnavailableException($result['message']);
        }

        return $this->serializer->deserialize($response, CourseDto::class, 'json');
    }

    public function paymentCourse(User $user, string $codeCourse): PayDto
    {
        // Запрос в сервис биллинг
        $query = curl_init($this->apiUrl . '/api/v1/courses/' . $codeCourse . '/pay');
        curl_setopt($query, CURLOPT_POST, 1);
        curl_setopt($query, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($query, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $user->getApiToken()
        ]);
        $response = curl_exec($query);
        // Ошибка с биллинга
        if ($response === false) {
            throw new BillingUnavailableException('Сервис временно недоступен. 
            Попробуйте позднее');
        }
        curl_close($query);

        // Ответа от сервиса
        $result = json_decode($response, true);
        if (isset($result['code'])) {
            throw new BillingUnavailableException($result['message']);
        }

        return $this->serializer->deserialize($response, PayDto::class, 'json');
    }

    public function newCourse(User $user, CourseDto $courseDto): array
    {
        $response = $this->serializer->serialize($courseDto, 'json');
        // Запрос в сервис биллинг, для добавление нового курса
        $query = curl_init($this->apiUrl . '/api/v1/courses/new');
        curl_setopt($query, CURLOPT_POST, 1);
        curl_setopt($query, CURLOPT_POSTFIELDS, $response);
        curl_setopt($query, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($query, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $user->getApiToken(),
            'Content-Length: ' . strlen($response)
        ]);
        $response = curl_exec($query);
        // Ошибка с биллинга
        if ($response === false) {
            throw new BillingUnavailableException('Сервис временно недоступен. 
            Попробуйте позднее');
        }
        curl_close($query);

        // Ответа от сервиса
        $result = json_decode($response, true);
        if (isset($result['code']) && $result['code'] !== 201) {
            throw new BillingUnavailableException($result['message']);
        }

        return $result;
    }

    public function editCourse(User $user, string $codeCourse, CourseDto $courseDto): array
    {
        $response = $this->serializer->serialize($courseDto, 'json');
        // Запрос в сервис биллинг, для добавление нового курса
        $query = curl_init($this->apiUrl . '/api/v1/courses/' . $codeCourse . '/edit');
        curl_setopt($query, CURLOPT_POST, 1);
        curl_setopt($query, CURLOPT_POSTFIELDS, $response);
        curl_setopt($query, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($query, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $user->getApiToken(),
            'Content-Length: ' . strlen($response)
        ]);
        $response = curl_exec($query);
        // Ошибка с биллинга
        if ($response === false) {
            throw new BillingUnavailableException('Сервис временно недоступен. 
            Попробуйте позднее');
        }
        curl_close($query);

        // Ответа от сервиса
        $result = json_decode($response, true);
        if (isset($result['code']) && $result['code'] !== 200) {
            throw new BillingUnavailableException($result['message']);
        }

        return $result;
    }

}