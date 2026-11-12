<?php
$this->client = new \Symfony\Component\HttpClient\RetryableHttpClient($client, null, 3);
