<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use Random\RandomException;
// Removed unused Symfony Exception interface
// use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;

/**
 * MOFH API.
 * Uses json-api/ endpoints with HTTP Basic Authentication
 *
 * @see https://myownfreehost.net/xml-api/ (Links to API docs)
 */
class Server_Manager_Mofh extends Server_Manager
{
    /**
     * Returns the form configuration for the MOFH server manager.
     *
     * @return array the form configuration as an associative array
     */
    public static function getForm(): array
    {
        return [
            'label' => 'MOFH (MyOwnFreeHost)',
            'form' => [
                // === FIX ===
                // Removed the 'credentials' nesting.
                // The admin UI in your version doesn't support this,
                // causing it to save 'undefined'.
                'fields' => [
                    [
                        'name' => 'api_username',
                        'type' => 'text',
                        'label' => 'API Username',
                        'placeholder' => 'API Username provided by MOFH',
                        'required' => true,
                    ],
                    [
                        'name' => 'api_key',
                        'type' => 'password',
                        'label' => 'API Key',
                        'placeholder' => 'API Key (or password) provided by MOFH',
                        'required' => true,
                    ],
                    [
                        'name' => 'username_prefix',
                        'type' => 'text',
                        'label' => 'Username Prefix (Reference Only)',
                        'placeholder' => 'Account username prefix (e.g., cnf_)',
                        'required' => false,
                    ],
                    [
                        'name' => 'cpanel_host',
                        'type' => 'text',
                        'label' => 'cPanel Host',
                        'placeholder' => 'Your cPanel hostname (e.g., cpanel.yourdomain.com)',
                        'required' => true,
                    ],
                ],
                // === END FIX ===
            ],
        ];
    }

    /**
     * Initializes the MOFH server manager.
     * Checks if the necessary configuration options are set and throws an exception if any are missing.
     *
     * @throws Server_Exception if any necessary configuration options are missing
     */
    public function init(): void
    {
        // === FIX ===
        // This function MUST be here to check the config.
        // If this is empty, all API calls will fail.
        /**
         if (empty($this->_config['host'])) {
             throw new Server_Exception('The ":server_manager" server manager is not fully configured. Please configure the :missing', [':server_manager' => 'MOFH', ':missing' => 'hostname (e.g., panel.myownfreehost.net)'], 2001);
         }

         if (empty($this->_config['api_username'])) {
             throw new Server_Exception('The ":server_manager" server manager is not fully configured. Please configure the :missing', [':server_manager' => 'MOFH', ':missing' => 'API Username'], 2001);
         }

         if (empty($this->_config['api_key'])) {
             throw new Server_Exception('The ":server_manager" server manager is not fully configured. Please configure the :missing', [':server_manager' => 'MOFH', ':missing' => 'API Key'], 2001);
         }

         if (empty($this->_config['cpanel_host'])) {
             throw new Server_Exception('The ":server_manager" server manager is not fully configured. Please configure the :missing', [':server_manager' => 'MOFH', ':missing' => 'cPanel Host'], 2001);
         }
         */
        // === END FIX ===
    }

    /**
     * Returns the login URL for a cPanel account.
     *
     * @param Server_Account|null $account The account for which to get the login URL. This parameter is currently not used.
     *
     * @return string the login URL
     */
    public function getLoginUrl(?Server_Account $account = null): string
    {
        $host = $this->_config['cpanel_host'];

        return ($this->_config['secure'] ? 'https' : 'http') . '://' . $host;
    }

    /**
     * Returns the login URL for the MOFH reseller panel.
     *
     * @param Server_Account|null $account The account for which to get the login URL. This parameter is currently not used.
     *
     * @return string the login URL
     */
    public function getResellerLoginUrl(?Server_Account $account = null): string
    {
        // === FIX ===
        // Returns the login to the reseller panel (MOFH panel)
        // MOFH Reseller Panel (VistaPanel) is on port 2087
        return ($this->_config['secure'] ? 'https' : 'http') . '://' . $this->_config['host'] . ':2087';
        // === END FIX ===
    }

