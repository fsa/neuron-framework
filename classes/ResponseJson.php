<?php

namespace FSA\Neuron;

class ResponseJson extends Response
{

    public function json($response, $options = JSON_UNESCAPED_UNICODE)
    {
        header('Content-Type: application/json;charset=UTF-8');
        echo json_encode($response, $options);
        exit;
    }

    public function jsonString(?string $response)
    {
        if (is_null($response)) {
            $this->returnError(404);
        }
        header('Content-Type: application/json;charset=UTF-8');
        echo $response;
        exit;
    }

    public function jsonError($http_response_code, $response, $options = JSON_UNESCAPED_UNICODE)
    {
        http_response_code($http_response_code);
        header('Content-Type: application/json;charset=UTF-8');
        header('Cache-Control: no-store');
        header('Pragma: no-cache');
        if (is_string($response)) {
            echo $response;
        } else {
            echo json_encode($response, $options);
        }
        exit;
    }

    public function returnError(int $http_response_code, $message = null)
    {
        http_response_code($http_response_code);
        if (empty($message)) {
            $message = parent::HTTP_STATUS_CODES[$http_response_code] ?? 'Unknown';
        }
        $this->jsonError($http_response_code, ['error' => $message, 'code'=>$http_response_code]);
        exit;
    }
 
}
