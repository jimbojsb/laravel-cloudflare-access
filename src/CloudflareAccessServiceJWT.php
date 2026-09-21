<?php

namespace Jimbojsb\CloudflareAccess;

class CloudflareAccessServiceJWT extends CloudflareAccessJWT
{
    public ?string $commonName = null;

    public function decode(string $headerString): self
    {
        $decodedJwt = $this->decodeToken($headerString);

        $this->populateCommonFields($decodedJwt);

        $this->commonName = $decodedJwt->common_name ?? null;

        return $this;
    }

    protected function hasRequiredFields(): bool
    {
        return isset($this->audience)
            && isset($this->issuedAt)
            && isset($this->notBefore)
            && isset($this->expiresAt)
            && isset($this->commonName);
    }
}
