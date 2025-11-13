<?php

/*
 * Author: Eka Yudinata <ekayudinatha@gmail.com>
 * Created: 2025-11-11 11:52:56
 * Copyright (c) 2025, Eka Yudinata
 */

namespace App\Actions\Retina\Dropshipping\Portfolio;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Exception;


class DownloadPortfolioZipImagesToR2Service
{
    protected $disk;
    protected $publicUrl;
    protected $accountId;
    protected $apiToken;
    protected $wafSecret;

    public function __construct()
    {
        $this->disk = Storage::disk('catalogue-iris-r2');
        $this->publicUrl = config('filesystems.disks.zip-r2.url');
        $this->accountId = config('services.cloudflare-zip-r2.account_id');
        $this->apiToken = config('services.cloudflare-zip-r2.token');
        $this->wafSecret = config('services.cloudflare-zip-r2.waf_secret');
    }

    /**
     * Check if file exists in R2
     */
    public function fileExists(string $path): bool
    {
        return $this->disk->exists($path);
    }

    /**
     * Upload zip file to R2
     */
    public function uploadZip(string $sourcePath, string $destinationPath): bool
    {
        try {
            $fileContents = file_get_contents($sourcePath);

            return $this->disk->put(
                $destinationPath,
                $fileContents,
                'public'
            );
        } catch (Exception $e) {
            Log::error('R2 Upload Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Upload from local storage
     */
    public function uploadFromStorage(string $storagePath, string $destinationPath): bool
    {
        try {
            $fileContents = Storage::disk('local')->get($storagePath);

            return $this->disk->put(
                $destinationPath,
                $fileContents,
                'public'
            );
        } catch (Exception $e) {
            Log::error('R2 Upload Error: ' . $e->getMessage());
            return false;
        }
    }


    /**
     * Upload from local file
     */
    public function uploadFileFromFile(string $filePath, string $destinationPath): bool
    {
        try {
            $fileContents = file_get_contents($filePath);

            return $this->disk->put(
                $destinationPath,
                $fileContents,
                'public'
            );
        } catch (Exception $e) {
            Log::error('R2 Upload Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate Cloudflare WAF authenticated URL
     * Following Cloudflare's official token authentication format
     */
    public function generateAuthenticatedUrl(string $filePath, int $expiresInMinutes = 60): string
    {
        $baseUrl = rtrim($this->publicUrl, '/');
        $cleanPath = '/' . ltrim($filePath, '/');

        // Generate Cloudflare WAF token
        $tokenData = $this->generateCloudflareToken($cleanPath, $expiresInMinutes);

        // Construct URL with verify parameter
        return "{$baseUrl}{$cleanPath}?verify={$tokenData}";
    }

    /**
     * Generate Cloudflare WAF token using official format
     * Format: verify={timestamp}-{token}
     */
    protected function generateCloudflareToken(string $message, int $expiresInMinutes): string
    {
        $secret = $this->wafSecret;
        $timestamp = time() + ($expiresInMinutes * 60); // Expiration timestamp

        // Create HMAC-SHA256 hash
        $hash = hash_hmac('sha256', $message . $timestamp, $secret, true);

        // Encode to base64 and URL encode
        $token = urlencode(base64_encode($hash));

        // Return in Cloudflare format: timestamp-token
        return "{$timestamp}-{$token}";
    }

    /**
     * Verify Cloudflare WAF token
     */
    public function verifyCloudflareToken(string $message, string $verifyParam): bool
    {
        // Parse verify parameter: timestamp-token
        $parts = explode('-', $verifyParam, 2);

        if (count($parts) !== 2) {
            Log::warning('Invalid verify parameter format', ['verify' => $verifyParam]);
            return false;
        }

        list($timestamp, $providedToken) = $parts;

        // Check if token has expired
        if (time() > (int)$timestamp) {
            Log::info('Token expired', [
                'timestamp' => $timestamp,
                'current_time' => time()
            ]);
            return false;
        }

        // Regenerate token with the same timestamp
        $secret = $this->wafSecret;
        $hash = hash_hmac('sha256', $message . $timestamp, $secret, true);
        $expectedToken = urlencode(base64_encode($hash));

        // Compare tokens
        $isValid = hash_equals($expectedToken, $providedToken);

        if (!$isValid) {
            Log::warning('Token verification failed', [
                'message' => $message,
                'provided_token' => substr($providedToken, 0, 20) . '...',
                'expected_token' => substr($expectedToken, 0, 20) . '...'
            ]);
        }

        return $isValid;
    }

    /**
     * Get public URL without authentication (for internal use)
     */
    public function getPublicUrl(string $filePath): string
    {
        $baseUrl = rtrim($this->publicUrl, '/');
        $cleanPath = ltrim($filePath, '/');

        return "{$baseUrl}/{$cleanPath}";
    }

    /**
     * Delete file from R2
     */
    public function deleteFile(string $filePath): bool
    {
        try {
            return $this->disk->delete($filePath);
        } catch (Exception $e) {
            Log::error('R2 Delete Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get file size
     */
    public function getFileSize(string $filePath): int
    {
        try {
            return $this->disk->size($filePath);
        } catch (Exception $e) {
            Log::error('R2 Get Size Error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get file metadata
     */
    public function getFileMetadata(string $filePath): ?array
    {
        try {
            if (!$this->fileExists($filePath)) {
                return null;
            }

            return [
                'path' => $filePath,
                'size' => $this->disk->size($filePath),
                'last_modified' => $this->disk->lastModified($filePath),
                'mime_type' => $this->disk->mimeType($filePath),
            ];
        } catch (Exception $e) {
            Log::error('R2 Get Metadata Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate multiple authenticated URLs at once
     */
    public function generateBulkAuthenticatedUrls(array $filePaths, int $expiresInMinutes = 60): array
    {
        $urls = [];

        foreach ($filePaths as $filePath) {
            $urls[$filePath] = $this->generateAuthenticatedUrl($filePath, $expiresInMinutes);
        }

        return $urls;
    }

    /**
     * List files in directory
     */
    public function listFiles(string $directory = '', bool $recursive = false): array
    {
        try {
            $method = $recursive ? 'allFiles' : 'files';
            return $this->disk->$method($directory);
        } catch (Exception $e) {
            Log::error('R2 List Files Error: ' . $e->getMessage());
            return [];
        }
    }
}
