<?php

namespace Webcomic\Component\Xkcd\Api\Client;

use function Sauls\Component\Helper\array_merge;
use function Sauls\Component\Helper\rrmdir;
use GuzzleHttp\ClientInterface as GuzzleHttpClientInterface;
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Psr7\Response;
use Webcomic\Component\Xkcd\Api\Dto\Info;
use Symfony\Component\Cache\Simple\FilesystemCache;

class ClientTestCase extends TestCase
{
    public static function tearDownAfterClass()
    {
        $path = __DIR__.'/tmp';
        if (file_exists($path)) {
            rrmdir($path);
        }
    }

    protected function createXkcdResponse(int $status = 200, array $data = []): Response
    {
        $data = array_merge(\json_decode(\file_get_contents(__DIR__.'/../Stubs/xkcd.response.json'), true), $data);

        return new Response($status, [], \json_encode($data));
    }

    protected function assertInfo(Info $info): void
    {
        $this->assertNotNull($info->getYear());
        $this->assertNotNull($info->getNum());
        $this->assertNotNull($info->getTitle());
        $this->assertNotNull($info->getAlt());
        $this->assertNotNull($info->getDay());
        $this->assertNotNull($info->getImg());
        $this->assertNotNull($info->getLink());
        $this->assertNotNull($info->getMonth());
        $this->assertNotNull($info->getNews());
        $this->assertNotNull($info->getSafeTitle());
        $this->assertNotNull($info->getTranscript());
    }

    protected function createClientWithCache(
        array $clientOptions = [],
        GuzzleHttpClientInterface $guzzleHttpClient = null
    ): Client {
        $client = $this->createClient($clientOptions, $guzzleHttpClient);

        $cache = new FilesystemCache('', 0, __DIR__.'/tmp');

        $client->setCache($cache);

        return $client;
    }

    protected function createClient(
        array $clientOptions = [],
        GuzzleHttpClientInterface $guzzleHttpClient = null
    ): Client {

        return new Client($clientOptions, $guzzleHttpClient);
    }

}
