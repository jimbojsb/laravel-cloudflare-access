<?php

namespace Jimbojsb\CloudflareAccess;

use Carbon\Carbon;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CloudflareAccessJWT
{
    public ?Carbon $notBefore = null;

    public ?Carbon $expiresAt = null;

    public ?Carbon $issuedAt = null;

    public ?string $email = null;

    public ?string $name = null;

    public ?array $audience = null;

    public ?string $issuer = null;

    public array $groups = [];

    protected string $subdomain;

    protected string $expectedAudience;

    protected int $cacheMinutes;

    public function __construct(string $subdomain, string $expectedAudience, int $cacheMinutes = 60)
    {
        $this->subdomain = $subdomain;
        $this->expectedAudience = $expectedAudience;
        $this->cacheMinutes = $cacheMinutes;
    }

    public function decode(string $headerString): self
    {
        $jwkData = $this->getJwkData();

        $jwk = JWK::parseKeySet($jwkData);
        $decodedJwt = JWT::decode($headerString, $jwk);

        $this->notBefore = isset($decodedJwt->nbf) ? Carbon::createFromTimestamp($decodedJwt->nbf) : null;
        $this->issuedAt = isset($decodedJwt->iat) ? Carbon::createFromTimestamp($decodedJwt->iat) : null;
        $this->expiresAt = isset($decodedJwt->exp) ? Carbon::createFromTimestamp($decodedJwt->exp) : null;
        $this->email = $decodedJwt->email ?? null;
        $this->name = $decodedJwt->custom->name ?? null;
        $this->groups = $decodedJwt->custom->groups ?? [];
        $this->audience = $decodedJwt->aud ?? null;
        $this->issuer = $decodedJwt->iss ?? null;

        return $this;
    }

    public function isValid(): bool
    {
        $now = Carbon::now();

        if (! $this->hasRequiredFields()) {
            return false;
        }

        if (! $this->hasValidTimestamps($now)) {
            return false;
        }

        if (! $this->hasValidAudience()) {
            return false;
        }

        return true;
    }

    protected function hasRequiredFields(): bool
    {
        return isset($this->audience)
            && isset($this->issuedAt)
            && isset($this->notBefore)
            && isset($this->email)
            && isset($this->expiresAt)
            && isset($this->name);
    }

    protected function hasValidTimestamps(Carbon $now): bool
    {
        return $now->greaterThanOrEqualTo($this->notBefore)
            && $now->greaterThanOrEqualTo($this->issuedAt)
            && $now->lessThan($this->expiresAt);
    }

    protected function hasValidAudience(): bool
    {
        if (! is_array($this->audience)) {
            return false;
        }

        foreach ($this->audience as $audience) {
            if ($audience === $this->expectedAudience) {
                return true;
            }
        }

        return false;
    }

    protected function getJwkData(): array
    {
        $cacheKey = 'cloudflare_access_jwk_'.$this->subdomain;

        return Cache::remember($cacheKey, $this->cacheMinutes * 60, function () {
            $jwkUrl = sprintf(
                'https://%s.cloudflareaccess.com/cdn-cgi/access/certs',
                $this->subdomain
            );

            $response = Http::get($jwkUrl);

            if ($response->successful()) {
                return $response->json();
            }

            throw new \RuntimeException('Failed to fetch Cloudflare Access JWK keys');
        });
    }

    public function getSubdomain(): string
    {
        return $this->subdomain;
    }

    public function getExpectedAudience(): string
    {
        return $this->expectedAudience;
    }
}
