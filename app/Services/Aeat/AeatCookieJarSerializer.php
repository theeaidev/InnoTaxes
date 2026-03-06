<?php

namespace App\Services\Aeat;

use GuzzleHttp\Cookie\CookieJar;

class AeatCookieJarSerializer
{
    /**
     * Serialize a cookie jar to a plain array.
     *
     * @return array<int, array<string, mixed>>
     */
    public function serialize(CookieJar $cookieJar): array
    {
        return $cookieJar->toArray();
    }

    /**
     * Restore a cookie jar from serialized data.
     *
     * @param  array<int, array<string, mixed>>  $cookies
     */
    public function deserialize(array $cookies): CookieJar
    {
        return new CookieJar(false, $cookies);
    }
}
