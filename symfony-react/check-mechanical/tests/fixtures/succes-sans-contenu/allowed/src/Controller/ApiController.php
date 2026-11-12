<?php
return new Response(null, Response::HTTP_NO_CONTENT);
return $this->json(['ok' => false, 'status' => 'pending']);
// Jamais un 200 ['ok' => true].
