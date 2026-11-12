<?php
$page = $request->query->getInt('page', 1);
$filtres = $request->query->all();
$fichier = $request->files->get('document');
if ($request->query->has('email')) {}
