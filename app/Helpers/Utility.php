<?php

function base_url(string $path = ''): string
{
    $base = 'event-management-six-beige.vercel.app';
    return $base . '/' . ltrim($path, '/');
}
