<?php

/**
 * Calagopus API
 *
 * A lightweight cURL based client for the Calagopus admin API.
 *
 * @package blesta
 * @subpackage blesta.components.modules.calagopus.lib
 * @copyright Copyright (c) 2024, Calagopus
 * @link https://calagopus.com Calagopus
 */
class CalagopusApi
{
    /**
     * @var string The base URL of the Calagopus panel (e.g. https://panel.example.com)
     */
    private $base_url;

    /**
     * @var string The admin API key (Bearer token)
     */
    private $api_key;

    /**
     * Initializes the API.
     *
     * @param string $host The hostname or URL of the Calagopus panel
     * @param string $api_key The admin API key
     * @param bool $use_ssl Whether to connect over SSL (default true)
     */
    public function __construct($host, $api_key, $use_ssl = true)
    {
        // A protocol given in the host takes precedence over the use_ssl flag
        $host = trim((string)$host);
        if (preg_match('#^(https?)://#i', $host, $matches)) {
            $protocol = strtolower($matches[1]);
            $host = preg_replace('#^https?://#i', '', $host);
        } else {
            $protocol = $use_ssl ? 'https' : 'http';
        }
        $host = rtrim($host, '/');

        $this->base_url = $protocol . '://' . $host;
        $this->api_key = $api_key;
    }

    /**
     * Performs a request against the Calagopus API.
     *
     * @param string $method The HTTP method (GET, POST, PATCH, PUT, DELETE)
     * @param string $endpoint The API endpoint (e.g. /api/admin/locations)
     * @param array $params The request parameters. For GET requests these are
     *  sent as query parameters, otherwise as a JSON body.
     * @return CalagopusResponse The response from the API
     */
    public function apiRequest($method, $endpoint, array $params = [])
    {
        $method = strtoupper($method);
        $url = $this->base_url . $endpoint;

        if ($method === 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->api_key,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);

        if ($method !== 'GET' && !empty($params)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        }

        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return new CalagopusResponse(0, json_encode(['errors' => ['cURL Error: ' . $error]]));
        }

        return new CalagopusResponse($status, $raw);
    }

    /**
     * Returns the base panel URL.
     *
     * @return string The base panel URL
     */
    public function getBaseUrl()
    {
        return $this->base_url;
    }

    /**
     * Returns the panel URL to manage the given server.
     *
     * @param string $server_uuid The UUID of the server
     * @return string The panel URL
     */
    public function getServerUrl($server_uuid)
    {
        return $this->base_url . '/server/' . $server_uuid;
    }
}

/**
 * Calagopus API Response
 *
 * Wraps a raw API response and exposes helpers for inspecting it.
 */
class CalagopusResponse
{
    /**
     * @var int The HTTP status code
     */
    private $status;

    /**
     * @var string The raw response body
     */
    private $raw;

    /**
     * @var mixed The decoded response body
     */
    private $decoded;

    /**
     * Initializes the response.
     *
     * @param int $status The HTTP status code
     * @param string $raw The raw response body
     */
    public function __construct($status, $raw)
    {
        $this->status = (int)$status;
        $this->raw = (string)$raw;
        $this->decoded = json_decode($this->raw);
    }

    /**
     * Returns the HTTP status code.
     *
     * @return int The HTTP status code
     */
    public function status()
    {
        return $this->status;
    }

    /**
     * Returns the raw response body.
     *
     * @return string The raw response body
     */
    public function raw()
    {
        return $this->raw;
    }

    /**
     * Returns the decoded response body.
     *
     * @return mixed The decoded response (stdClass/array) or null
     */
    public function response()
    {
        return $this->decoded;
    }

    /**
     * Returns a list of errors, if any. A response is considered errored when
     * the HTTP status is >= 400.
     *
     * @return array A list of error messages keyed by an index
     */
    public function errors()
    {
        if ($this->status < 400 && $this->status !== 0) {
            return [];
        }

        $errors = [];
        if (is_object($this->decoded) && isset($this->decoded->errors)) {
            foreach ((array)$this->decoded->errors as $error) {
                if (is_string($error)) {
                    $errors[] = $error;
                } elseif (is_object($error)) {
                    $errors[] = $error->detail ?? ($error->message ?? json_encode($error));
                }
            }
        }

        if (empty($errors)) {
            $errors[] = 'API Error (HTTP ' . $this->status . ')';
        }

        return ['api' => $errors];
    }
}
