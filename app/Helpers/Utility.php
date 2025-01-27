<?php

function base_url(string $path = ''): string
{
    $base = 'http://localhost/event/public';
    return $base . '/' . ltrim($path, '/');
}