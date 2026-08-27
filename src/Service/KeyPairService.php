<?php

declare(strict_types=1);

namespace App\Service;

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use RuntimeException;

class KeyPairService
{
    public const FILE_PRIVATE = 'private.jwk';
    public const FILE_PUBLIC = 'public.jwk';
    public const FILE_DID_DOCUMENT = 'did.json';
    public const FILE_PRIVATE_PEM = 'private.pem';

    public function __construct(
        private readonly string $secretsDir,
        private readonly string $baseUrl,
    ) {
    }

    /**
     * @return array{private: bool, public: bool, did: bool, privatePem: bool, publicKeyContent: ?string, didDocumentContent: ?string}
     */
    public function getStatus(): array
    {
        return [
            'private' => $this->exists(self::FILE_PRIVATE),
            'public' => $this->exists(self::FILE_PUBLIC),
            'did' => $this->exists(self::FILE_DID_DOCUMENT),
            'privatePem' => $this->exists(self::FILE_PRIVATE_PEM),
            'publicKeyContent' => $this->readIfExists(self::FILE_PUBLIC),
            'didDocumentContent' => $this->readIfExists(self::FILE_DID_DOCUMENT),
        ];
    }

    public function secretsDirectory(): string
    {
        return $this->secretsDir;
    }

    public function getDidDocumentContent(): ?string
    {
        return $this->readIfExists(self::FILE_DID_DOCUMENT);
    }

    public function did(): string
    {
        return $this->didWeb();
    }

    public function signAccessToken(array $claims): string
    {
        $pem = $this->readIfExists(self::FILE_PRIVATE_PEM);

        if ($pem === null) {
            throw new RuntimeException('Kunci privat PEM belum dibuat. Silakan generate key pair terlebih dahulu.');
        }

        $kid = $this->readPrivateKid();

        return JWT::encode($claims, $pem, 'ES256', $kid);
    }

    /**
     * @return array<string, mixed>
     */
    public function publicKey(): array
    {
        $content = $this->readIfExists(self::FILE_PUBLIC);

        if ($content === null) {
            throw new RuntimeException('Kunci publik JWK belum dibuat. Silakan generate key pair terlebih dahulu.');
        }

        $jwk = json_decode($content, true);

        if (!is_array($jwk)) {
            throw new RuntimeException('Kunci publik JWK tidak valid.');
        }

        return $jwk;
    }

    /**
     * Verifies an access token JWT and returns its claims.
     *
     * @return array<string, mixed>
     */
    public function verifyAccessToken(string $jwt): array
    {
        $key = JWK::parseKey($this->publicKey(), 'ES256');

        try {
            $payload = JWT::decode($jwt, $key);
        } catch (\Throwable $e) {
            throw new RuntimeException('Access token tidak valid: ' . $e->getMessage());
        }

        return (array) $payload;
    }

    /**
     * Validates an OID4VCI key proof JWT and returns the holder's public JWK
     * from the JOSE header.
     *
     * @return array<string, mixed>
     */
    public function verifyProofJwt(string $proofJwt, string $expectedAud, string $expectedNonce): array
    {
        $parts = explode('.', $proofJwt);

        if (count($parts) !== 3) {
            throw new RuntimeException('Proof JWT tidak valid.');
        }

        $header = json_decode(self::base64UrlDecode($parts[0]), true);
        $payload = json_decode(self::base64UrlDecode($parts[1]), true);

        if (!is_array($header) || !is_array($payload)) {
            throw new RuntimeException('Proof JWT tidak valid.');
        }

        if (($header['typ'] ?? null) !== 'openid4vci-proof+jwt') {
            throw new RuntimeException('Proof JWT typ header tidak valid.');
        }

        if (!isset($header['jwk']) || !is_array($header['jwk'])) {
            throw new RuntimeException('Proof JWT tidak memuat public key (jwk).');
        }

        $alg = $header['jwk']['alg'] ?? ($header['alg'] ?? null);
        $allowedAlgs = ['ES256', 'ES384', 'ES512', 'EdDSA', 'RS256', 'PS256'];

        if (!is_string($alg) || !in_array($alg, $allowedAlgs, true)) {
            throw new RuntimeException('Proof JWT algoritma tidak didukung.');
        }

        if (($payload['aud'] ?? null) !== $expectedAud) {
            throw new RuntimeException('Proof JWT aud tidak cocok.');
        }

        if (($payload['nonce'] ?? null) !== $expectedNonce) {
            throw new RuntimeException('Proof JWT nonce tidak cocok atau kedaluwarsa.');
        }

        $iat = $payload['iat'] ?? null;

        if (is_numeric($iat)) {
            $skew = 60;

            if (abs((int) $iat - time()) > $skew) {
                throw new RuntimeException('Proof JWT iat di luar toleransi.');
            }
        }

        try {
            JWT::decode($proofJwt, JWK::parseKey($header['jwk']));
        } catch (\Throwable $e) {
            throw new RuntimeException('Tanda tangan proof tidak valid: ' . $e->getMessage());
        }

        return $header['jwk'];
    }

