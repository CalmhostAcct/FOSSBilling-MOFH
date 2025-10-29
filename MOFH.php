<?php

class Server_Manager_MOFH extends Server_Manager
{
    public static function getForm(): array
    {
        return [
            'label' => 'MyOwnFreeHost (MOFH)',
            'form' => [
                'credentials' => [
                    'fields' => [
                        [
                            'name' => 'username',
                            'type' => 'text',
                            'label' => 'API Username',
                            'placeholder' => 'MOFH API username',
                            'required' => true,
                        ],
                        [
                            'name' => 'password',
                            'type' => 'password',
                            'label' => 'API Password',
                            'placeholder' => 'MOFH API password',
                            'required' => true,
                        ],
                    ],
                ],
            ],
        ];
    }

    public function init(): void
    {
        if (empty($this->_config['host'])) {
            throw new Server_Exception('Please configure the MOFH server hostname.');
        }

        if (empty($this->_config['username']) || empty($this->_config['password'])) {
            throw new Server_Exception('Please configure MOFH API username and password.');
        }

        $this->_config['port'] = '2087';
        $this->_config['secure'] = true;
    }

    public function getLoginUrl(?Server_Account $account = null): string
    {
        return 'https://cpanel.' . $this->_config['host'];
    }

    public function getResellerLoginUrl(?Server_Account $account = null): string
    {
        return 'https://panel.myownfreehost.net:2087';
    }

    public function testConnection(): bool
    {
        $result = $this->request('listpkgs');
        return !empty($result);
    }

    public function generateUsername(string $domain): string
    {
        $base = strtolower(preg_replace('/[^a-z0-9]/', '', $domain));
        return substr($base, 0, 7) . random_int(0, 9);
    }

    public function createAccount(Server_Account $account): bool
    {
        $client = $account->getClient();
        $package = $account->getPackage();

        $params = [
            'username'     => $account->getUsername(),
            'domain'       => $account->getDomain(),
            'password'     => $account->getPassword(),
            'contactemail' => $client->getEmail(),
            'plan'         => $this->getPackageName($package),
        ];

        $json = $this->request('createacct', $params);

        $this->setUsername($json->result[0]->vp_username);
        $this->setPassword($account->getPassword());

        return isset($json->result[0]->status) && $json->result[0]->status == 1;
    }

    public function suspendAccount(Server_Account $account): bool
    {
        $params = [
            'user'   => $account->getUsername(),
            'reason' => $account->getNote(),
        ];
        $this->request('suspendacct', $params);
        return true;
    }

    public function unsuspendAccount(Server_Account $account): bool
    {
        $params = ['user' => $account->getUsername()];
        $this->request('unsuspendacct', $params);
        return true;
    }

    public function cancelAccount(Server_Account $account): bool
    {
        $params = [
            'user'    => $account->getUsername(),
            'keepdns' => 0,
        ];
        $this->request('removeacct', $params);
        return true;
    }

    public function changeAccountPackage(Server_Account $account, Server_Package $package): bool
    {
        $params = [
            'user' => $account->getUsername(),
            'pkg'  => $this->getPackageName($package),
        ];
        $this->request('changepackage', $params);
        return true;
    }

    public function changeAccountPassword(Server_Account $account, string $newPassword): bool
    {
        $params = [
            'user' => $account->getUsername(),
            'pass' => $newPassword,
        ];

        $result = $this->request('passwd', $params);

        if (isset($result->passwd[0]) && $result->passwd[0]->status == 0) {
            throw new Server_Exception($result->passwd[0]->statusmsg);
        }

        return true;
    }

