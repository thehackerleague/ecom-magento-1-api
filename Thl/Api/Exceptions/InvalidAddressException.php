<?php

class Thl_Api_Exceptions_InvalidAddressException extends Exception
{
    /**
     * @var string
     */
    public $messages;

    public function __construct(
        $messages
    ) {
        $this->messages = $message;

        parent::__construct('Address Error', 1);
    }
}