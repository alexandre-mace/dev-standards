<?php
final class MeteoApi
{
    public function __construct(HttpClientInterface $client)
    {
        $this->client = new RetryableHttpClient($client);
    }
}
