<?php
namespace App\Models;

class ResultDto {

    private $res;

    private $message;

    private $data;

    function __construct(int $res, string $message, array $data = []) {
        $this->res = $res;
        $this->message = $message;
        $this->data = $data;
    }

    public function getAnswer(): array {
        return [
            'res' => $this->res,
            'message' => $this->message,
            'data' => $this->data
        ];
    }
}