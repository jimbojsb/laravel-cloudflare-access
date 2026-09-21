<?php

namespace Jimbojsb\CloudflareAccess;

use Jimbojsb\CloudflareAccess\Contracts\ServiceUserIdentityResolver;

class DefaultServiceUserIdentityResolver implements ServiceUserIdentityResolver
{
    public function email(string $commonName): string
    {
        return sprintf('%s@%s.cloudflareaccess.com', $commonName, config('cloudflare-access.subdomain'));
    }

    public function name(string $commonName): string
    {
        return $commonName;
    }
}
