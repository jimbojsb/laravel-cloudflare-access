<?php

namespace Jimbojsb\CloudflareAccess\Contracts;

interface ServiceUserIdentityResolver
{
    public function email(string $commonName): string;

    public function name(string $commonName): string;
}
