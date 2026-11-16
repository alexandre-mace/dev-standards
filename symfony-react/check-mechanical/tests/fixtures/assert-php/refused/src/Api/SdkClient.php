<?php
$client = SearchClient::create($id, $key);
\assert($client instanceof SearchClient);
