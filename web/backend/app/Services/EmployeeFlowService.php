<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Exception;

class EmployeeFlowService
{
    protected string $baseUrl;
    protected string $username;
    protected string $password;
    protected ?string $token = null;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.employee_flow.base_url'), '/');
        $this->username = config('services.employee_flow.username');
        $this->password = config('services.employee_flow.password');

        if (empty($this->baseUrl) || empty($this->username) || empty($this->password)) {
            throw new Exception("Employee Flow API credentials are not fully configured.");
        }
    }

    /**
     * Authenticate with the Employee Flow API and get the access token.
     *
     * @return string
     * @throws Exception
     */
    public function login(): string
    {
        if ($this->token) {
            return $this->token;
        }

        $response = Http::withoutVerifying()->post("{$this->baseUrl}/auth/login", [
            'username' => $this->username,
            'password' => $this->password,
        ]);

        if ($response->failed()) {
            throw new Exception("Failed to login to Employee Flow API: " . $response->body());
        }

        $data = $response->json();
        
        if (empty($data['access_token'])) {
            throw new Exception("Access token not found in login response.");
        }

        $this->token = $data['access_token'];
        return $this->token;
    }

    /**
     * Get personnel data from the Employee Flow API.
     *
     * @return array
     * @throws Exception
     */
    public function getPersonal(): array
    {
        $token = $this->login();

        $response = Http::withoutVerifying()
            ->withToken($token)
            ->timeout(60)
            ->get("{$this->baseUrl}/personal");

        if ($response->failed()) {
            throw new Exception("Failed to fetch personal data: " . $response->body());
        }

        return $response->json();
    }
}