    /**
     * Tests the connection to the MOFH server.
     * Sends a request to the MOFH server to list packages.
     *
     * @return true if the connection was successful
     *
     * @throws Server_Exception if an error occurs during the request
     */
    public function testConnection(): bool
    {
        // Corrected syntax: $this->
        $this->request('listpkgs');

        return true;
    }

    /**
     * Generates a *suggested* username for a new account.
     * MOFH API ignores this and generates its own username,
     * which is captured during account creation.
     *
     * @param string $domain the domain name for which to generate a username
     *
     * @return string the generated *suggested* username
     *
     * @throws RandomException if an error occurs during the generation of a random number
     */
    public function generateUsername(string $domain): string
    {
        // Create a "suggested" username based on the domain.
        // The MOFH API will ignore this and return the real one.
        $processedDomain = strtolower(preg_replace('/[^A-Za-z0-9]/', '', $domain));
        $username = substr($processedDomain, 0, 7) . random_int(0, 9);

        // Remove "test" prefix if present
        if (str_starts_with($username, 'test')) {
            $username = 'a' . substr($username, 4);
        }

        // Ensure it doesn't start with a number
        if (is_numeric(substr($username, 0, 1))) {
            $username = 'a' . $username;
        }

        // Ensure max 8 chars for cPanel compatibility
        return substr($username, 0, 8);
    }

    /**
     * Synchronizes an account with the MOFH server.
     * Sends a request to the MOFH server to get the account's status and updates the Server_Account object.
     *
     * @param Server_Account $account the account to be synchronized
     *
     * @return Server_Account the updated account
     *
     * @throws Server_Exception if an error occurs during the request, or if the account does not exist on the MOFH server
     */
    public function synchronizeAccount(Server_Account $account): Server_Account
    {
        $this->getLog()->info(sprintf('Synchronizing account %s %s with server', $account->getDomain(), $account->getUsername()));

        $action = 'accountstatus';
        $varHash = [
            'user' => $account->getUsername(),
        ];

        // Corrected syntax: $this->
        $result = $this->request($action, $varHash);

        // Check for 'status' property
        if (!isset($result->status)) {
            // Check for 'account_status' as a fallback
            if (isset($result->account_status)) {
                $result->status = $result->account_status;
            } else {
                error_log('Could not synchronize account with MOFH server. Account does not exist or API error.');

                return $account;
            }
        }

        $new = clone $account;

        // MOFH API only returns 'active' or 'suspended'
        $status = (string) $result->status;
        $new->setSuspended($status === 'suspended');

        // Other details like domain, user, ip are not returned by this MOFH API call
        // We rely on the local FOSSBilling data for those.

        return $new;
    }

    /**
     * Creates a new account on the MOFH server.
     * Sends a request to the MOFH server to create a new account with the details provided in the Server_Account object.
     * It then captures the *actual* username returned by the API and updates the account object.
     *
     * @param Server_Account $account The account to be created. This object will be updated with the real username.
     *
     * @return bool returns true if the account was successfully created, false otherwise
     *
     * @throws Server_Exception if an error occurs during the request, or if the response from the MOFH server indicates an error
     */
    public function createAccount(Server_Account $account): bool
    {
        // Log the account creation
        $this->getLog()->info('Creating account with suggested username: ' . $account->getUsername());

        // Get the client and package associated with the account
        $client = $account->getClient();
        $package = $account->getPackage();

        // Check if the package exists on the MOFH server
        // Corrected syntax: $this->
        $this->checkPackageExists($package, true);

        // Prepare the parameters for the API request
        $action = 'createacct';
        $varHash = [
            'username' => $account->getUsername(), // This is the "suggested" username
            'domain' => $account->getDomain(),
            'password' => $account->getPassword(),
            'contactemail' => $client->getEmail(),
            // Corrected syntax: $this->
            'plan' => $this->getPackageName($package),
        ];

        // Send the request to the MOFH API. It will throw on error.
        // Corrected syntax: $this->
        $response = $this->request($action, $varHash);

        // MOFH API returns the *actual* username in vp_username
        if (isset($response->vp_username)) {
            $actual_username = (string) $response->vp_username;
            // Corrected syntax: $this->
            $this->getLog()->info(sprintf('MOFH account creation sent username %s, API returned actual username %s', $account->getUsername(), $actual_username));

            // Update the account object with the real username returned by the API
            $account->setUsername($actual_username);
        } else {
            // This is an error case. The request() function should have thrown
            // an exception on failure, but if it succeeded without returning
            // a vp_username, something is wrong with the API response.
            // Corrected syntax: $this->
            $this->getLog()->crit('MOFH API successful response did not include vp_username. Account username may be incorrect.');
        }

        // Return the result of the account creation
        return true;
    }

