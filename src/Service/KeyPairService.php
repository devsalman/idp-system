<?php

declare(strict_types=1);

namespace App\Service;

use RuntimeException;

class KeyPairService
{
    public const FILE_PRIVATE = 'private.jwk';
    public const FILE_PUBLIC = 'public.jwk';
    public const FILE_DID_DOCUMENT = 'did.json';

    public function __construct(
        private readonly string $secretsDir,
        private readonly string $baseUrl,
    ) {
    }

    /**
     * @return array{private: bool, public: bool, did: bool, publicKeyContent: ?string, didDocumentContent: ?string}
     */
    public function getStatus(): array
    {
        return [
            'private' => $this->exists(self::FILE_PRIVATE),
            'public' => $this->exists(self::FILE_PUBLIC),
            'did' => $this->exists(self::FILE_DID_DOCUMENT),
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
}
