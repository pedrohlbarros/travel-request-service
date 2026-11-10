<?php

declare(strict_types=1);

namespace Interfaces\Http\Identity\Resource;

use Application\Identity\Output\TokenPairOutput;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * OAuth 2.0 token response shape (RFC 6749 §5.1), so it is not wrapped in "data".
 *
 * @property TokenPairOutput $resource
 */
final class TokenPairResource extends JsonResource
{
    private const string TOKEN_TYPE = 'Bearer';

    public static $wrap;

    public function __construct(TokenPairOutput $tokenPair)
    {
        parent::__construct($tokenPair);
    }

    /**
     * @return array{access_token: string, token_type: string, expires_in: int, refresh_token: string, refresh_expires_in: int}
     */
    public function toArray(Request $request): array
    {
        return [
            'access_token' => $this->resource->accessToken,
            'token_type' => self::TOKEN_TYPE,
            'expires_in' => $this->resource->accessTokenExpiresIn,
            'refresh_token' => $this->resource->refreshToken,
            'refresh_expires_in' => $this->resource->refreshTokenExpiresIn,
        ];
    }
}