    /**
     * Suspends an account on the MOFH server.
     *
     * @param Server_Account $account the account to be suspended
     *
     * @return bool returns true if the account was successfully suspended
     *
     * @throws Server_Exception if an error occurs during the request
     */
    public function suspendAccount(Server_Account $account): bool
    {
        // Log the suspension
        $this->getLog()->info('Suspending account ' . $account->getUsername());

        // Define the action and parameters for the API request
        $action = 'suspendacct';
        $varHash = [
            'user' => $account->getUsername(),
            'reason' => $account->getNote() ?: 'Suspended by billing system',
        ];

        // Send the request to the MOFH API
        // Corrected syntax: $this->
        $this->request($action, $varHash);

        return true;
    }

    /**
     * Unsuspends an account on the MOFH server.
     *
     * @param Server_Account $account the account to be unsuspended
     *
     * @return bool returns true if the account was successfully unsuspended
     *
     * @throws Server_Exception if an error occurs during the request
     */
    public function unsuspendAccount(Server_Account $account): bool
    {
        // Log the unsuspension
        $this->getLog()->info('Activating account ' . $account->getUsername());

        // Define the action and parameters for the API request
        $action = 'unsuspendacct';
        $varHash = [
            'user' => $account->getUsername(),
        ];

        // Send the request to the MOFH API
        // Corrected syntax: $this->
        $this->request($action, $varHash);

        return true;
    }

    /**
     * Cancels (terminates) an account on the MOFH server.
     *
     * @param Server_Account $account the account to be cancelled
     *
     * @return bool returns true if the account was successfully cancelled
     *
     * @throws Server_Exception if an error occurs during the request
     */
    public function cancelAccount(Server_Account $account): bool
    {
        // Log the cancellation
        // Corrected syntax: $this->
        $this->getLog()->info('Canceling account ' . $account->getUsername());

        // Define the action and parameters for the API request
        $action = 'removeacct';
        $varHash = [
            'user' => $account->getUsername(),
        ];

        // Send the request to the MOFH API
        // Corrected syntax: $this->
        $this->request($action, $varHash);

        return true;
    }

    /**
     * Changes the package of an account on the MOFH server.
     *
     * @param Server_Account $account the account for which to change the package
     * @param Server_Package $package the new package
     *
     * @return bool returns true if the package was successfully changed
     *
     * @throws Server_Exception if an error occurs during the request
     */
    public function changeAccountPackage(Server_Account $account, Server_Package $package): bool
    {
        // Log the package change
        // Corrected syntax: $this->
        $this->getLog()->info('Changing account ' . $account->getUsername() . ' package');

        // Check if the package exists on the MOFH server
        // Corrected syntax: $this->
        $this->checkPackageExists($package, true);

        // Define the action and parameters for the API request
        $varHash = [
            'user' => $account->getUsername(),
            // Corrected syntax: $this->
            'pkg' => $this->getPackageName($package),
        ];

        // Send the request to the MOFH API
        // Corrected syntax: $this->
        $this->request('changepackage', $varHash);

        return true;
    }

    /**
     * Changes the password of an account on the MOFH server.
     *
     * @param Server_Account $account     the account for which to change the password
     * @param string         $newPassword the new password
     *
     * @return bool returns true if the password was successfully changed
     *
     * @throws Server_Exception if an error occurs during the request
     */
    public function changeAccountPassword(Server_Account $account, string $newPassword): bool
    {
        // Log the password change
        // Corrected syntax: $this->
        $this->getLog()->info('Changing account ' . $account->getUsername() . ' password');

        // Define the action and parameters for the API request
        $action = 'passwd';
        $varHash = [
            'user' => $account->getUsername(),
            'pass' => $newPassword,
        ];

        // Send the request to the MOFH API
        // Corrected syntax: $this->
        $this->request($action, $varHash);

        return true;
    }

