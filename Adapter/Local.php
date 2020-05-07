<?php

namespace Xeviant\AsyncFlysystem\Adapter;

use League\Flysystem\Config;
use League\Flysystem\NotSupportedException;
use League\Flysystem\UnreadableFileException;
use League\Flysystem\Util;
use LogicException;
use React\EventLoop\LoopInterface;
use React\Filesystem\Node\DirectoryInterface;
use React\Filesystem\Node\FileInterface;
use React\Filesystem\Node\LinkInterface;
use React\Filesystem\Node\NodeInterface;
use React\Promise;
use React\Promise\PromiseInterface;
use Xeviant\ReactFilesystem\Adapter\DecoratedAsyncAdapter;
use Xeviant\ReactFilesystem\DecoratedAsyncFilesystem;
use Xeviant\ReactFilesystem\Node\ExtendedFileInterface;
use Xeviant\ReactFilesystem\Wrapper;

class Local extends AbstractAdapter
{
    /**
     * @var int
     */
    const SKIP_LINKS = 0001;

    /**
     * @var int
     */
    const DISALLOW_LINKS = 0002;

    /**
     * @var array
     */
    protected static array $permissions = [
        'file' => [
            'public' => 0644,
            'private' => 0600,
        ],
        'dir' => [
            'public' => 0755,
            'private' => 0700,
        ],
    ];

    /**
     * @var string
     */
    protected $pathSeparator = DIRECTORY_SEPARATOR;

    protected array $permissionMap;

    protected int $writeFlags;

    private int $linkHandling;

    private LoopInterface $loop;
    
    private Wrapper $filesystem;

    private PromiseInterface $readyPromise;

    /**
     * Constructor.
     *
     * @param LoopInterface $loop
     * @param string $root
     * @param int    $writeFlags
     * @param int    $linkHandling
     * @param array  $permissions
     *
     * @throws LogicException Rejects
     */
    public function __construct(LoopInterface $loop, $root, $writeFlags = LOCK_EX, $linkHandling = self::DISALLOW_LINKS, array $permissions = [])
    {
        $this->loop = $loop;
        $root = is_link($root) ? realpath($root) : $root;

        $this->filesystem = new Wrapper(
            DecoratedAsyncFilesystem::createFromExtendedAdapter(new DecoratedAsyncAdapter($this->loop))
        );

        $this->setPathPrefix($root);
        $this->writeFlags = $writeFlags;
        $this->linkHandling = $linkHandling;

        $this->permissionMap = array_replace_recursive(static::$permissions, $permissions);

        $this->readyPromise = $this->filesystem->ensureDirectoryExists($root)->then(function ($e) use ($linkHandling, $writeFlags, $root) {
            return $this->filesystem->isReadable($root)->then(function ($e) use ($root) {
                if (! $e) {
                    throw new LogicException('The root path ' . $root . ' is not readable.');
                }

                return $this;
            });
        });

        return $this->readyPromise;
    }

    /**
     * Ensure the root directory exists.
     *
     * @param string $root root directory path
     *
     * @return PromiseInterface<bool>
     *
     */
    protected function ensureDirectory($root)
    {
        return $this->filesystem->ensureDirectoryExists($root, $this->permissionMap['dir']['public'], true);
    }

    /**
     * @inheritdoc
     */
    public function has($path)
    {
        return $this->readyPromise->then(function () use ($path) {
            $location = $this->applyPathPrefix($path);
            return $this->filesystem->getAsyncFilesystem()->file($location)->exists();
        })->then(function () {
            return true;
        }, function () {
            return false;
        });
    }

    /**
     * @inheritdoc
     */
    public function write($path, $contents, Config $config)
    {
        $mode = (FILE_APPEND & $this->writeFlags) === FILE_APPEND ? 'cw' : 'cwt';
        $location = null;
        $file = null;

        return $this->readyPromise->then(function () use ($path, &$location, &$file) {
            $location = $this->applyPathPrefix($path);
            $file = $this->filesystem->getAsyncFilesystem()->file($location);
            return $this->ensureDirectory(dirname($location));
        })->then(function () use (&$file, $mode) {
            return $file->open($mode);
        })->then(function ($stream) use ($contents) {
            $stream->write($contents);
            return $stream->close();
        })->then(function () use (&$file) {
            return $file->size();
        })->then(function ($size) use ($path, $contents, $config) {
            $type = 'file';
            $result = compact('contents', 'type', 'size', 'path');

            if ($visibility = $config->get('visibility')) {
                $result['visibility'] = $visibility;
                return $this->setVisibility($path, $visibility)->then(function () use ($result) {
                    return $result;
                });
            }

            return $result;
        });
    }

