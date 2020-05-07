<?php

namespace Xeviant\AsyncFlysystem;

use React\Promise\PromiseInterface;

interface AsyncReadInterface
{
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
