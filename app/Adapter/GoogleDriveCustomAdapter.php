<?php

namespace App\Adapter;

use League\Flysystem\PathPrefixer;
use League\Flysystem\UnableToReadFile;
use Masbug\Flysystem\GoogleDriveAdapter;
use Psr\Http\Message\RequestInterface;

class GoogleDriveCustomAdapter extends GoogleDriveAdapter
{
    public function read(string $location): string
    {
        $this->refreshToken();
        $path = (new PathPrefixer(''))->prefixPath($location);
        if (static::$defaultOptions['useDisplayPaths']) {
            $fileId = $this->toVirtualPath($path, false, true);
        } else {
            [, $fileId] = $this->splitPath($path);
        }
        try {
            /** @var RequestInterface $response */
            if (($response = $this->service->files->get(/** @scrutinizer ignore-type */ $fileId, $this->applyDefaultParams(['alt' => 'media'], 'files.get')))) {
                return (string) $response->getBody();
            }
        } catch (\Exception $e) {
            /** @var RequestInterface $response */
            if (($response = $this->service->files->export(/** @scrutinizer ignore-type */ $fileId, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $this->applyDefaultParams(['alt' => 'media'], 'files.get')))) {
                return (string) $response->getBody();
            }
        }
        throw UnableToReadFile::fromLocation($path, 'Unable To Read File');
    }
}