    /**
     * @inheritdoc
     */
    public function writeStream($path, $resource, Config $config)
    {
        // TODO: not possible to simply read from a strem with react/filesystem by now
        $location = null;

        return $this->readyPromise->then(function () use ($path, &$location) {
            $location = $this->applyPathPrefix($path);
            return $this->ensureDirectory(dirname($location));
        })->then(function () use ($path, $resource, $config, &$location) {
            $stream = fopen($location, 'w+b');

            if ( ! $stream || stream_copy_to_stream($resource, $stream) === false || ! fclose($stream)) {
                return false;
            }

            $type = 'file';
            $result = compact('type', 'path');

            if ($visibility = $config->get('visibility')) {
                $result['visibility'] = $visibility;
                return $this->setVisibility($path, $visibility)->then(function () use ($result) {
                    return $result;
                });
            }

            return $result;
        })->otherwise(fn() => false);
    }

    /**
     * @inheritdoc
     */
    public function readStream($path)
    {
        // Not sure how to do this, doesn't seem possible to return a resource async
        return $this->readyPromise->then(function () use ($path) {
            $location = $this->applyPathPrefix($path);
            return $this->filesystem->getAsyncFilesystem()->file($location)->getContents();
        })->then(function ($contents) {
            // Note 'w' flag doesn't matter
            $handle = fopen('php://memory', 'rwb');
            fwrite($handle, $contents);
            fseek($handle, 0);
            return $handle;
        })->then(function ($handle) use ($path) {
            return ['type' => 'file', 'path' => $path, 'stream' => $handle];
        });
    }

    /**
     * @inheritdoc
     */
    public function updateStream($path, $resource, Config $config)
    {
        return $this->writeStream($path, $resource, $config);
    }

    /**
     * @inheritdoc
     */
    public function update($path, $contents, Config $config)
    {
        $mode = (FILE_APPEND & $this->writeFlags) === FILE_APPEND ? 'cw' : 'cwt';
        $file = null;

        return $this->readyPromise->then(function () use ($path, $mode, &$file) {
            $location = $this->applyPathPrefix($path);
            $file = $this->filesystem->getAsyncFilesystem()->file($location);
            return $file->open($mode);
        })->then(function ($stream) use ($contents) {
            $stream->end($contents);
            return true;
        })->then(function () use (&$file) {
            return $file->size();
        })->then(function ($size) use ($path, $contents) {
            $type = 'file';

            $result = compact('type', 'path', 'size', 'contents');

            if ($mimetype = Util::guessMimeType($path, $contents)) {
                $result['mimetype'] = $mimetype;
            }

            return $result;
        }, function () {
            return false;
        });
    }

    /**
     * @inheritdoc
     */
    public function read($path)
    {
        return $this->readyPromise->then(function () use ($path) {
            $location = $this->applyPathPrefix($path);
            return $this->filesystem->getAsyncFilesystem()->file($location)->getContents();
        })->then(function ($contents) use ($path) {
            return ['type' => 'file', 'path' => $path, 'contents' => $contents];
        }, function () {
            return false;
        });
    }

    /**
     * @inheritdoc
     */
    public function rename($path, $newpath)
    {
        $location = null;
        $destination = null;

        return $this->readyPromise->then(function () use ($path, $newpath, &$location, &$destination) {
            $location = $this->applyPathPrefix($path);
            $destination = $this->applyPathPrefix($newpath);
            $parentDirectory = $this->applyPathPrefix(Util::dirname($newpath));
            return $this->ensureDirectory($parentDirectory);
        })->then(function () use (&$location, &$destination) {
            return $this->filesystem->getAsyncFilesystem()->file($location)->rename($destination);
        })->then(function () {
            return true;
        }, function () {
            return false;
        });
    }

    /**
     * @inheritdoc
     */
    public function copy($path, $newpath)
    {
        $location = null;
        $destination = null;

        return $this->readyPromise->then(function () use ($path, $newpath, &$location, &$destination) {
            $location = $this->applyPathPrefix($path);
            $destination = $this->applyPathPrefix($newpath);
            return $this->ensureDirectory(dirname($destination));
        })->then(function () use (&$location, &$destination) {
            return $this->filesystem->getAsyncFilesystem()->file($location)->copy($this->filesystem->getAsyncFilesystem()->file($destination));
        })->then(function () {
            return true;
        }, function ($e) {
            return false;
        });
    }

