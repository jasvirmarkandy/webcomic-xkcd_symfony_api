<?php

namespace Webcomic\Component\Xkcd\Api\Client;

use function Sauls\Component\Helper\define_object;
use function Sauls\Component\Helper\string_camelize;
use GuzzleHttp\Client as GuzzleHttpClient;
use GuzzleHttp\ClientInterface as GuzzleHttpClientInterface;
use GuzzleHttp\Exception\ClientException;
use Psr\SimpleCache\CacheInterface;
use Sauls\Component\Collection\ImmutableArrayCollection;
use Sauls\Component\OptionsResolver\OptionsResolver;
use Webcomic\Component\Xkcd\Api\Dto\Info;
use Webcomic\Component\Xkcd\Api\Exception\ComicNotFoundException;
use WebcomicComponent\Xkcd\Api\Exception\ServiceDownException;
use Webcomic\Component\Xkcd\Api\Exception\XkcdClientException;

class Client implements ClientInterface
{
    private $guzzleHttpClient;
    private $cache = null;
    private $options;

    public function __construct(array $clientOptions = [], GuzzleHttpClientInterface $guzzleHttpClient = null)
    {
        $this->initializeClient($clientOptions);
        $this->guzzleHttpClient = $this->createGuzzleHttpClient($guzzleHttpClient);
    }

    private function initializeClient($clientOptions): void
    {
        $resolver = new OptionsResolver();
        $this->configureDefaults($resolver);
        $this->options = new ImmutableArrayCollection($resolver->resolve($clientOptions));
    }

    private function configureDefaults(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefined([
                'client.url.latest',
                'client.url.comic',
                'client.cache.prefix',
                'client.cache.ttl',
                'http_client.base_uri',
            ])
            ->addAllowedTypes('client.url.latest', ['string'])
            ->addAllowedTypes('client.url.comic', ['string'])
            ->addAllowedTypes('client.cache.prefix', ['string'])
            ->addAllowedTypes('client.cache.ttl', ['int'])
            ->addAllowedTypes('http_client.base_uri', ['string'])
            ->setDefaults(
                [
                    'client' => [
                        'url' => [
                            'latest' => '/info.0.json',
                            'comic' => '/{num}/info.0.json',
                        ],
                        'cache' => [
                            'prefix' => '__xkcd__',
                            'ttl' => 720,
                        ],
                    ],
                    'http_client' => [
                        'base_uri' => 'http://xkcd.com',
                    ],
                ]
            );
    }

    private function createGuzzleHttpClient(
        GuzzleHttpClientInterface $guzzleHttpClient = null
    ): GuzzleHttpClientInterface {
        if (null === $guzzleHttpClient) {
            return new GuzzleHttpClient($this->options->get('http_client'));
        }

        return $guzzleHttpClient;
    }

    public function getRandom(): Info
    {
        $info = $this->getLatest();

        $randomComicNum = \random_int(1, $info->getNum());

        return $this->get($randomComicNum);

    }

    public function getLatest(): Info
    {
        $latestComicUrl = $this->options->get('client.url.latest');

        if (null !== $this->cache) {
            return $this->resolveCachedInfo('latest', $latestComicUrl);
        }

        return $this->request($latestComicUrl);
    }

    private function resolveCachedInfo(string $name, string $url): Info
    {
        $cacheKey = $this->createCacheKey($name);
        if (!$info = $this->cache->get($cacheKey)) {
            $info = $this->request($url);
            $this->cache->set($cacheKey, $info, $this->options->get('client.cache.ttl'));
        }

        return $info;
    }

    private function createCacheKey(string $name)
    {
        return sprintf('%s%s', $this->options->get('client.cache.prefix'), $name);
    }

    /**
     * @throws \Webcomic\Component\Xkcd\Api\Exception\XkcdClientException
     * @throws \Webcomic\Component\Xkcd\Api\Exception\ComicNotFoundException
     * @throws \Webcomic\Component\Xkcd\Api\Exception\ServiceDownException
     */
    private function request(string $url): Info
    {
        try {
            $response = $this->guzzleHttpClient->get($url);

            return $this->createInfo(json_decode($response->getBody()->getContents(), true));
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                throw new ComicNotFoundException('Comic not found.', 404, $e);
            }
            throw new ServiceDownException('Xkcd service is down.', 500, $e);
        } catch (\Exception $e) {
            throw new XkcdClientException('Error occured.', 0, $e);
        }
    }

    private function createInfo(array $data): Info
    {
        return define_object(new Info, $this->formatData($data));
    }

    private function formatData($data): array
    {
        return \array_combine(
            \array_map(function ($value) {
                return \lcfirst(string_camelize($value));
            }, \array_keys($data)),
            $data
        );
    }

    public function get(int $num): Info
    {
        $concreteComicUrl = $this->createComicUrl($num);
        if (null !== $this->cache) {
            return $this->resolveCachedInfo($num, $concreteComicUrl);
        }

        return $this->request($concreteComicUrl);
    }

    private function createComicUrl(int $num): string
    {
        return strtr($this->options->get('client.url.comic'), [
            '{num}' => $num,
        ]);
    }

    public function setCache(CacheInterface $cache): void
    {
        $this->cache = $cache;
    }
}
