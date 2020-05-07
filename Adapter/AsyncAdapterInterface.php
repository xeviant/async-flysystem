<?php

namespace Xeviant\AsyncFlysystem\Adapter;

use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use React\Promise\PromiseInterface;

interface AsyncAdapterInterface extends AdapterInterface
{
    /**
     * Write a new file.
     *
     * @param string $path
     * @param string $contents
     * @param Config $config   Config object
     *
     * @return PromiseInterface<array|false false on failure file meta data on success>
     */
    public function write($path, $contents, Config $config);

    /**
     * Write a new file using a stream.
     *
     * @param string   $path
     * @param resource $resource
     * @param Config   $config   Config object
     *
     * @return PromiseInterface<array|false false on failure file meta data on success>
     */
    public function writeStream($path, $resource, Config $config);

    /**
     * Update a file.
     *
     * @param string $path
     * @param string $contents
     * @param Config $config   Config object
     *
     * @return PromiseInterface<array|false false on failure file meta data on success>
     */
    public function update($path, $contents, Config $config);

    /**
     * Update a file using a stream.
     *
     * @param string   $path
     * @param resource $resource
     * @param Config   $config   Config object
     *
     * @return PromiseInterface<array|false false on failure file meta data on success>
     */
    public function updateStream($path, $resource, Config $config);

    /**
     * Rename a file.
     *
     * @param string $path
     * @param string $newpath
     *
     * @return PromiseInterface<bool>
     */
    public function rename($path, $newpath);

    /**
     * Copy a file.
     *
     * @param string $path
     * @param string $newpath
     *
     * @return PromiseInterface<bool>
     */
    public function copy($path, $newpath);

    /**
     * Delete a file.
     *
     * @param string $path
     *
     * @return PromiseInterface<bool>
     */
    public function delete($path);

    /**
     * Delete a directory.
     *
     * @param string $dirname
     *
     * @return PromiseInterface<bool>
     */
    public function deleteDir($dirname);

    /**
     * Create a directory.
     *
     * @param string $dirname directory name
     * @param Config $config
     *
     * @return PromiseInterface<array|false>
     */
    public function createDir($dirname, Config $config);

    /**
     * Set the visibility for a file.
     *
     * @param string $path
     * @param string $visibility
     *
     * @return PromiseInterface<array|false file meta data>
     */
    public function setVisibility($path, $visibility);

    /**
     * Check whether a file exists.
     *
     * @param string $path
     *
     * @return PromiseInterface<array|bool|null>
     */
    public function has($path);

    /**
     * Read a file.
     *
     * @param string $path
     *
     * @return PromiseInterface<array|false>
     */
    public function read($path);

    /**
     * Read a file as a stream.
     *
     * @param string $path
     *
     * @return PromiseInterface<array|false>
     */
    public function readStream($path);

    /**
     * List contents of a directory.
     *
     * @param string $directory
     * @param bool   $recursive
     *
     * @return PromiseInterface<array>
     */
    public function listContents($directory = '', $recursive = false);

    /**
     * Get all the meta data of a file or directory.
     *
     * @param string $path
     *
     * @return PromiseInterface<array|false>
     */
    public function getMetadata($path);

    /**
     * Get the size of a file.
     *
     * @param string $path
     *
     * @return PromiseInterface<array|false>
     */
    public function getSize($path);

    /**
     * Get the mimetype of a file.
     *
     * @param string $path
     *
     * @return PromiseInterface<array|false>
     */
    public function getMimetype($path);

    /**
     * Get the last modified time of a file as a timestamp.
     *
     * @param string $path
     *
     * @return PromiseInterface<array|false>
     */
    public function getTimestamp($path);

    /**
     * Get the visibility of a file.
     *
     * @param string $path
     *
     * @return PromiseInterface<array|false>
     */
    public function getVisibility($path);
}