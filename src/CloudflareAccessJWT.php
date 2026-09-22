<?php

namespace Jimbojsb\CloudflareAccess;

use Carbon\Carbon;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

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

    protected bool $trustUnverifiedJwt;

    public function __construct(string $subdomain, string $expectedAudience, int $cacheMinutes = 60, bool $trustUnverifiedJwt = false)
    {
        $this->subdomain = $subdomain;
        $this->expectedAudience = $expectedAudience;
        $this->cacheMinutes = $cacheMinutes;
        $this->trustUnverifiedJwt = $trustUnverifiedJwt;
    }

    public function decode(string $headerString): self
    {
        $decodedJwt = $this->decodeToken($headerString);

        $this->populateCommonFields($decodedJwt);

        $this->email = $decodedJwt->email ?? null;
        $this->name = $decodedJwt->custom->name ?? null;
        $this->groups = $decodedJwt->custom->groups ?? [];

        return $this;
    }

    protected function decodeToken(string $headerString): object
    {
        return $this->trustsUnverifiedTokens()
            ? $this->decodeWithoutVerification($headerString)
            : $this->decodeAndVerify($headerString);
    }

    protected function populateCommonFields(object $decodedJwt): void
    {
        $this->notBefore = isset($decodedJwt->nbf) ? Carbon::createFromTimestamp($decodedJwt->nbf) : null;
        $this->issuedAt = isset($decodedJwt->iat) ? Carbon::createFromTimestamp($decodedJwt->iat) : null;
        $this->expiresAt = isset($decodedJwt->exp) ? Carbon::createFromTimestamp($decodedJwt->exp) : null;
        $this->audience = $decodedJwt->aud ?? null;
        $this->issuer = $decodedJwt->iss ?? null;
    }

    /**
     * Whether this instance is configured to trust JWT claims without verifying
     * the signature. Only ever true outside of production, regardless of
     * configuration, since there is no legitimate reason to skip verification
     * once real Cloudflare Access traffic is involved.
     */
    public function trustsUnverifiedTokens(): bool
    {
        return $this->trustUnverifiedJwt && config('app.env') !== 'production';
    }

    protected function decodeAndVerify(string $headerString): object
    {
        $jwkData = $this->getJwkData();

        $jwk = JWK::parseKeySet($jwkData);

        return JWT::decode($headerString, $jwk);
    }

    /**
     * Decode a JWT's payload without verifying its signature. Only used in
     * local development (see trustsUnverifiedTokens()), where there is no
     * Cloudflare Access edge available to have signed the token in the first
     * place.
     */
    protected function decodeWithoutVerification(string $headerString): object
    {
        $parts = explode('.', $headerString);

        if (count($parts) !== 3) {
            throw new UnexpectedValueException('Malformed JWT.');
        }

        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')));

        if (! is_object($payload)) {
            throw new UnexpectedValueException('Malformed JWT payload.');
        }

        return $payload;
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