    public function getPackages(): array
    {
        $pkgs = $this->request('listpkgs');

        if (!isset($pkgs->package) || !is_array($pkgs->package)) {
            return [];
        }

        $return = [];

        foreach ($pkgs->package as $pkg) {
            if (!is_object($pkg)) {
                continue;
            }

            $intOrNull = function ($val) {
                return is_numeric($val) ? (int)$val : null;
            };

            $return[] = [
                'title'     => isset($pkg->name) ? (string)$pkg->name : '',
                'name'      => isset($pkg->name) ? (string)$pkg->name : '',
                'quota'     => isset($pkg->QUOTA) ? $intOrNull($pkg->QUOTA) : null,
                'bandwidth' => isset($pkg->BWLIMIT) ? $intOrNull($pkg->BWLIMIT) : null,
            ];
        }

        return $return;
    }

    private function request(string $action, array $params = []): mixed
    {
        $protocol = $this->_config['secure'] ? 'https' : 'http';
        $host = "panel.myownfreehost.net";
        $port = $this->_config['port'] ?? '';
        $url = $protocol . '://' . $host;

        if ($port && !(($protocol === 'https' && $port == '443') || ($protocol === 'http' && $port == '80'))) {
            $url .= ':' . $port;
        }

        $url .= "/json-api/{$action}?api.version=1";

        $ch = curl_init($url);
        $auth = base64_encode($this->_config['username'] . ':' . $this->_config['password']);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Basic $auth",
            "Content-Type: application/x-www-form-urlencoded"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($errno) {
            throw new Server_Exception("cURL error calling MOFH API: [$errno] $error");
        }

        if ($body === false) {
            throw new Server_Exception("Empty response from MOFH API endpoint: $url");
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new Server_Exception("HTTP $httpCode error from MOFH API: $body");
        }

        $json = json_decode($body);

        if (!is_object($json)) {
            throw new Server_Exception("Invalid JSON response from MOFH API: $body");
        }

        $errorMessages = [];

        if (isset($json->cpanelresult)) {
            $cp = $json->cpanelresult;

            if (isset($cp->error) && $cp->error) {
                $errorMessages[] = $cp->error;
            }

            if (isset($cp->data->result) && (string)$cp->data->result === '0') {
                $errorMessages[] = $cp->data->reason ?? 'Unknown API error (data->result == 0)';
            }
        }

        if (
            (isset($json->result[0]->status) && $json->result[0]->status == 0) ||
            (isset($json->status) && $json->status != 1)
        ) {
            $errorMessages[] = $json->result[0]->statusmsg
                ?? $json->statusmsg
                ?? 'Unknown API error (status==0)';
        }

        if (
            (isset($json->userdomains) && $json->userdomains === null) ||
            (isset($json->domainuser) && $json->domainuser === null)
        ) {
            $errorMessages[] = 'No result returned from the API (null array)';
        }

        if (!empty($errorMessages)) {
            throw new Server_Exception("MOFH API error calling '$action': " . implode(' | ', $errorMessages));
        }

        if (!isset($json->result) && !isset($json->cpanelresult) && !isset($json->status)) {
            throw new Server_Exception("MOFH API: Unknown response format or missing result for '$action'");
        }

        return $json;
    }

    private function getPackageName(Server_Package $package): string
    {
        return $package->getName();
    }

    public function changeAccountUsername(Server_Account $account, string $newUsername): never
    {
        throw new Server_Exception(
            ':type: does not support :action:',
            [
                ':type:' => 'MOFH',
                ':action:' => __trans('username changes'),
            ]
        );
    }

    public function changeAccountDomain(Server_Account $account, string $newDomain): never
    {
        throw new Server_Exception(
            ':type: does not support :action:',
            [
                ':type:' => 'MOFH',
                ':action:' => __trans('domain changes'),
            ]
        );
    }

    public function changeAccountIp(Server_Account $account, string $newIp): never
    {
        throw new Server_Exception(
            ':type: does not support :action:',
            [
                ':type:' => 'MOFH',
                ':action:' => __trans('IP address changes'),
            ]
        );
    }

    public function synchronizeAccount(Server_Account $account): never
    {
        throw new Server_Exception(
            ':type: does not support :action:',
            [
                ':type:' => 'MOFH',
                ':action:' => __trans('account synchronization'),
            ]
        );
    }
}
