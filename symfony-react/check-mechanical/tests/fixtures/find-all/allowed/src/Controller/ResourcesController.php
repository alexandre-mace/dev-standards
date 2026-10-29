<?php
$regions = $regionRepository->findBy([], ['name' => 'ASC']);
// pas de ->findAll() ici
