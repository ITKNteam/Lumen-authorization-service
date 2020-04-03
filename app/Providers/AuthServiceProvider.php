<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Lumen\Application;
use Illuminate\Support\Facades\Redis;
use Exception;
use Firebase\JWT\JWT;
use App\Models\ResultDto;
use Sentry;

class AuthServiceProvider extends ServiceProvider {

    const JWT_KEY = 'eyJ0eXAiOBaracuda2009iJKV1QiLCJhbGciOiJIUzI1';

    const USER_TOKEN_KEY = 'user.';

    const TOKEN_USER_KEY = 'token.';

    const TOKEN_EXPARATION_IN_DAYS = 1;

    /**
     * @var array;
     */

    private $redis;

    protected $app;

    function __construct(Application $app) {
        parent::__construct($app);
        $this->redis = Redis::connection();
    }

    /**
     * @return string|null
     */
    private function generateSHA256() {
        $str = $this->generateRandomStr(50);
        $salt = '$6$rounds=' . $this->generateRandomStr(4) . '$'
            . $this->generateRandomStr(41);
        return crypt($str, $salt);
    }

    /**
     * @param $length
     *
     * @return string
     */
    private function generateRandomStr($length): string {
        $characters
            = 'abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public function getToken(int $userId): ResultDto {
        $accesToken = $this->generateSHA256();
        $refreshToken = $this->generateSHA256();
        $accesTokenExp = time() + (self::TOKEN_EXPARATION_IN_DAYS * 24 * 60
                * 60);

        $token = [
            'iss' => 'auth.microservice',
            'access' => $accesToken,
            'refresh' => $refreshToken,
            'exp' => $accesTokenExp,
        ];
        $jwt = JWT::encode($token, self::JWT_KEY);

        try {
            $cache = $this->getCache();
            $userTokenKey = self::USER_TOKEN_KEY . $userId;
            $tokenUserKey = self::TOKEN_USER_KEY . $accesToken;

            $cache->set($userTokenKey, $accesToken);
            $cache->set($tokenUserKey, $userId, $accesTokenExp);

        } catch (\Error $e) {
            Sentry\captureException($e);
            return new ResultDto(0, $e->getMessage(), ['trace' => $e->getTrace()]);
        } catch (\Throwable $t) {
            Sentry\captureException($t);
            return new ResultDto(0, $t->getMessage());
        }

        return new ResultDto(1, 'OK', [
            'token' => $jwt
        ]);
    }

    private function getCache() {
        return $this->redis;
    }

    /**
     * @param int $userId
     *
     * @return ResultDto
     */
    private function getTokenByUserId(int $userId): ResultDto {
        $UserTokenKey = self::USER_TOKEN_KEY . $userId;
        $cache = $this->getCache();
        $token = $cache->get($UserTokenKey);

        return empty($token)
            ? new ResultDto(0, 'Not found')
            : new ResultDto(1, 'Token ok', [
                'token' => $token
            ]);
    }

    /**
     * @param $token
     *
     * @return ResultDto
     */
    public function logout($token): ResultDto {

        $retJwt = $this->validateJwt($token)->getAnswer();
        if ($retJwt['res'] === 1) {
            $accessToken = $retJwt['data']['access'] ?? '';

            $cache = $this->getCache();

            try {
                $cache->del(self::TOKEN_USER_KEY . $accessToken);
                return new ResultDto(1, 'Success');
            } catch (\RedisException $e) {
                Sentry\captureException($e);
                return new ResultDto(0, 'Logout', ['error' => $e->getMessage()]);
            }

            return new ResultDto(1, 'Logout', [
                'accessToken' => $accessToken
            ]);
        } else {
            return new ResultDto($retJwt['res'], $retJwt['message'], $retJwt['data']);
        }
    }

    /**
     * @param string $jwt
     *
     * @return ResultDto
     */
    private function validateJwt(string $jwt): ResultDto {
        try {
            $decoded = JWT::decode($jwt, self::JWT_KEY, ['HS256']);
            return new ResultDto(1, 'JWT OK', (array)$decoded);
        } catch (Exception $e) {
            Sentry\captureException($e);
            return new ResultDto(0, 'JWT: ' . $e->getMessage(), ['status' => 0]);
        }
    }

    /**
     *
     * @param string $token
     *
     * @return ResultDto
     */
    public function authentication(string $token): ResultDto {
        $retJwt = $this->validateJwt($token)->getAnswer();
        if ($retJwt['res'] === 1) {
            $accessToken = $retJwt['data']['access'] ?? '';
            $accesTokenExp = $retJwt['data']['exp'] ?? 0;
            if ($accesTokenExp >= time()) {
                $retToken = $this->getUserIdByToken($accessToken)->getAnswer();
                if ($retToken === 1) {
                    return new ResultDto(1, 'User ok', [
                        'status' => 1,
                        'user_id' => $retToken['data']['user_id']
                    ]);
                } else {
                    return new ResultDto($retToken['res'], $retToken['message'], $retToken['data']);
                }
            } else {
                return new ResultDto(0, 'Token expired, refresh!', ['status' => 2]);
            }
        }
        return new ResultDto($retJwt['res'], $retJwt['message'], $retJwt['data']);
    }

    /**
     * @param string $token
     *
     * @return ResultDto
     */
    private function getUserIdByToken(string $token): ResultDto {
        $tokenUserKey = self::TOKEN_USER_KEY . $token;
        $cache = $this->getCache();
        $userId = $cache->get($tokenUserKey);

        return empty($userId)
            ? new ResultDto(0, 'User not found', ['status' => 0])
            : new ResultDto(1, 'User id ok', ['user_id' => $userId]);
    }

    /**
     * @param string $token
     * @return ResultDto
     */
    public function refreshToken(string $token): ResultDto {
        $retJwt = $this->validateJwt($token)->getAnswer();
        if ($retJwt['res'] === 1) {
            $accessToken = $retJwt['data']['access'] ?? '';
            $retToken = $this->getUserIdByToken($accessToken)->getAnswer();
            if ($retToken['res'] === 1) {
                $resNewToken = $this->getToken($retToken['data']['user_id'])->getAnswer();
                if ($resNewToken['res'] == 1) {
                    $newToken = $resNewToken['data']['token'];
                    $cache = $this->getCache();
                    $cache->del(self::TOKEN_USER_KEY . $accessToken);
                    return new ResultDto(1, 'User ok', ['token' => $newToken]);
                } else {
                    return new ResultDto($resNewToken['res'], $resNewToken['message'], $resNewToken['data']);
                }
            } else {
                return new ResultDto($retToken['res'], $retToken['message'], $retToken['data']);
            }
        }
        return new ResultDto($retJwt['res'], $retJwt['message'], $retJwt['data']);
    }
}
