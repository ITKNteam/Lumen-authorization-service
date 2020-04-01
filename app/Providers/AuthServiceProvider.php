<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Lumen\Application;
use Redis;
use App\Configuration;
use Exception;
use Firebase\JWT\JWT;

class AuthServiceProvider extends ServiceProvider {

    const JWT_KEY = 'eyJ0eXAiOBaracuda2009iJKV1QiLCJhbGciOiJIUzI1';

    const USER_TOKEN_KEY = 'user.';

    const TOKEN_USER_KEY = 'token.';

    const TOKEN_EXPARATION_IN_DAYS = 1;

    /**
     * @var array;
     */
    private $config;

    private $redis;

    protected $app;

    function __construct(Application $app) {
        $this->app = $app;

        $this->config = new Configuration();
        $this->redis = new Redis();
        $config = $this->config->getRedis();
        $this->redis->connect($config['host'], $config['port']);
    }

    public function test() {
        return 'test';
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
    private function generateRandomStr($length) {
        $characters
            = 'abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public function getToken(int $userId): array {
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
            //$this->logger->error('getToken', ['error'=>$e->getMessage(), 'trace'=>$e->getTrace() ]);
            // return  ['res'=>0, 'message'=>['error'=> $e->getMessage()], 'data'=>['trace'=>$e->getTrace()]];
        } catch (\Throwable $t) {

            // $this->logger->error('getToken', ['Throwable error'=>$t->getMessage(), 'trace'=>$t->getTrace() ]);
            //return  ['res'=>0, 'message'=>$t->getMessage(), 'data'=>[]];
        }

        return [
            'res' => 1,
            'message' => 'OK',
            'data' => [
                'token' => $jwt
            ]
        ];
    }

    private function getCache() {
        return $this->redis;
    }

    /**
     * @param int $userId
     *
     * @return array
     */
    private function getTokenByUserId(int $userId): array {
        $ret = [
            'res' => 0,
            'message' => 'Token not found',
            'data' => []
        ];
        $UserTokenKey = self::USER_TOKEN_KEY . $userId;
        $cache = $this->getCache();
        $token = $cache->get($UserTokenKey);

        if ($token !== null) {

            $ret = [
                'res' => 1,
                'message' => 'Token ok',
                'data' => ['token' => $token]
            ];
        }

        return $ret;
    }


    /**
     * @param $token
     *
     * @return array
     */
    public function logout($token) {
        $retJwt = $this->validateJwt($token);
        if ($retJwt['res'] === 1) {
            $accessToken = $retJwt['data']['access'] ?? '';

            $cache = $this->getCache();

            try {
                $cache->set(self::TOKEN_USER_KEY . $accessToken, '');
            } catch (\RedisException $e) {
                return [
                    'res' => 0,
                    'message' => 'Logout',
                    'data' => ['error' => $e->getMessage()]
                ];
            }
            return [
                'res' => 1,
                'message' => 'Logout',
                'data' => ['accessToken' => $accessToken]
            ];
        }
        return $retJwt;
    }

    /**
     * @param string $jwt
     *
     * @return array
     */
    private function validateJwt(string $jwt): array {
        try {
            $decoded = JWT::decode($jwt, self::JWT_KEY, ['HS256']);
            $ret = [
                'res' => 1,
                'message' => 'JWT OK',
                'data' => (array)$decoded
            ];
        } catch (Exception $e) {
            $ret = [
                'res' => 0,
                'message' => 'JWT: ' . $e->getMessage(),
                'data' => ['status' => 0]
            ];
        }

        return $ret;
    }

    /**
     *
     * @param string $token
     *
     * @return array
     */
    public function authentication(string $token): array {
        $retJwt = $this->validateJwt($token);
        if ($retJwt['res'] === 1) {
            $accessToken = $retJwt['data']['access'] ?? '';
            $accesTokenExp = $retJwt['data']['exp'] ?? 0;
            if ($accesTokenExp >= time()) {
                $retToken = $this->getUserIdByToken($accessToken);
                if ($retToken === 1) {
                    return [
                        'res' => 1,
                        'message' => 'User ok',
                        'data' => [
                            'status' => 1,
                            'user_id' => $retToken['data']['user_id']
                        ]
                    ];
                } else {
                    return $retToken;
                }
            } else {
                return [
                    'res' => 0,
                    'message' => 'Token expired, refresh!',
                    'data' => ['status' => 2]
                ];
            }

        }
        return $retJwt;
    }

    /**
     * @param string $token
     *
     * @return array
     */
    private function getUserIdByToken(string $token): array {
        $ret = [
            'res' => 0,
            'message' => 'User not found',
            'data' => ['status' => 0]
        ];


        $tokenUserKey = self::TOKEN_USER_KEY . $token;
        $cache = $this->getCache();
        $userId = $cache->get($tokenUserKey);

        if (!empty($userId)) {
            $ret = [
                'res' => 1,
                'message' => 'User id ok',
                'data' => ['user_id' => $userId]
            ];
        }
        return $ret;
    }

    public function refreshToken(string $token): array {
        $retJwt = $this->validateJwt($token);
        if ($retJwt['res'] === 1) {
            $accessToken = $retJwt['data']['access'] ?? '';
            $retToken = $this->getUserIdByToken($accessToken);
            if ($retToken['res'] === 1) {
                $resNewToken = $this->getToken($retToken['data']['user_id']);
                if ($resNewToken['res'] == 1) {
                    $newToken = $resNewToken['data']['token'];
                    $cache = $this->getCache();
                    $cache->delete(self::TOKEN_USER_KEY . $accessToken);
                    return [
                        'res' => 1,
                        'message' => 'User ok',
                        'data' => [
                            'token' => $newToken
                        ]
                    ];
                } else {
                    return $resNewToken;
                }
            } else {
                return $retToken;
            }
        }
        return $retJwt;
    }
}
