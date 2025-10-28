<?php

class Server_Manager_Mofh extends Server_Manager
{
    public static function getForm(): array
    {
        return [
            'label' => 'MOFH',
            'form' => [
                'credentials' => [
                    'fields' => [
                        [
                            'name' => 'host',
                            'type' => 'text',
                            'label' => 'Server Host',
                            'placeholder' => 'Hostname (e.g. mofhreseller.com)',
                            'required' => true,
                        ],
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
                        [
                            'name' => 'port',
                            'type' => 'text',
                            'label' => 'Port',
                            'placeholder' => '2087',
                            'required' => false,
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
        $this->_config['port'] = $this->_config['port'] ?? '2087';
        $this->_config['secure'] = true;
    }

    public function getLoginUrl(?Server_Account $account = null): string
    {
        return 'https://' . $this->_config['host'] . ':2083';
    }

    public function getResellerLoginUrl(?Server_Account $account = null): string
    {
        return 'https://' . $this->_config['host'] . ':2087';
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
            'username' => $account->getUsername(),
            'domain' => $account->getDomain(),
            'password' => $account->getPassword(),
            'contactemail' => $client->getEmail(),
            'plan' => $this->getPackageName($package),
        ];
        $json = $this->request('createacct', $params);
        return isset($json->result[0]->status) && $json->result[0]->status == 1;
    }

    public function suspendAccount(Server_Account $account): bool
    {
        $params = ['user' => $account->getUsername(), 'reason' => $account->getNote()];
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
        $params = ['user' => $account->getUsername(), 'keepdns' => 0];
        $this->request('removeacct', $params);
        return true;
    }

    public function changeAccountPackage(Server_Account $account, Server_Package $package): bool
    {
        $params = [
            'user' => $account->getUsername(),
            'pkg' => $this->getPackageName($package),
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
        $return = [];
        foreach ($pkgs->package as $pkg) {
            $return[] = [
                'title' => $pkg->name,
                'name' => $pkg->name,
                'quota' => $pkg->QUOTA ?? null,
                'bandwidth' => $pkg->BWLIMIT ?? null,
            ];
        }
        return $return;
    }

private function request(string $action, array $params = []): mixed
{
    $protocol = $this->_config['secure'] ? 'https' : 'http';
    $host = $this->_config['host'];
    $port = $this->_config['port'] ?? '';
    $url = $protocol . '://' . $host;

    // Only add :port if port is not empty and not default for protocol
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

    // Optionally, don't verify SSL certificate (for self-signed on MOFH servers, but should be TRUE in production!)
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

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

// Collect possible error messages for debugging
$errorMessages = [];

// Handle WHM/MOFH legacy and variant APIs
if (isset($json->cpanelresult)) {
    $cp = $json->cpanelresult;

    // Check for an explicit 'error' property
    if (isset($cp->error) && $cp->error) {
        $errorMessages[] = $cp->error;
    }

    // Check if data->result exists and is '0'
    if (isset($cp->data->result) && (string)$cp->data->result === '0') {
        if (isset($cp->data->reason)) {
            $errorMessages[] = $cp->data->reason;
        }
        // fallback if no reason
        if (empty($errorMessages)) {
            $errorMessages[] = 'Unknown API error (data->result == 0)';
        }
    }
}

// Check for WHM classic JSON status
if (
    (isset($json->result) && is_array($json->result) && isset($json->result[0]->status) && $json->result[0]->status == 0)
    || (isset($json->status) && $json->status != 1)
) {
    if (isset($json->result[0]->statusmsg)) {
        $errorMessages[] = $json->result[0]->statusmsg;
    } elseif (isset($json->statusmsg)) {
        $errorMessages[] = $json->statusmsg;
    } else {
        $errorMessages[] = 'Unknown API error (status==0)';
    }
}

// Handle null response arrays (like user/domain lookups)
if (
    (isset($json->userdomains) && $json->userdomains === null)
    || (isset($json->domainuser) && $json->domainuser === null)
) {
    $errorMessages[] = 'No result returned from the API (null array)';
}

// If there are any error messages, throw them
if (!empty($errorMessages)) {
    $msg = implode(' | ', $errorMessages);
    throw new Server_Exception("MOFH API error calling '$action': $msg");
}

// Fallback: If everything else looks fine, but the key result is missing (very defensive)
if (!isset($json->result) && !isset($json->cpanelresult) && !isset($json->status)) {
    throw new Server_Exception("MOFH API: Unknown response format or missing result for '$action'");
}

    return $json;
}


    private function getPackageName(Server_Package $package): string
    {
        return $package->getName();
    }
	// Example for changeAccountUsername
public function changeAccountUsername(Server_Account $account, string $newUsername): never
{
    throw new Server_Exception(
        ':type: does not support :action:',
        [
            ':type:' => 'MOFH',
            ':action:' => __trans('username changes')
        ]
    );
}

// Example for changeAccountDomain
public function changeAccountDomain(Server_Account $account, string $newDomain): never
{
    throw new Server_Exception(
        ':type: does not support :action:',
        [
            ':type:' => 'MOFH',
            ':action:' => __trans('domain changes')
        ]
    );
}

// Example for changeAccountIp
public function changeAccountIp(Server_Account $account, string $newIp): never
{
    throw new Server_Exception(
        ':type: does not support :action:',
        [
            ':type:' => 'MOFH',
            ':action:' => __trans('IP address changes')
        ]
    );
}

// Example for synchronizeAccount
public function synchronizeAccount(Server_Account $account): never
{
    throw new Server_Exception(
        ':type: does not support :action:',
        [
            ':type:' => 'MOFH',
            ':action:' => __trans('account synchronization')
        ]
    );
}

}
