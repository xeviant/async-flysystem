<?php

namespace Xeviant\AsyncFlysystem\Adapter;

use League\Flysystem\Config;
use Clue\React\Block;

trait AsyncToSyncTrait {

    /**
     * @inheritdoc
     */
    public function has($path)
    {
        return Block\await(call_user_func_array([$this, 'hasAsync'], func_get_args()), $this->loop);
    }

    /**
     * @inheritdoc
     */
    public function read($path)
    {
        return Block\await(call_user_func_array([$this, 'readAsync'], func_get_args()), $this->loop);
    }

    /**
     * @inheritdoc
     */
    public function readStream($path)
    {
        return Block\await(call_user_func_array([$this, 'readStreamAsync'], func_get_args()), $this->loop);
    }

    /**
     * @inheritdoc
     */
    public function listContents($directory = '', $recursive = false)
    {
        return Block\await(call_user_func_array([$this, 'listContentsAsync'], func_get_args()), $this->loop);
    }

    /**
     * @inheritdoc
     */
    public function getMetadata($path)
    {
        return Block\await(call_user_func_array([$this, 'getMetadataAsync'], func_get_args()), $this->loop);
    }

    /**
     * @inheritdoc
     */
    public function getSize($path)
    {
        return Block\await(call_user_func_array([$this, 'getSizeAsync'], func_get_args()), $this->loop);
    }

    /**
     * @inheritdoc
     */
    public function getMimetype($path)
    {
        return Block\await(call_user_func_array([$this, 'getMimetypeAsync'], func_get_args()), $this->loop);
    }

    /**
     * @inheritdoc
     */
    public function getTimestamp($path)
    {
        return Block\await(call_user_func_array([$this, 'getTimestampAsync'], func_get_args()), $this->loop);
    }

    /**
     * @inheritdoc
     */
    public function getVisibility($path)
    {
        return Block\await(call_user_func_array([$this, 'getVisibilityAsync'], func_get_args()), $this->loop);
    }

    /**
     * @inheritdoc
     */
    public function write($path, $contents, Config $config)
    {
        return Block\await(call_user_func_array([$this, 'writeAsync'], func_get_args()), $this->loop);
    }

    /**
     * @inheritdoc
     */
    public function writeStream($path, $resource, Config $config)
    {
        return Block\await(call_user_func_array([$this, 'writeStreamAsync'], func_get_args()), $this->loop);
    }

    /**
     * @inheritdoc
     */
    public function update($path, $contents, Config $config)
    {
        return Block\await(call_user_func_array([$this, 'updateAsync'], func_get_args()), $this->loop);
    }

    /**
     * @inheritdoc
     */
    public function updateStream($path, $resource, Config $config)
    {
        return Block\await(call_user_func_array([$this, 'updateStreamAsync'], func_get_args()), $this->loop);
    }

    /**
     * @inheritdoc
     */
    public function rename($path, $newpath)
    {
        return Block\await(call_user_func_array([$this, 'renameAsync'], func_get_args()), $this->loop);
    }

    /**
     * @inheritdoc
     */
    public function copy($path, $newpath)
    {
        return Block\await(call_user_func_array([$this, 'copyAsync'], func_get_args()), $this->loop);
    }

    /**
     * @inheritdoc
     */
    public function delete($path)
    {
        return Block\await(call_user_func_array([$this, 'deleteAsync'], func_get_args()), $this->loop);
    }

    /**
     * @inheritdoc
     */
    public function deleteDir($dirname)
    {
        return Block\await(call_user_func_array([$this, 'deleteDirAsync'], func_get_args()), $this->loop);
    }

    /**
     * @inheritdoc
     */
    public function createDir($dirname, Config $config)
    {
        return Block\await(call_user_func_array([$this, 'createDirAsync'], func_get_args()), $this->loop);
    }

    /**
     * @inheritdoc
     */
    public function setVisibility($path, $visibility)
    {
        return Block\await(call_user_func_array([$this, 'setVisibilityAsync'], func_get_args()), $this->loop);
    }
}