    public function signCredential(array $claims): string
    {
        $pem = $this->readIfExists(self::FILE_PRIVATE_PEM);

        if ($pem === null) {
            throw new RuntimeException('Kunci privat PEM belum dibuat. Silakan generate key pair terlebih dahulu.');
        }

        $kid = $this->readPrivateKid();

        return JWT::encode($claims, $pem, 'ES256', $kid);
    }

    private function readPrivateKid(): string
    {
        $content = $this->readIfExists(self::FILE_PRIVATE);

        if ($content === null) {
            throw new RuntimeException('Kunci privat JWK belum dibuat. Silakan generate key pair terlebih dahulu.');
        }

        $jwk = json_decode($content, true);

        if (!is_array($jwk) || !isset($jwk['kid'])) {
            throw new RuntimeException('Kunci privat JWK tidak valid atau tidak memiliki "kid".');
        }

        return $jwk['kid'];
    }

    public function generate(): void
    {
        $key = openssl_pkey_new([
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);

        if ($key === false) {
            throw new RuntimeException('Gagal membuat kunci EC: ' . (string) openssl_error_string());
        }

        $details = openssl_pkey_get_details($key);

        if ($details === false) {
            throw new RuntimeException('Gagal membaca detail kunci EC.');
        }

        $ec = $details['ec'] ?? $details;

        if (!isset($ec['d'], $ec['x'], $ec['y'])) {
            throw new RuntimeException('Komponen kunci EC (d, x, y) tidak tersedia.');
        }

        $x = self::base64UrlEncode($ec['x']);
        $y = self::base64UrlEncode($ec['y']);
        $kid = self::base64UrlEncode(hash('sha256', json_encode([
            'crv' => 'P-256',
            'kty' => 'EC',
            'x' => $x,
            'y' => $y,
        ], JSON_THROW_ON_ERROR)));

        $publicJwk = [
            'kty' => 'EC',
            'crv' => 'P-256',
            'x' => $x,
            'y' => $y,
            'kid' => $kid,
            'use' => 'sig',
            'alg' => 'ES256',
        ];

        $privateJwk = [
            'kty' => 'EC',
            'crv' => 'P-256',
            'x' => $x,
            'y' => $y,
            'd' => self::base64UrlEncode($ec['d']),
            'kid' => $kid,
            'use' => 'sig',
            'alg' => 'ES256',
        ];

        $did = $this->didWeb();
        $keyId = $did . '#' . $kid;
        $didDocument = [
            '@context' => [
                'https://www.w3.org/ns/did/v1',
                'https://w3id.org/security/suites/jws-2020/v1',
            ],
            'id' => $did,
            'verificationMethod' => [
                [
                    'id' => $keyId,
                    'type' => 'JsonWebKey2020',
                    'controller' => $did,
                    'publicKeyJwk' => $publicJwk,
                ],
            ],
            'authentication' => [$keyId],
            'assertionMethod' => [$keyId],
        ];

        $jsonFlags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

        if (!is_dir($this->secretsDir) && !@mkdir($this->secretsDir, 0770, true) && !is_dir($this->secretsDir)) {
            throw new RuntimeException(sprintf('Tidak dapat membuat direktori "%s".', $this->secretsDir));
        }

        $this->writeFile(self::FILE_PRIVATE, json_encode($privateJwk, $jsonFlags), 0600);
        $this->writeFile(self::FILE_PUBLIC, json_encode($publicJwk, $jsonFlags), 0644);
        $this->writeFile(self::FILE_DID_DOCUMENT, json_encode($didDocument, $jsonFlags), 0644);

        if (!openssl_pkey_export($key, $pem)) {
            throw new RuntimeException('Gagal mengekspor kunci privat ke format PEM.');
        }

        $this->writeFile(self::FILE_PRIVATE_PEM, $pem, 0600);
    }

    private function didWeb(): string
    {
        $host = strtolower((string) (parse_url($this->baseUrl, PHP_URL_HOST) ?? ''));

        if ($host === '') {
            throw new RuntimeException(sprintf('Tidak dapat menentukan DID dari base URL "%s".', $this->baseUrl));
        }

        $port = parse_url($this->baseUrl, PHP_URL_PORT);

        if (is_int($port)) {
            $host .= '%3A' . $port;
        }

        return 'did:web:' . $host;
    }

    private function writeFile(string $filename, string $content, int $mode): void
    {
        $path = $this->path($filename);

        if (@file_put_contents($path, $content) === false) {
            throw new RuntimeException(sprintf('Tidak dapat menulis file "%s".', $path));
        }

        @chmod($path, $mode);
    }

    private function path(string $filename): string
    {
        return rtrim($this->secretsDir, '/') . '/' . $filename;
    }

    private function exists(string $filename): bool
    {
        return is_file($this->path($filename));
    }

    private function readIfExists(string $filename): ?string
    {
        $path = $this->path($filename);

        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $content = file_get_contents($path);

        return $content === false ? null : $content;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;

        if ($remainder !== 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        return $decoded === false ? '' : $decoded;
    }
}
