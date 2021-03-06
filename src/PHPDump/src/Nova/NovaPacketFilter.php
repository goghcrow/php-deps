<?php

namespace Minimalism\PHPDump\Nova;


class NovaPacketFilter
{
    public $servicePattern;
    public $methodPattern;

    public function __construct($servicePattern = "", $methodPattern = "")
    {
        $this->servicePattern = strtolower($servicePattern);
        $this->methodPattern = strtolower($methodPattern);
    }

    private function matchService($service)
    {
        return fnmatch($this->servicePattern, strtolower($service));
    }

    private function matchMethod($method)
    {
        return fnmatch($this->methodPattern, strtolower($method));
    }

    public static function isHeartbeat($service, $method)
    {
        return $service === "com.youzan.service.test" && ($method === "ping" || $method === "pong");
    }

    public function __invoke(NovaPDU $novaPacket)
    {
        $service = $novaPacket->service;
        $method = $novaPacket->method;

        $serviceMatched = $this->matchService($service);
        $methodMatched = $this->matchMethod($method);

        if ($this->servicePattern && $this->methodPattern) {
            return $serviceMatched && $methodMatched;
        } else if ($this->servicePattern && !$this->methodPattern) {
            return $serviceMatched;
        } else if (!$this->servicePattern && $this->methodPattern) {
            return $methodMatched;
        } else {
            return true;
        }
    }
}