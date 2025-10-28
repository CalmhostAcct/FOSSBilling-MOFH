<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 */

class Server_Manager_MOFH extends Server_Manager
{
    private string $xmlApiBase = 'https://panel.myownfreehost.net/xml-api';
    private string $jsonApiBase = 'https://panel.myownfreehost.net/json-api';
    private string $apiUsername;
    private string $apiPassword;
    private string $resellerDomain;

    public static function getForm(): array
    {
        return [
            'label' => 'MyOwnFreeHost (MOFH)',
            'form' => [
                'api_username' => [
                    'text', [
                        'label' => 'API Username',
                        'required' => true,
                    ],
                ],
                'api_password' => [
                    'password', [
                        'label' => 'API Password',
                        'required' => true,
                    ],
                ],
                'reseller_domain' => [
                    'text', [
                        'label' => 'Reseller Domain (example: myreseller.com)',
                        'required' => true,
                    ],
                ],
            ],
        ];
    }

    public function init()
    {
        $cfg = $this->_config ?? [];
        if (empty($cfg['api_username']) || empty($cfg['api_password']) || empty($cfg['reseller_domain'])) {
            throw new Server_Exception('MOFH API credentials or reseller domain are missing.');
        }

        $this->apiUsername = $cfg['api_username'];
        $this->apiPassword = $cfg['api_password'];
        $this->resellerDomain = preg_replace('/^https?:\/\//', '', trim($cfg['reseller_domain']));
    }

    /**
     * Core XML API request handler.
     */
    private function callXmlApi(string $function, array $params = []): SimpleXMLElement
    {
        $params['api_user'] = $this->apiUsername;
        $params['api_key'] = $this->apiPassword;

        $url = rtrim($this->xmlApiBase, '/') . '/' . ltrim($function, '/');

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url . '?' . http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new Server_Exception('MOFH XML API request failed: ' . $error);
        }

        if ($httpCode >= 400) {
            throw new Server_Exception("MOFH XML API returned HTTP error code $httpCode");
        }

        $xml = @simplexml_load_string($response);
        if ($xml === false) {
            throw new Server_Exception('Invalid XML response from MOFH API.');
        }

        return $xml;
    }

    /**
     * Core JSON API request handler (for listpkgs only).
     */
    private function callJsonApi(string $function, array $params = []): array
    {
        $params['api_user'] = $this->apiUsername;
        $params['api_key'] = $this->apiPassword;

        $url = rtrim($this->jsonApiBase, '/') . '/' . ltrim($function, '/');

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url . '?' . http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new Server_Exception('MOFH JSON API request failed: ' . $error);
        }

        if ($httpCode >= 400) {
            throw new Server_Exception("MOFH JSON API returned HTTP error code $httpCode");
        }

        $json = json_decode($response, true);
        if ($json === null) {
            throw new Server_Exception('Invalid JSON response from MOFH API.');
        }

        return $json;
    }

    /**
     * Test connection via /xml-api/version
     */
    public function testConnection(): bool
    {
        $xml = $this->callXmlApi('version');
        if (isset($xml->version)) {
            $this->getLog()->info('MOFH connection successful. Version: ' . (string)$xml->version);
            return true;
        }

        throw new Server_Exception('Unable to connect to MOFH API.');
    }

    /**
     * Create account via /xml-api/createacct
     */
    public function createAccount(Server_Account $account): bool
    {
        $params = [
            'username'     => $account->getUsername(),
            'password'     => $account->getPassword(),
            'plan'         => $account->getPackage()->getName(),
            'contactemail' => $account->getClientEmail(),
            'domain'       => $account->getDomain(),
        ];

        $xml = $this->callXmlApi('createacct', $params);

        if (isset($xml->result->status) && (string)$xml->result->status === '0') {
            throw new Server_Exception('MOFH: ' . (string)$xml->result->statusmsg);
        }

        $this->getLog()->info('MOFH: Account created successfully.');
        return true;
    }

    public function suspendAccount(Server_Account $account): bool
    {
        $this->callXmlApi('suspendacct', ['user' => $account->getUsername()]);
        $this->getLog()->info('MOFH: Account suspended.');
        return true;
    }

    public function unsuspendAccount(Server_Account $account): bool
    {
        $this->callXmlApi('unsuspendacct', ['user' => $account->getUsername()]);
        $this->getLog()->info('MOFH: Account unsuspended.');
        return true;
    }

    public function cancelAccount(Server_Account $account): bool
    {
        $this->callXmlApi('removeacct', ['user' => $account->getUsername()]);
        $this->getLog()->info('MOFH: Account terminated.');
        return true;
    }

    public function changeAccountPassword(Server_Account $account, string $newPassword): bool
    {
        $this->callXmlApi('passwd', [
            'user'     => $account->getUsername(),
            'password' => $newPassword,
        ]);
        $this->getLog()->info('MOFH: Password changed.');
        return true;
    }

    /**
     * List packages using the JSON API.
     */
    public function listPackages(): array
    {
        $json = $this->callJsonApi('listpkgs');
        $packages = [];

        if (!empty($json['packages'])) {
            foreach ($json['packages'] as $pkg) {
                $packages[] = [
                    'name'      => $pkg['name'] ?? '',
                    'quota'     => $pkg['quota'] ?? '',
                    'bandwidth' => $pkg['bandwidth'] ?? '',
                ];
            }
        }

        return $packages;
    }

    public function changeAccountPackage(Server_Account $account, Server_Package $package): bool
    {
        $this->callXmlApi('changepackage', [
            'user' => $account->getUsername(),
            'pkg'  => $package->getName(),
        ]);

        $this->getLog()->info('MOFH: Package changed to ' . $package->getName());
        return true;
    }

    /**
     * Unsupported features.
     */
    public function changeAccountIp(Server_Account $account, string $newIp): bool
    {
        throw new Server_Exception(':type: does not support :action:', [
            ':type:'  => 'MOFH',
            ':action:' => __trans('changing the account IP'),
        ]);
    }

    public function changeAccountUsername(Server_Account $account, string $newUsername): bool
    {
        throw new Server_Exception(':type: does not support :action:', [
            ':type:'  => 'MOFH',
            ':action:' => __trans('changing the account username'),
        ]);
    }

    public function synchronizeAccount(Server_Account $account): Server_Account
    {
        throw new Server_Exception(':type: does not support :action:', [
            ':type:'  => 'MOFH',
            ':action:' => __trans('account synchronization'),
        ]);
    }

    /**
     * Correct MOFH login URLs.
     */
    public function getLoginUrl(?Server_Account $account = null): string
    {
        return 'https://cpanel.' . $this->resellerDomain;
    }

    public function getResellerLoginUrl(?Server_Account $account = null): string
    {
        return 'https://panel.myownfreehost.net/';
    }
}