    /**
     * @inheritdoc
     */
    public function delete($path)
    {
        return $this->readyPromise->then(function () use ($path) {
            $location = $this->applyPathPrefix($path);
            return $this->filesystem->getAsyncFilesystem()->file($location)->remove();
        })->then(function () {
            return true;
        }, function () {
            return false;
        });
    }

    /**
     * @inheritdoc
     */
    public function listContents($directory = '', $recursive = false)
    {
        $method = $recursive ? 'lsRecursive' : 'ls';

        return $this->readyPromise->then(function () use ($directory, $method) {
            $location = $this->applyPathPrefix($directory);
            return $this->filesystem->isDirectory($location)->then(function ($isDir) use ($method, $location) {
                return $isDir
                    ? $this->filesystem->getAsyncFilesystem()->dir($location)->$method()
                    : [];
            });
        })->then(function ($list) {
            $promises = [];
            foreach ($list as $node) {
                $path = $node->getPath();

                if (preg_match('#(^|/|\\\\)\.{1,2}$#', $path)) {
                    continue;
                }

                $promises[] = $this->getNormalizedFileInfoAsync($node);
            }

            return Promise\all($promises);
        })->then(function ($results) {
            return array_filter($results);
        });
    }

    /**
     * @inheritdoc
     */
    public function getMetadata($path)
    {
        return $this->readyPromise->then(function () use ($path) {
            $location = $this->applyPathPrefix($path);
            return $this->getNodeAsync($location);
        })->then(function ($node) {
            return $this->getNormalizedFileInfoAsync($node);
        });
    }

