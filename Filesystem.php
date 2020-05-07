<?php

namespace Xeviant\AsyncFlysystem;

use Illuminate\Filesystem\Flysystem\Adapter\AsyncAdapterInterface;
use InvalidArgumentException;
use League\Flysystem\Adapter\CanOverwriteFiles;
use League\Flysystem\Config;
use League\Flysystem\Filesystem as FilesystemSync;
use League\Flysystem\Handler;
use League\Flysystem\Util;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\FileExistsException;
use League\Flysystem\Util\ContentListingFormatter;
use React\Filesystem\Node\Directory;
use React\Filesystem\Node\File;
use React\Promise;
use React\Promise\PromiseInterface;


class Filesystem extends FilesystemSync implements AsyncFlysystemInterface
{
    private bool $adapterSupportsAsync;

    /**
     * Constructor.
     *
     * @param AsyncAdapterInterface $adapter
     * @param Config|array $config
     */
    public function __construct(AsyncAdapterInterface $adapter, Config $config = null)
    {
        parent::__construct($adapter, $config);

        $this->adapter = $adapter;
        $this->setConfig($config);
        $this->adapterSupportsAsync = $adapter instanceof AsyncAdapterInterface;
    }

    public function getAdapter(): AsyncAdapterInterface
    {
        return $this->adapter;
    }

    /**
     * @inheritdoc
     */
    public function has($path): PromiseInterface
    {
        $path = Util::normalizePath($path);

        if (strlen($path) === 0) {
            return Promise\resolve(false);
        }
       
        return $this->getAdapter()->has($path)->then(function ($result) {
            return (bool) $result;
        });
    }

    /**
     * @inheritdoc
     */
    public function write($path, $contents, array $config = [])
    {
        if (!$this->adapterSupportsAsync) {
            return $this->fallbackToSync(__FUNCTION__, func_get_args());
        }

        $path = Util::normalizePath($path);
        return $this->assertAbsent($path)->then(function () use ($path, $contents, $config) {
            $config = $this->prepareConfig($config);
            return $this->getAdapter()->write($path, $contents, $config);
        })->then(function ($result) {
            return (bool) $result;
        });
    }

    /**
     * @inheritdoc
     */
    public function writeStream($path, $resource, array $config = [])
    {
        if (!$this->adapterSupportsAsync) {
            return $this->fallbackToSync(__FUNCTION__, func_get_args());
        }

        if (!is_resource($resource)) {
            return Promise\reject(new InvalidArgumentException(__METHOD__ . ' expects argument #2 to be a valid resource.'));
        }

        $path = Util::normalizePath($path);
        return $this->assertAbsent($path)->then(function () use ($path, $resource, $config) {
            $config = $this->prepareConfig($config);

            Util::rewindStream($resource);

            return $this->getAdapter()->writeStream($path, $resource, $config);
        })->then(function ($result) {
            return (bool) $result;
        });
    }

    /**
     * @inheritdoc
     */
    public function put($path, $contents, array $config = [])
    {
        if (!$this->adapterSupportsAsync) {
            return $this->fallbackToSync(__FUNCTION__, func_get_args());
        }

        $path = Util::normalizePath($path);
        $config = $this->prepareConfig($config);

        if (!$this->getAdapter() instanceof CanOverwriteFiles) {
            $methodPromise = $this->has($path)->then(function ($result) {
                return $result === true ? 'update' : 'write';
            });
        } else {
            $methodPromise = Promise\resolve('write');
        }

        return $methodPromise->then(function ($method) use ($path, $contents, $config) {
            return $this->getAdapter()->$method($path, $contents, $config);
        })->then(function ($result) {
            return (bool) $result;
        });
    }

    /**
     * @inheritdoc
     */
    public function putStream($path, $resource, array $config = [])
    {
        if (!$this->adapterSupportsAsync) {
            return $this->fallbackToSync(__FUNCTION__, func_get_args());
        }

        if (!is_resource($resource)) {
            return Promise\reject(new InvalidArgumentException(__METHOD__ . ' expects argument #2 to be a valid resource.'));
        }

        $adapterMethod = 'writeStream';
        $path = Util::normalizePath($path);
        $config = $this->prepareConfig($config);
        Util::rewindStream($resource);

        if (!$this->getAdapter() instanceof CanOverwriteFiles) {
            $methodPromise = $this->has($path)->then(function ($result) {
               return $result === true ? 'updateStream' : 'writeStream';
            });
        } else {
            $methodPromise = Promise\resolve('writeStream');
        }

        return $methodPromise->then(function ($method) use ($path, $resource, $config) {
            return $this->getAdapter()->$method($path, $resource, $config);
        })->then(function ($result) {
            return (bool) $result;
        });
    }

    /**
     * @inheritdoc
     */
    public function readAndDelete($path)
    {
        if (!$this->adapterSupportsAsync) {
            return $this->fallbackToSync(__FUNCTION__, func_get_args());
        }

        $path = Util::normalizePath($path);
        return $this->assertPresent($path)->then(function () use ($path) {
            return $this->read($path);
        })->then(function ($contents) use ($path) {
            return $this->delete($path)->then(function () use ($contents) {
                return $contents;
            });
        });
    }

