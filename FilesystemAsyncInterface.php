<?php

namespace Xeviant\AsyncFlysystem;

use League\Flysystem\FilesystemInterface;
use InvalidArgumentException;

interface FilesystemAsyncInterface extends FilesystemInterface
{
    /**
     * Check whether a file exists.
     *
     * @param string $path
     *
     * @return \React\Promise\PromiseInterface.<bool>
     */
    public function hasAsync($path);

    /**
     * Read a file.
     *
     * @param string $path The path to the file.
     *
     * @throws FileNotFoundException Rejects if $path doesn't exist.
     *
     * @return \React\Promise\PromiseInterface.<string|false> The file contents or false on failure.
     */
    public function readAsync($path);

    /**
     * Retrieves a read-stream for a path.
     *
     * @param string $path The path to the file.
     *
     * @throws FileNotFoundException Rejects if $path doesn't exist.
     *
     * @return \React\Promise\PromiseInterface.<resource|false> The path resource or false on failure.
     */
    public function readStreamAsync($path);

    /**
     * List contents of a directory.
     *
     * @param string $directory The directory to list.
     * @param bool   $recursive Whether to list recursively.
     *
     * @return \React\Promise\PromiseInterface.<array> A list of file metadata.
     */
    public function listContentsAsync($directory = '', $recursive = false);

    /**
     * Get a file's metadata.
     *
     * @param string $path The path to the file.
     *
     * @throws FileNotFoundException Rejects if $path doesn't exist.
     *
     * @return \React\Promise\PromiseInterface.<array|false> The file metadata or false on failure.
     */
    public function getMetadataAsync($path);

    /**
     * Get a file's size.
     *
     * @param string $path The path to the file.
     *
     * @throws FileNotFoundException Rejects if $path doesn't exist.
     *
     * @return \React\Promise\PromiseInterface.<int|false> The file size or false on failure.
     */
    public function getSizeAsync($path);

    /**
     * Get a file's mime-type.
     *
     * @param string $path The path to the file.
     *
     * @throws FileNotFoundException Rejects if $path doesn't exist.
     *
     * @return \React\Promise\PromiseInterface.<string|false> The file mime-type or false on failure.
     */
    public function getMimetypeAsync($path);

    /**
     * Get a file's timestamp.
     *
     * @param string $path The path to the file.
     *
     * @throws FileNotFoundException Rejects if $path doesn't exist.
     *
     * @return \React\Promise\PromiseInterface.<string|false> The timestamp or false on failure.
     */
    public function getTimestampAsync($path);

    /**
     * Get a file's visibility.
     *
     * @param string $path The path to the file.
     *
     * @throws FileNotFoundException Rejects if $path doesn't exist.
     *
     * @return \React\Promise\PromiseInterface.<string|false> The visibility (public|private) or false on failure.
     */
    public function getVisibilityAsync($path);

    /**
     * Write a new file.
     *
     * @param string $path     The path of the new file.
     * @param string $contents The file contents.
     * @param array  $config   An optional configuration array.
     *
     * @throws FileNotFoundException Rejects if $path doesn't exist.
     *
     * @return \React\Promise\PromiseInterface.<bool> True on success, false on failure.
     */
    public function writeAsync($path, $contents, array $config = []);

    /**
     * Write a new file using a stream.
     *
     * @param string   $path     The path of the new file.
     * @param resource $resource The file handle.
     * @param array    $config   An optional configuration array.
     *
     * @throws InvalidArgumentException Rejects if $resource is not a file handle.
     * @throws FileNotFoundException Rejects if $path doesn't exist.
     *
     * @return \React\Promise\PromiseInterface.<bool> True on success, false on failure.
     */
    public function writeStreamAsync($path, $resource, array $config = []);

    /**
     * Update an existing file.
     *
     * @param string $path     The path of the existing file.
     * @param string $contents The file contents.
     * @param array  $config   An optional configuration array.
     *
     * @throws FileNotFoundException Rejects if $path doesn't exist.
     *
     * @return \React\Promise\PromiseInterface.<bool> True on success, false on failure.
     */
    public function updateAsync($path, $contents, array $config = []);

