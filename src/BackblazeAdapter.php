<?php

namespace ElGovanni\Flysystem;

use BackblazeB2\Client;
use BackblazeB2\File;
use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\Utils;
use InvalidArgumentException;
use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Adapter\Polyfill\NotSupportingVisibilityTrait;
use League\Flysystem\Config;

class BackblazeAdapter extends AbstractAdapter
{
    use NotSupportingVisibilityTrait;

    protected Client $client;

    protected string $bucketName;

    protected ?string $bucketId;

    public function __construct(Client $client, $bucketName, $bucketId = null)
    {
        $this->client = $client;
        $this->bucketName = $bucketName;
        $this->bucketId = $bucketId;
    }

    /**
     * {@inheritdoc}
     */
    public function has($path): bool|array|null
    {
        return $this->getClient()->fileExists(['FileName' => $path, 'BucketId' => $this->bucketId, 'BucketName' => $this->bucketName]);
    }

    /**
     * {@inheritdoc}
     */
    public function write($path, $contents, Config $config): bool|array
    {
        $file = $this->getClient()->upload([
            'BucketId'   => $this->bucketId,
            'BucketName' => $this->bucketName,
            'FileName'   => $path,
            'Body'       => $contents,
        ]);

        return $this->getFileInfo($file);
    }

    /**
     * {@inheritdoc}
     */
    public function writeStream($path, $resource, Config $config): bool|array
    {
        $file = $this->getClient()->upload([
            'BucketId'   => $this->bucketId,
            'BucketName' => $this->bucketName,
            'FileName'   => $path,
            'Body'       => $resource,
        ]);

        return $this->getFileInfo($file);
    }

    /**
     * {@inheritdoc}
     */
    public function update($path, $contents, Config $config): bool|array
    {
        $file = $this->getClient()->upload([
            'BucketId'   => $this->bucketId,
            'BucketName' => $this->bucketName,
            'FileName'   => $path,
            'Body'       => $contents,
        ]);

        return $this->getFileInfo($file);
    }

    /**
     * {@inheritdoc}
     */
    public function updateStream($path, $resource, Config $config): bool|array
    {
        $file = $this->getClient()->upload([
            'BucketId'   => $this->bucketId,
            'BucketName' => $this->bucketName,
            'FileName'   => $path,
            'Body'       => $resource,
        ]);

        return $this->getFileInfo($file);
    }

    /**
     * {@inheritdoc}
     */
    public function read($path): bool|array
    {
        $file = $this->getClient()->getFile([
            'BucketId'   => $this->bucketId,
            'BucketName' => $this->bucketName,
            'FileName'   => $path,
        ]);
        $fileContent = $this->getClient()->download([
            'FileId' => $file->getId(),
        ]);

        return ['contents' => $fileContent];
    }

    /**
     * {@inheritdoc}
     */
    public function readStream($path): bool|array
    {
        $stream = Utils::streamFor();
        $download = $this->getClient()->download([
            'BucketId'   => $this->bucketId,
            'BucketName' => $this->bucketName,
            'FileName'   => $path,
            'SaveAs'     => $stream,
        ]);
        $stream->seek(0);

        try {
            $resource = Psr7\StreamWrapper::getResource($stream);
        } catch (InvalidArgumentException $e) {
            return false;
        }

        return $download === true ? ['stream' => $resource] : false;
    }

    /**
     * {@inheritdoc}
     */
    public function rename($path, $newpath): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function copy($path, $newPath): bool|File
    {
        return $this->getClient()->upload([
            'BucketId'   => $this->bucketId,
            'BucketName' => $this->bucketName,
            'FileName'   => $newPath,
            'Body'       => @file_get_contents($path),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($path): bool
    {
        return $this->getClient()->deleteFile(['FileName' => $path, 'BucketId' => $this->bucketId, 'BucketName' => $this->bucketName]);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteDir($path): bool
    {
        return $this->getClient()->deleteFile(['FileName' => $path, 'BucketId' => $this->bucketId, 'BucketName' => $this->bucketName]);
    }

    /**
     * {@inheritdoc}
     */
    public function createDir($path, Config $config): bool|File|array
    {
        return $this->getClient()->upload([
            'BucketId'   => $this->bucketId,
            'BucketName' => $this->bucketName,
            'FileName'   => $path,
            'Body'       => '',
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata($path): bool|array
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getMimetype($path): bool|array
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getSize($path): bool|array
    {
        $file = $this->getClient()->getFile(['FileName' => $path, 'BucketId' => $this->bucketId, 'BucketName' => $this->bucketName]);

        return $this->getFileInfo($file);
    }

    /**
     * {@inheritdoc}
     */
    public function getTimestamp($path): bool|array
    {
        $file = $this->getClient()->getFile(['FileName' => $path, 'BucketId' => $this->bucketId, 'BucketName' => $this->bucketName]);

        return $this->getFileInfo($file);
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * {@inheritdoc}
     */
    public function listContents($directory = '', $recursive = false): array
    {
        $fileObjects = $this->getClient()->listFiles([
            'BucketId'   => $this->bucketId,
            'BucketName' => $this->bucketName,
        ]);
        if ($recursive === true && $directory === '') {
            $regex = '/^.*$/';
        } elseif ($recursive === true && $directory !== '') {
            $regex = '/^'.preg_quote($directory).'\/.*$/';
        } elseif ($recursive === false && $directory === '') {
            $regex = '/^(?!.*\\/).*$/';
        } elseif ($recursive === false && $directory !== '') {
            $regex = '/^'.preg_quote($directory).'\/(?!.*\\/).*$/';
        } else {
            throw new InvalidArgumentException();
        }
        $fileObjects = array_filter($fileObjects, function ($fileObject) use ($directory, $regex) {
            return 1 === preg_match($regex, $fileObject->getName());
        });
        $normalized = array_map(function ($fileObject) {
            return $this->getFileInfo($fileObject);
        }, $fileObjects);

        return array_values($normalized);
    }

    /**
     * Get file info.
     *
     * @param $file
     *
     * @return array
     */
    protected function getFileInfo($file): array
    {
        $normalized = [
            'type'      => 'file',
            'path'      => $file->getName(),
            'timestamp' => substr($file->getUploadTimestamp(), 0, -3),
            'size'      => $file->getSize(),
        ];

        return $normalized;
    }
}
