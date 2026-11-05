<?php
const A = '/^\d+$/D';
preg_match('/^\w+$/uD', $s);
const B = '/^\d+\z/';
preg_match('/\d+$/', $s);
preg_match('#^/aides/[a-z-]+$#D', $path);
preg_match('~^\d{5}$~uD', $code);
preg_match('#^\d+$#m', $s, $m, 0, 0) ?: preg_match('~\d+$~', $s);