    /**
     * @inheritdoc
     */
    public function getSize($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * @inheritdoc
     */
    public function getMimetype($path)
    {
        // TODO
        $location = $this->applyPathPrefix($path);

        $mimetype = Util\MimeType::detectByFilename($location);

        return Promise\resolve(['path' => $path, 'type' => 'file', 'mimetype' => $mimetype]);
    }

    /**
     * @inheritdoc
     */
    public function getTimestamp($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * @inheritdoc
     */
    public function getVisibility($path)
    {
        $location = $this->applyPathPrefix($path);
        return $this->readyPromise->then(function () use ($location, $path) {
            return $this->getNodeAsync($location);
        })->then(function ($node) {
            /**
             * @var $node ExtendedFileInterface
             */
            return $node->stat();

        })->then(function ($stat) use ($location, $path) {
            $permissions = octdec(substr(sprintf('%o', $stat['mode']), -4));

            return $this->filesystem->type($location)->then(function ($type) use ($location, $permissions, $path) {
                foreach ($this->permissionMap[$type] as $visibility => $visibilityPermissions) {
                    if ($visibilityPermissions == $permissions) {
                        return compact('path', 'visibility');
                    }
                }

                $visibility = substr(sprintf('%o', fileperms($location)), -4);

                return compact('path', 'visibility');
            });
        });
    }

    /**
     * @inheritdoc
     */
    public function setVisibility($path, $visibility)
    {

        return $this->readyPromise->then(function () use ($path) {
            $location = $this->applyPathPrefix($path);
            return $this->detectType($location);
        })->then(function ($node) use ($visibility) {
            $type = $this->getType($node);
            return $node->chmod($this->permissionMap[$type][$visibility]);
        })->then(function () use ($path, $visibility) {
            return compact('path', 'visibility');
        }, function () {
            return false;
        });
    }

    /**
     * @inheritdoc
     */
    public function createDir($dirname, Config $config)
    {
        $visibility = $config->get('visibility', 'public');

        $result = ['path' => $dirname, 'type' => 'dir'];
        $location = null;

        return $this->readyPromise->then(function () use ($dirname, $visibility, &$location) {
            $location = $this->applyPathPrefix($dirname);
            return $this->filesystem->getAsyncFilesystem()->dir($location)->createRecursive($this->permissionMap['dir'][$visibility]);
        })->then(function () use ($result) {
            return $result;
        }, function ($reason) use ($dirname, &$location, $result) {
            return $this->getNodeAsync($location)->then(function ($node) use ($result) {
                return $this->getType($node) === 'dir' ? $result : false;
            });
        }); 
    }

    /**
     * @inheritdoc
     */
    public function deleteDir($dirname)
    {
        $location = null;
        return $this->readyPromise->then(function () use ($dirname, &$location) {
            $location = $this->applyPathPrefix($dirname);
            return $this->filesystem->getAsyncFilesystem()->dir($location)->lsRecursive();
        })->then(function ($list) {
            $promises = [];
            foreach ($list as $node) {
                $path = $node->getPath();

                if (preg_match('#(^|/|\\\\)\.{1,2}$#', $path)) {
                    continue;
                }

                $promises[] = $this->deleteFileInfoObject($node);
            }

            return Promise\all($promises);
        })->then(function () use (&$location) {
            return $this->filesystem->getAsyncFilesystem()->dir($location)->removeRecursive();
        })->then(function () {
            return true;
        }, function () {
            return false;
        });
    }

    protected function deleteFileInfoObject(NodeInterface $node)
    {
        if ($node instanceof LinkInterface) {
            return $this->filesystem->delete($node->getPath());
        }

        return $this->getType($node) === 'dir'
            ? true
            : $node->remove();
    }

    /**
     * Gets the constructor ready promise.
     */
    public function getReadyPromise()
    {
        return $this->readyPromise;
    }

    /**
     * Normalize the file info.
     *
     * @param NodeInterface $file
     *
     * @return Promise\FulfilledPromise|Promise\Promise|Promise\PromiseInterface|Promise\RejectedPromise
     *
     * @throws NotSupportedException
     */
    protected function getNormalizedFileInfoAsync(NodeInterface $file)
    {
        if (! $file instanceof LinkInterface) {
            return $this->mapFileInfoAsync($file);
        }

        if ($this->linkHandling & self::DISALLOW_LINKS) {
            return Promise\reject(new NotSupportedException('Links are not supported, encountered link at ' . $file->getPath()));
        }
    }

    /**
     * Get the normalized path from a NodeInterface.
     *
     * @param NodeInterface $file
     *
     * @return \React\Promise\PromiseInterface.<string>
     */
    protected function getFilePath(NodeInterface $file)
    {
        return $this->readyPromise->then(function () use ($file) {
            $location = $file->getPath();
            $path = $this->removePathPrefix($location);
            return trim(str_replace('\\', '/', $path), '/');
        });
    }

    /**
     * Get the type from a NodeInterface.
     *
     * @param NodeInterface $file
     *
     * @return string
     */
    protected function getType(NodeInterface $file)
    {
        if ($file instanceof LinkInterface) {
            return 'link';
        } else if ($file instanceof FileInterface) {
            return 'file';
        } else if ($file instanceof DirectoryInterface) {
            return 'dir';
        } else {
            return null;
        }
    }

    /**
     * @param NodeInterface $file
     *
     * @return \React\Promise\PromiseInterface.<array>
     */
    protected function mapFileInfoAsync(NodeInterface $file)
    {
        $normalized = [
            'type' => $this->getType($file),
        ];
        return Promise\all([
            $file->stat(),
            $normalized['type'] === 'file'
                ? $file->size() : null,
            $this->getFilePath($file),
        ])->then(function ($results) use ($normalized) {
            $normalized['timestamp'] = $results[0]['mtime']->getTimestamp();
            if ($normalized['type'] === 'file') {
                $normalized['size'] = $results[1];
            }
            $normalized['path'] = $results[2];
            return $normalized;
        });
    }

    /**
     * Build a \React\Filesystem\Node\NodeInterface node using brute force.
     *
     * @param string $path
     *
     * @return \React\Promise\Promise.<\React\Filesystem\Node\NodeInterface>
     */
    protected function getNodeAsync($path)
    {
        return Promise\race([
            $this->filesystem->getAsyncFilesystem()->constructLink($path)->then(function ($node) {
                return $node;
            }, function () {
                return false;
            }),
            ($dir = $this->filesystem->getAsyncFilesystem()->dir($path))->stat()->then(function () use (&$dir) {
                return $dir;
            }, function () {
                return false;
            }),
        ])->then(function ($result) use ($path) {
            return $result ? $result : $this->filesystem->getAsyncFilesystem()->file($path);
        });
    }

    /**
     * Build a \React\Filesystem\Node\NodeInterface node using brute force.
     *
     * @param string $path
     *
     * @return Promise\PromiseInterface<NodeInterface>
     */
    protected function detectType($path)
    {
        return $this->filesystem->getAsyncFilesystem()->getAdapter()->detectType($path);
    }


    protected function guardAgainstUnreadableFileInfo(ExtendedFileInterface $file)
    {
        return $file->isReadable()->then(function ($isReadable) use ($file) {
            if ( ! $isReadable) {

                throw new UnreadableFileException(
                    sprintf(
                        'Unreadable file encountered: %s',
                        $file->getPath()
                    )
                );
            }
        });
    }
}
