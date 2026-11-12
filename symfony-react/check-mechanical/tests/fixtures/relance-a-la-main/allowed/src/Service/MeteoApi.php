<?php
// Pas de new RetryableHttpClient ici : retry_failed sur le client nommé.
final class MeteoApi
{
    public function __construct(private HttpClientInterface $meteoClient)
    {
    }
}