    /**
     * Update an existing file using a stream.
     *
     * @param string   $path     The path of the existing file.
     * @param resource $resource The file handle.
     * @param array    $config   An optional configuration array.
     *
     * @throws InvalidArgumentException Rejects if $resource is not a file handle.
     * @throws FileNotFoundException Rejects if $path doesn't exist.
     *
     * @return \React\Promise\PromiseInterface.<bool> True on success, false on failure.
     */
    public function updateStreamAsync($path, $resource, array $config = []);

    /**
     * Rename a file.
     *
     * @param string $path    Path to the existing file.
     * @param string $newpath The new path of the file.
     *
     * @throws FileExistsException   Rejects if $newpath exists.
     * @throws FileNotFoundException Rejects if $path does not exist.
     *
     * @return \React\Promise\PromiseInterface.<bool> True on success, false on failure.
     */
    public function renameAsync($path, $newpath);

    /**
     * Copy a file.
     *
     * @param string $path    Path to the existing file.
     * @param string $newpath The new path of the file.
     *
     * @throws FileExistsException   Rejects if $newpath exists.
     * @throws FileNotFoundException Rejects if $path does not exist.
     *
     * @return \React\Promise\PromiseInterface.<bool> True on success, false on failure.
     */
    public function copyAsync($path, $newpath);

    /**
     * Delete a file.
     *
     * @param string $path
     *
     * @throws FileNotFoundException Rejects if $path doesn't exist.
     *
     * @return \React\Promise\PromiseInterface.<bool> True on success, false on failure.
     */
    public function deleteAsync($path);

    /**
     * Delete a directory.
     *
     * @param string $dirname
     *
     * @throws RootViolationException Rejects if $dirname is empty.
     *
     * @return \React\Promise\PromiseInterface.<bool> True on success, false on failure.
     */
    public function deleteDirAsync($dirname);

    /**
     * Create a directory.
     *
     * @param string $dirname The name of the new directory.
     * @param array  $config  An optional configuration array.
     *
     * @return \React\Promise\PromiseInterface.<bool> True on success, false on failure.
     */
    public function createDirAsync($dirname, array $config = []);

    /**
     * Set the visibility for a file.
     *
     * @param string $path       The path to the file.
     * @param string $visibility One of 'public' or 'private'.
     *
     * @throws FileNotFoundException Rejects if $path doesn't exist.
     *
     * @return \React\Promise\PromiseInterface.<bool> True on success, false on failure.
     */
    public function setVisibilityAsync($path, $visibility);

    /**
     * Create a file or update if exists.
     *
     * @param string $path     The path to the file.
     * @param string $contents The file contents.
     * @param array  $config   An optional configuration array.
     *
     * @return \React\Promise\PromiseInterface.<bool> True on success, false on failure.
     */
    public function putAsync($path, $contents, array $config = []);

    /**
     * Create a file or update if exists.
     *
     * @param string   $path     The path to the file.
     * @param resource $resource The file handle.
     * @param array    $config   An optional configuration array.
     *
     * @throws InvalidArgumentException Rejects if $resource is not a resource.
     *
     * @return \React\Promise\PromiseInterface.<bool> True on success, false on failure.
     */
    public function putStreamAsync($path, $resource, array $config = []);

    /**
     * Read and delete a file.
     *
     * @param string $path The path to the file.
     *
     * @throws FileNotFoundException
     *
     * @return \React\Promise\PromiseInterface.<string|false> The file contents, or false on failure.
     */
    public function readAndDeleteAsync($path);

    /**
     * Get a file/directory handler.
     *
     * @deprecated
     *
     * @param string  $path    The path to the file.
     * @param Handler $handler An optional existing handler to populate.
     *
     * @return \React\Promise\PromiseInterface.<Handler> Either a file or directory handler.
     */
    public function getAsync($path, Handler $handler = null);
}