    /**
     * @inheritdoc
     */
    public function update($path, $contents, array $config = [])
    {
        if (!$this->adapterSupportsAsync) {
            return $this->fallbackToSync(__FUNCTION__, func_get_args());
        }

        $path = Util::normalizePath($path);
        $config = $this->prepareConfig($config);

        return $this->assertPresent($path)->then(function () use ($path, $contents, $config) {
            return $this->getAdapter()->update($path, $contents, $config);
        })->then(function ($result) {
            return (bool) $result;
        });
    }

    /**
     * @inheritdoc
     */
    public function updateStream($path, $resource, array $config = [])
    {
        if (!$this->adapterSupportsAsync) {
            return $this->fallbackToSync(__FUNCTION__, func_get_args());
        }

        if (!is_resource($resource)) {
            return Promise\reject(new InvalidArgumentException(__METHOD__ . ' expects argument #2 to be a valid resource.'));
        }

        $path = Util::normalizePath($path);
        $config = $this->prepareConfig($config);
        return $this->assertPresent($path)->then(function () use ($path, $resource, $config) {
            Util::rewindStream($resource);

            return $this->getAdapter()->updateStream($path, $resource, $config);
        })->then(function ($result) {
            return (bool) $result;
        });
    }

    /**
     * @inheritdoc
     */
    public function read($path)
    {
        if (!$this->adapterSupportsAsync) {
            return $this->fallbackToSync(__FUNCTION__, func_get_args());
        }

        $path = Util::normalizePath($path);
        return $this->assertPresent($path)->then(function () use ($path) {
            return $this->getAdapter()->read($path);
        })->then(function ($object) {
            return !$object ? false : $object['contents'];
        });
    }

    /**
     * @inheritdoc
     */
    public function readStream($path)
    {
        if (!$this->adapterSupportsAsync) {
            return $this->fallbackToSync(__FUNCTION__, func_get_args());
        }

        $path = Util::normalizePath($path);
        return $this->assertPresent($path)->then(function () use ($path) {
            return $this->getAdapter()->readStream($path);
        })->then(function ($object) {
            return !$object ? false: $object['stream'];
        });
    }

    /**
     * @inheritdoc
     */
    public function rename($path, $newpath)
    {
        if (!$this->adapterSupportsAsync) {
            return $this->fallbackToSync(__FUNCTION__, func_get_args());
        }

        $path = Util::normalizePath($path);
        $newpath = Util::normalizePath($newpath);
        return Promise\all([
            $this->assertPresent($path),
            $this->assertAbsent($newpath),
        ])->then(function () use ($path, $newpath) {
            return $this->getAdapter()->rename($path, $newpath);
        })->then(function ($result) {
            return (bool) $result;
        });
    }

    /**
     * @inheritdoc
     */
    public function copy($path, $newpath)
    {
        if (!$this->adapterSupportsAsync) {
            return $this->fallbackToSync(__FUNCTION__, func_get_args());
        }

        $path = Util::normalizePath($path);
        $newpath = Util::normalizePath($newpath);
        return Promise\all([
            $this->assertPresent($path),
            $this->assertAbsent($newpath),
        ])->then(function () use ($path, $newpath) {
            return $this->getAdapter()->copy($path, $newpath);
        });
    }

    /**
     * @inheritdoc
     */
    public function delete($path)
    {
        if (!$this->adapterSupportsAsync) {
            return $this->fallbackToSync(__FUNCTION__, func_get_args());
        }

        $path = Util::normalizePath($path);
        return $this->assertPresent($path)->then(function () use ($path) {
            return $this->getAdapter()->delete($path);
        });
    }

    /**
     * @inheritdoc
     */
    public function deleteDir($dirname)
    {
        if (!$this->adapterSupportsAsync) {
            return $this->fallbackToSync(__FUNCTION__, func_get_args());
        }

        $dirname = Util::normalizePath($dirname);

        if ($dirname === '') {
            return Promise\reject(new RootViolationException('Root directories can not be deleted.'));
        }

        return $this->getAdapter()->deleteDir($dirname)->then(function ($result) {
            return (bool) $result;
        });
    }

    /**
     * @inheritdoc
     */
    public function createDir($dirname, array $config = [])
    {
        if (!$this->adapterSupportsAsync) {
            return $this->fallbackToSync(__FUNCTION__, func_get_args());
        }

        $dirname = Util::normalizePath($dirname);
        $config = $this->prepareConfig($config);

        return $this->getAdapter()->createDir($dirname, $config)->then(function ($result) {
            return (bool) $result;
        });
    }

    /**
     * @inheritdoc
     */
    public function listContents($directory = '', $recursive = false)
    {
        if (!$this->adapterSupportsAsync) {
            return $this->fallbackToSync(__FUNCTION__, func_get_args());
        }

        $directory = Util::normalizePath($directory);
        return $this->getAdapter()->listContents($directory, $recursive)->then(function ($contents) use ($directory, $recursive) {

            return (new ContentListingFormatter($directory, $recursive, $this->config->get('case_sensitive', true)))
                ->formatListing($contents);
        });
    }