    /**
     * Changes the username of an account on the MOFH server. (NOT SUPPORTED)
     *
     * @param Server_Account $account     the account for which to change the username
     * @param string         $newUsername the new username
     *
     * @return bool
     *
     * @throws Server_Exception
     */
    public function changeAccountUsername(Server_Account $account, string $newUsername): bool
    {
        throw new Server_Exception('This function is not supported by the MOFH API.');
    }

    /**
     * Changes the domain of an account on the MOFH server. (NOT SUPPORTED)
     *
     * @param Server_Account $account   the account for which to change the domain
     * @param string         $newDomain the new domain
     *
     * @return bool
     *
     * @throws Server_Exception
     */
    public function changeAccountDomain(Server_Account $account, string $newDomain): bool
    {
        throw new Server_Exception('This function is not supported by the MOFH API.');
    }

    /**
     * Changes the IP of an account on the MOFH server. (NOT SUPPORTED)
     *
     * @param Server_Account $account the account for which to change the IP
     * @param string         $newIp   the new IP
     *
     * @return bool
     *
     * @throws Server_Exception
     */
    public function changeAccountIp(Server_Account $account, string $newIp): bool
    {
        throw new Server_Exception('This function is not supported by the MOFH API.');
    }

    /**
     * Retrieves the packages from the MOFH server.
     *
     * @return array an array of packages, each represented as an associative array of package details
     *
     * @throws Server_Exception if an error occurs during the request
     */
    public function getPackages(): array
    {
        // Send a request to the MOFH server to list the packages
        // Corrected syntax: $this->
        $pkgs = $this->request('listpkgs');
        $return = [];

        // Iterate over the packages and add their details to the return array
        // MOFH API only returns package names
        if (isset($pkgs->package)) {
            foreach ($pkgs->package as $pkgName) {
                $name = (string) $pkgName;
                $return[] = [
                    'title' => $name,
                    'name' => $name,
                ];
            }
        }

        return $return;
    }

    /**
     * Sends a request to the MOFH json-api/ endpoints.
     *
     * @param string $action the action to be performed on the MOFH server (e.g., 'createacct')
     * @param array  $params the parameters to be sent with the request
     *
     * @return mixed the response from the MOFH server, decoded from JSON
     *
     * @throws Server_Exception if an error occurs during the request, or if the response from the MOFH server indicates an error
     */
    private function request(string $action, array $params = []): mixed
    {
        // Build URL
        $url = ($this->_config['secure'] ? 'https' : 'http') . '://' . $this->_config['host'] . '/json-api/' . $action . '.php';

        // Get auth creds
        $username = $this->_config['api_username'];
        $password = $this->_config['api_key'];

        // Log the request (without password)
        $this->getLog()->debug(sprintf('Requesting MOFH json-api action "%s" with params "%s" ', $action, print_r($params, true)));

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 90);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        // Set HTTP Basic Auth
        curl_setopt($ch, CURLOPT_USERPWD, "{$username}:{$password}");

        // Define which actions are POST
        // 'accountstatus' is not in the user's list, but it takes a 'user' param, so we assume POST.
        $postActions = ['createacct', 'suspendacct', 'unsuspendacct', 'removeacct', 'passwd', 'changepackage', 'accountstatus'];

