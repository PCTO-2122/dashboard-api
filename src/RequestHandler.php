<?php

namespace Src;

class RequestHandler {
    private DatabaseHandler $db;
    private string $requestMethod;
    private string $function;
    private array $data;

    /**
     * Initialize a new instance of the RequestHandler.
     */
    public function __construct(\mysqli $db, string $requestMethod, string $function) {
        $this->db = new DatabaseHandler($db);
        $this->requestMethod = $requestMethod;
        $this->function = $function;
        $this->data = $this->parseJsonData();
    }

    /**
     * Parse input JSON data into an associative array.
     * @return array, the parsed associative array.
     */
    private function parseJsonData(): array {
        return json_decode(file_get_contents('php://input'), true);
    }

    /**
     * Check if there is a DB error.
     * @throws Exception if there is an error.
     */
    private function checkErrorThrowException() {
        $error = $this->db->error();
        if ($error) {
            throw new \Exception($error);
        }
    }

    private function jsonKeysOK(array $keys): bool {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $this->data)) {
                throw new \InvalidArgumentException("Invalid JSON input parameters");
            }
        }
        return true;
    }

    /**
     * Handle the /api/user REST endpoint.
     * 
     * Get data about a specific user:
     *  GET /api/user
     *  {""} ---> {""}
     * 
     * Check login data of an existent user:
     *  POST /api/user
     *  {"email": string, "password": string}
     *      Result:
     *      {
     *          "success": bool,
     *          "error": undefined | string,
     *          "result": {"exists":bool} | {}
     *      }
     * 
     * Register a new user:
     *  POST /api/user
     * 
     * @return mixed any value that will encoded into JSON.
     * @throws UnsupportedMethodException current REST method not supported.
     */
    protected function user(): mixed {
        switch ($this->requestMethod) {
            case HTTP_GET:
                // Get user information
            case HTTP_POST:
                // Login
                $this->jsonKeysOK(array("email", "password"));
                $exists = $this->db->userExists(
                    $this->data["email"],
                    $this->data["password"]
                );
                $this->checkErrorThrowException();
                $result = array("exists" => $exists);
                break;
            case HTTP_PUT:
                // Register
                $this->jsonKeysOK(array("fiscalCode", "firstName", "lastName", "email", "password"));
                $this->db->registerUser(
                    $this->data["fiscalCode"],
                    $this->data["firstName"],
                    $this->data["lastName"],
                    $this->data["email"],
                    $this->data["password"]
                );
                $this->checkErrorThrowException();
                $result = null;
                break;
            default:
                throw new UnsupportedMethodException();
        }
        return $result;
    }

    protected function settings(): mixed {
    }

    protected function noncompliances(): mixed {
    }

    protected function noncompliance(): mixed {
    }

    protected function tickets(): mixed {
    }

    protected function ticket(): mixed {
    }

    /**
     * Process an HTTP request and print the JSON result.
     */
    public function processRequest(): void {
        if (
            \method_exists($this, $this->function) and
            (new \ReflectionMethod($this, $this->function))->isProtected()
        ) {
            try {
                // Variable function
                $apiFunction = $this->function;
                $result = $apiFunction();
                if ($result == null) {
                    showResult();
                } else {
                    showResult($result);
                }
            } catch (\Exception $e) {
                // BAD_REQUEST is default HTTP error
                $code = $e->getCode();
                showError($e->getMessage(), ($code != 0) ? $code : HTTP_BAD_REQUEST);
            }
        }
    }

    /**
     * Clean current database connession.
     */
    public function close(): void {
        $this->db->close();
    }
}
