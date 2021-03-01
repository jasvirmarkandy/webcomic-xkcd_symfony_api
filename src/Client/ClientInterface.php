<?php

namespace Webcomic\Component\Xkcd\Api\Client;

use Psr\SimpleCache\CacheInterface;
use Webcomic\Component\Xkcd\Api\Dto\Info;

interface ClientInterface
{
    public function get(int $num): Info;
    public function getLatest(): Info;
    public function getRandom(): Info;
    public function setCache(CacheInterface $cache): void;
}