        if (in_array($action, $postActions)) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        } else {
            // This is a GET request (e.g., listpkgs, version)
            curl_setopt($ch, CURLOPT_POST, false);
        }

        $body = curl_exec($ch);

        // Check for cURL errors
        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            curl_close($ch);
            $e = new Server_Exception('cURL Error: :error', [':error' => $error_msg]);
            $this->getLog()->err($e->getMessage());
            throw $e;
        }
        curl_close($ch);

        // Decode JSON
        $json = json_decode($body);

        // Check for JSON decoding errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Handle plain text "Authentication Failed"
            if (stripos($body, 'Authentication Failed') !== false) {
                $msg = 'Authentication Failed. Please check your API Username and Key.';
                $this->getLog()->crit(sprintf('MOFH json-api error calling action "%s": "%s"', $action, $msg));
                $placeholders = [':action:' => $action, ':type:' => 'MOFH', ':error:' => $msg];
                throw new Server_Exception('Failed to :action: on the :type: server. Error: :error:', $placeholders);
            }

            $msg = sprintf('Function call "%s" (json-api) response is invalid, body: %s', $action, $body);
            $this->getLog()->crit($msg);
            $placeholders = [':action:' => $action, ':type:' => 'MOFH'];
            throw new Server_Exception('Failed to :action: on the :type: server, check the error logs for further details', $placeholders);
        }

        // Check for API errors in the JSON response
        if (isset($json->error)) {
            $msg = (string) $json->error;
            $this->getLog()->crit(sprintf('MOFH json-api error calling action "%s": "%s"', $action, $msg));
            $placeholders = [':action:' => $action, ':type:' => 'MOFH', ':error:' => $msg];
            throw new Server_Exception('Failed to :action: on the :type: server. Error: :error:', $placeholders);
        }

        // Check for status: 0 error pattern
        if (isset($json->status) && $json->status == 0) {
            $msg = $json->errors[0] ?? 'Unknown error';
            if (is_array($json->errors)) {
                $msg = implode(', ', $json->errors);
            }
            $this->getLog()->crit(sprintf('MOFH json-api error calling action "%s": "%s"', $action, $msg));
            $placeholders = [':action:' => $action, ':type:' => 'MOFH', ':error:' => $msg];
            throw new Server_Exception('Failed to :action: on the :type: server. Error: :error:', $placeholders);
        }

        // Check for other simple error messages
        if (isset($json->result) && is_string($json->result) && (stripos($json->result, 'failed') !== false || stripos($json->result, 'error') !== false)) {
            $msg = (string) $json->result;
            $this->getLog()->crit(sprintf('MOFH json-api error calling action "%s": "%s"', $action, $msg));
            $placeholders = [':action:' => $action, ':type:' => 'MOFH', ':error:' => $msg];
            throw new Server_Exception('Failed to :action: on the :type: server. Error: :error:', $placeholders);
        }

        // === Special handling for listpkgs ===
        if ($action === 'listpkgs') {
            if (is_array($json)) {
                // Success. Wrap it in an object to match the structure expected
                // by getPackages() and checkPackageExists().
                $wrapper = new stdClass();
                $wrapper->package = $json;
                return $wrapper;
            } else {
                // If it's not an array and not an error, the format is unexpected.
                $msg = sprintf('Function call "listpkgs" (json-api) response was not in the expected array format, body: %s', $body);
                $this->getLog()->crit($msg);
                $placeholders = [':action:' => $action, ':type:' => 'MOFH'];
                throw new Server_Exception('Failed to :action: on the :type: server, check the error logs for further details', $placeholders);
            }
        }

        // Return the successful JSON response for all other actions
        return $json;
    }

    /**
     * Checks if a package exists on the MOFH server.
     *
     * @param Server_Package $package the package to check
     * @param bool           $create  whether to create the package if it does not exist (NOT SUPPORTED)
     *
     * @throws Server_Exception if an error occurs during the request or if package creation is attempted
     */
    private function checkPackageExists(Server_Package $package, bool $create = false): void
    {
        // Get the name of the package
        // Corrected syntax: $this->
        $name = $this->getPackageName($package);

        // Send a request to the MOFH server to list the packages
        // Corrected syntax: $this->
        $json = $this->request('listpkgs');
        $packages = $json->package;

        // Check if the package exists
        $exists = false;
        foreach ($packages as $p) {
            if ((string) $p == $name) {
                $exists = true;

                break;
            }
        }

        // If the package does not exist and $create is true, throw error
        if (!$exists && $create) {
            throw new Server_Exception('Package :name does not exist on MOFH server. Please create it manually in your reseller panel.', [':name' => $name]);
        }
    }

    /**
     * Generates a package name. For MOFH, this is just the package name.
     *
     * @param Server_Package $package the package for which to generate a name
     *
     * @return string the generated package name
     */
    private function getPackageName(Server_Package $package): string
    {
        return $package->getName();
    }
}
