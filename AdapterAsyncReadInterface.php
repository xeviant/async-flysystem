<?php

namespace Xeviant\AsyncFlysystem;

use League\Flysystem\Config;

interface AdapterAsyncReadInterface extends AsyncReadInterface
{
    /**
     * Write a new file.
     *
     * @param string $path
     * @param string $contents
     * @param Config $config   Config object
     *
     * @return \React\Promise\Promise.<array|false> false on failure file meta data on success
     */
    public function writeAsync($path, $contents, Config $config);

    /**
     * Write a new file using a stream.
     *
     * @param string   $path
     * @param resource $resource
     * @param Config   $config   Config object
     *
     * @return \React\Promise\Promise.<array|false> false on failure file meta data on success
     */
    public function writeStreamAsync($path, $resource, Config $config);

    /**
     * Update a file.
     *
     * @param string $path
     * @param string $contents
     * @param Config $config   Config object
     *
     * @return \React\Promise\Promise.<array|false> false on failure file meta data on success
     */
    public function updateAsync($path, $contents, Config $config);

    /**
     * Update a file using a stream.
     *
     * @param string   $path
     * @param resource $resource
     * @param Config   $config   Config object
     *
     * @return \React\Promise\Promise.<array|false> false on failure file meta data on success
     */
    public function updateStreamAsync($path, $resource, Config $config);

    /**
     * Rename a file.
     *
     * @param string $path
     * @param string $newpath
     *
     * @return \React\Promise\Promise.<bool>
     */
    public function renameAsync($path, $newpath);

    /**
     * Copy a file.
     *
     * @param string $path
     * @param string $newpath
     *
     * @return \React\Promise\Promise.<bool>
     */
    public function copyAsync($path, $newpath);

    /**
     * Delete a file.
     *
     * @param string $path
     *
     * @return \React\Promise\Promise.<bool>
     */
    public function deleteAsync($path);

    /**
     * Delete a directory.
     *
     * @param string $dirname
     *
     * @return \React\Promise\Promise.<bool>
     */
    public function deleteDirAsync($dirname);

    /**
     * Create a directory.
     *
     * @param string $dirname directory name
     * @param Config $config
     *
     * @return \React\Promise\Promise.<array|false>
     */
    public function createDirAsync($dirname, Config $config);

    /**
     * Set the visibility for a file.
     *
     * @param string $path
     * @param string $visibility
     *
     * @return \React\Promise\Promise.<array|false> file meta data
     */
    public function setVisibilityAsync($path, $visibility);
}