    /**
     * @inheritdoc
     */
    public function getMimetype($path)
    {
        if (!$this->adapterSupportsAsync) {
            return $this->fallbackToSync(__FUNCTION__, func_get_args());
        }

        $path = Util::normalizePath($path);
        return $this->assertPresent($path)->then(function () use ($path) {
            return $this->getAdapter()->getMimetype($path);
        })->then(function ($object) {
            return (!$object || !array_key_exists('mimetype', $object))
                ? false : $object['mimetype'];
        });
    }

    /**
     * @inheritdoc
     */
    public function getTimestamp($path)
    {
        if (!$this->adapterSupportsAsync) {
            return $this->fallbackToSync(__FUNCTION__, func_get_args());
        }

        $path = Util::normalizePath($path);
        return $this->assertPresent($path)->then(function () use ($path) {
            return $this->getAdapter()->getTimestamp($path);
        })->then(function ($object) {
            return (!$object || !array_key_exists('timestamp', $object))
                ? false : $object['timestamp'];
        });
    }

    /**
     * @inheritdoc
     */
    public function getVisibility($path)
    {
        if (!$this->adapterSupportsAsync) {
            return $this->fallbackToSync(__FUNCTION__, func_get_args());
        }

        $path = Util::normalizePath($path);
        return $this->assertPresent($path)->then(function () use ($path) {
            return $this->getAdapter()->getVisibility($path);
        })->then(function ($object) {
            return (!$object || !array_key_exists('visibility', $object))
                ? false : $object['visibility'];
        });
    }

    /**
     * @inheritdoc
     */
    public function getSize($path)
    {
        if (!$this->adapterSupportsAsync) {
            return $this->fallbackToSync(__FUNCTION__, func_get_args());
        }

        $path = Util::normalizePath($path);
        return $this->assertPresent($path)->then(function () use ($path) {
            return $this->getAdapter()->getSize($path);
        })->then(function ($object) {
            return (!$object || !array_key_exists('size', $object))
                ? false : (int) $object['size'];
        });
    }

    /**
     * @inheritdoc
     */
    public function setVisibility($path, $visibility)
    {
        if (!$this->adapterSupportsAsync) {
            return $this->fallbackToSync(__FUNCTION__, func_get_args());
        }

        $path = Util::normalizePath($path);
        return $this->assertPresent($path)->then(function () use ($path, $visibility) {
            return $this->getAdapter()->setVisibility($path, $visibility);
        })->then(function ($result) {
            return (bool) $result;
        });
    }

    /**
     * @inheritdoc
     */
    public function getMetadata($path)
    {
        if (!$this->adapterSupportsAsync) {
            return $this->fallbackToSync(__FUNCTION__, func_get_args());
        }

        $path = Util::normalizePath($path);
        return $this->assertPresent($path)->then(function () use ($path) {
            return $this->getAdapter()->getMetadata($path);
        });
    }

    /**
     * @inheritdoc
     */
    public function get($path, Handler $handler = null)
    {
        if (!$this->adapterSupportsAsync) {
            return $this->fallbackToSync(__FUNCTION__, func_get_args());
        }

        $path = Util::normalizePath($path);

        if (!$handler) {
            $handlerPromise = $this->getMetadata($path)->then(function ($metadata) use ($path) {
                return $metadata['type'] === 'file' ? new File($path, $this) : new Directory($this, $path);
            });
        } else {
            $handlerPromise = Promise\resolve($handler);
        }

        return $handlerPromise->then(function ($handler) use ($path) {
            $handler->setPath($path);
            $handler->setFilesystem($this);
            return $handler;
        });
    }

    /**
     * Assert a file is present.
     *
     * @param string $path path to file
     *
     *
     * @return PromiseInterface|Promise\RejectedPromise<FileNotFoundException>
     */
    public function assertPresent($path)
    {
        if ($this->config->get('disable_asserts', false) === true) {
            return Promise\resolve();
        }
        return $this->has($path)->then(function ($result) use ($path) {
            if ($result !== true) {
                throw new FileNotFoundException($path);
            }
        });
    }

    /**
     * Assert a file is absent.
     *
     * @param string $path path to file
     *
     *
     * @return PromiseInterface
     */
    public function assertAbsent($path)
    {
        if ($this->config->get('disable_asserts', false) === true) {
            return Promise\resolve();
        }
        return $this->has($path)->then(function ($result) use ($path) {
            if ($result === true) {
                throw new FileExistsException($path);
            }
        });
    }

    /**
     * Invokes the synchronous version of the given async method.
     *
     * @param string $method The async method invoked.
     * @param array $arguments
     *
     * @return PromiseInterface
     */
    protected function fallbackToSync($method, $arguments)
    {
        try {
            $result = call_user_func_array([$this, substr($method, 0, strlen($method) - 5)], $arguments);
            return Promise\resolve($result);
        } catch (\Exception $exception) {
            return Promise\reject($exception);
        }
    }
}
