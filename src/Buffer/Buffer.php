<?php

namespace Minimalism\Buffer;


interface Buffer
{
    public function get($len);

    public function readableBytes();

    public function read($len);

    public function readFull();

    public function write($bytes);
}