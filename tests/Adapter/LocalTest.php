<?php

namespace Xeviant\AsyncFlysystem\Tests\Adapter;

use Illuminate\Filesystem\Async\ExtendedFileInterface;
use Illuminate\Filesystem\Flysystem\Adapter\Local;
use League\Flysystem\Config;
use League\Flysystem\Exception;
use League\Flysystem\NotSupportedException;
use Prophecy\PhpUnit\ProphecyTrait;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use ReflectionClass;
use Xeviant\AsyncFlysystem\Tests\TestCase;
use function Clue\React\Block\await;
use function React\Promise\resolve;

function fopen($result, $mode)
{
    if (substr($result, -5) === 'false') {
        return false;
    }

    if (substr($result, -10) === 'fail.close') {
        return \fopen('data://text/plain,fail.close', $mode);
    }

    return call_user_func_array('fopen', func_get_args());
}

function fclose($result)
{
    if (is_resource($result) && stream_get_contents($result) === 'fail.close') {
        \fclose($result);

        return false;
    }

    return call_user_func_array('fclose', func_get_args());
}

function chmod($filename, $mode)
{
    if (strpos($filename, 'chmod.fail') !== false) {
        return false;
    }

    return \chmod($filename, $mode);
}

function mkdir($pathname, $mode = 0777, $recursive = false, $context = null)
{
    if (strpos($pathname, 'fail.plz') !== false) {
        return false;
    }

    return call_user_func_array('mkdir', func_get_args());
}


class LocalTest extends TestCase
{
    use ProphecyTrait;
    /**
     * @var Local
     */
    protected $adapter;

    protected $root;
    /**
     * @var LoopInterface
     */
    protected $loop;

    public function setup(): void
    {
        $this->root = __DIR__ . '/files/';
        $this->loop = Factory::create();

        $this->adapter = new Local($this->loop, $this->root);
    }

    public function teardown(): void
    {
        $it = new \RecursiveDirectoryIterator($this->root, \RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator(
            $it,
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            if ($file->getFilename() === '.' || $file->getFilename() === '..') {
                continue;
            }
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getPathname());
            }
        }
    }

    protected function await($promise)
    {
        return await($promise, $this->loop);
    }

    public function testStreamWrappersAreSupported()
    {
        if (IS_WINDOWS) {
            $this->markTestSkipped('Windows does not support this.');
        }

        $this->await($this->adapter->write('file.txt', 'contents', new Config()));

        $adapter = new Local($this->loop, 'file://' . $this->root);

        $this->assertCount(1, $this->await($adapter->listContents()));
    }

    public function testRelativeRootsAreSupportes()
    {
        $this->await((new Local($this->loop,__DIR__ . '/files'))->write('file.txt', 'contents', new Config()));

        $adapter = new Local($this->loop, __DIR__ . '/files/../files');
        $this->assertCount(1, $this->await($adapter->listContents()));
    }

    public function testHasWithDir()
    {
        $this->await($this->adapter->createDir('0', new Config()));
        $this->assertTrue($this->await($this->adapter->has('0')));
        $this->await($this->adapter->deleteDir('0'));
    }

    public function testHasWithFile()
    {
        $adapter = $this->adapter;
        $this->await($adapter->write('file.txt', 'content', new Config()));
        $this->assertTrue($this->await($adapter->has('file.txt')));
        $adapter->delete('file.txt');
    }

    public function testReadStream()
    {
        $adapter = $this->adapter;
        $this->await($adapter->write('file.txt', 'contents', new Config()));

        $result = $this->await($adapter->readStream('file.txt'));
        $this->assertIsArray($result);
        $this->assertArrayHasKey('stream', $result);
        $this->assertIsResource($result['stream']);
        fclose($result['stream']);
        $adapter->delete('file.txt');
    }

    public function testWriteStream()
    {
        $adapter = $this->adapter;
        $temp = tmpfile();
        fwrite($temp, 'dummy');
        rewind($temp);
        $this->await($adapter->writeStream('dir/file.txt', $temp, new Config(['visibility' => 'public'])));
        fclose($temp);
        $this->assertTrue($this->await($adapter->has('dir/file.txt')));
        $result = $this->await($adapter->read('dir/file.txt'));
        $this->assertEquals('dummy', $result['contents']);
        $adapter->deleteDir('dir');
    }

    public function testListingNonexistingDirectory()
    {
        $result = $this->await(
            $this->adapter->listContents('nonexisting/directory')
        );
        $this->assertEquals([], $result);
    }

    public function testUpdateStream()
    {
        $adapter = $this->adapter;
        $this->await($adapter->write('file.txt', 'initial', new Config()));
        $temp = tmpfile();
        fwrite($temp, 'dummy');
        $this->await($adapter->updateStream('file.txt', $temp, new Config()));
        fclose($temp);
        $this->assertTrue($this->await($adapter->has('file.txt')));
        $adapter->delete('file.txt');
    }

    public function testCreateZeroDir()
    {
        $this->await($this->adapter->createDir('0', new Config()));
        $this->assertTrue(is_dir($this->adapter->applyPathPrefix('0')));
        $this->adapter->deleteDir('0');
    }

    public function testCopy()
    {
        $adapter = $this->adapter;

        $this->await($adapter->write('file.ext', 'content', new Config(['visibility' => 'public'])));

        $this->assertTrue(
            $this->await($adapter->copy('file.ext', 'new.ext'))
        );
        $this->assertTrue(
            $this->await($adapter->has('new.ext'))
        );

        $this->await($adapter->delete('file.ext'));
        $this->await($adapter->delete('new.ext'));
    }

    public function testFailingStreamCalls()
    {
        $this->assertFalse(
            $this->await($this->adapter->writeStream('', tmpfile(), new Config()))
        );
        $this->assertFalse(
            $this->await($this->adapter->writeStream('', tmpfile(), new Config()))
        );
    }

    public function testNullPrefix()
    {
        $this->adapter->setPathPrefix('');
        $path = 'some' . DIRECTORY_SEPARATOR . 'path.ext';
        $this->assertEquals($path, $this->adapter->applyPathPrefix($path));
        $this->assertEquals($path, $this->adapter->removePathPrefix($path));
    }

    public function testWindowsPrefix()
    {
        $path = 'some' . DIRECTORY_SEPARATOR . 'path.ext';
        $expected = 'c:' . DIRECTORY_SEPARATOR . $path;

        $this->adapter->setPathPrefix('c:/');
        $prefixed = $this->adapter->applyPathPrefix($path);
        $this->assertEquals($expected, $prefixed);
        $this->assertEquals($path, $this->adapter->removePathPrefix($prefixed));

        $expected = 'c:\\\\some\\dir' . DIRECTORY_SEPARATOR . $path;
        $this->adapter->setPathPrefix('c:\\\\some\\dir\\');
        $prefixed = $this->adapter->applyPathPrefix($path);
        $this->assertEquals($expected, $prefixed);
        $this->assertEquals($path, $this->adapter->removePathPrefix($prefixed));
    }

    public function testGetPathPrefix()
    {
        $this->assertEquals(
            realpath($this->root),
            realpath($this->adapter->getPathPrefix())
        );
    }

    public function testRenameToNonExistsingDirectory()
    {
        $this->await($this->adapter->write('file.txt', 'contents', new Config()));
        $dirname = uniqid();
        $this->assertFalse(is_dir($this->root . DIRECTORY_SEPARATOR . $dirname));
        $this->assertTrue($this->await($this->adapter->rename('file.txt', $dirname . '/file.txt')));
    }

    public function testNotWritableRoot()
    {
        if (IS_WINDOWS) {
            $this->markTestSkipped("File permissions not supported on Windows.");
        }

        try {
            $root = $this->root . 'not-writable';
            mkdir($root, 0000, true);
            $this->expectException(\LogicException::class);
            $local = new Local($this->loop, $root);

            // Attempt to Write something ... only way to trigger this error for now
            $this->await($local->write('test.txt', 'content', new Config()));
        } catch (\Exception $e) {
            rmdir($root);
            throw $e;
        }
    }

    public function testListContents()
    {
        $this->await(
            $this->adapter->write('dirname/file.txt', 'contents', new Config())
        );

        $contents = $this->await(
            $this->adapter->listContents('dirname', false)
        );

        $this->assertCount(1, $contents);
        $this->assertArrayHasKey('type', $contents[0]);
    }

    public function testListContentsRecursive()
    {
        $this->await(
            $this->adapter->write('dirname/file.txt', 'contents', new Config())
        );
        $this->await(
            $this->adapter->write('dirname/other.txt', 'contents', new Config())
        );
        $contents = $this->await(
            $this->adapter->listContents('', true)
        );
        $this->assertCount(3, $contents);
    }

    public function testGetSize()
    {
        $this->await(
            $this->adapter->write('dummy.txt', '1234', new Config())
        );

        $result = $this->await($this->adapter->getSize('dummy.txt'));

        $this->assertIsArray($result);
        $this->assertArrayHasKey('size', $result);
        $this->assertEquals(4, $result['size']);
    }

    public function testGetTimestamp()
    {
        $this->await(
            $this->adapter->write('dummy.txt', '1234', new Config())
        );
        $result = $this->await($this->adapter->getTimestamp('dummy.txt'));
        $this->assertIsArray($result);
        $this->assertArrayHasKey('timestamp', $result);
        $this->assertIsInt($result['timestamp']);
    }

    public function testGetMimetype()
    {
        $this->await(
            $this->adapter->write('text.txt', 'contents', new Config())
        );

        $result = $this->await($this->adapter->getMimetype('text.txt'));
        $this->assertIsArray($result);
        $this->assertArrayHasKey('mimetype', $result);
        $this->assertEquals('text/plain', $result['mimetype']);
    }

    public function testCreateDirFail()
    {
        $this->assertFalse(
            $this->await(
                $this->adapter->createDir('', new Config())
            )
        );
    }

    public function testCreateDirDefaultVisibility()
    {
        $this->await($this->adapter->createDir('test-dir', new Config()));
        $output = $this->await($this->adapter->getVisibility('test-dir'));
        $this->assertIsArray($output);
        $this->assertArrayHasKey('visibility', $output);
        $this->assertEquals('public', $output['visibility']);
    }

    public function testDeleteDir()
    {
        $this->await(
            $this->adapter->write('nested/dir/path.txt', 'contents', new Config())
        );

        $this->assertTrue(is_dir(__DIR__ . '/files/nested/dir'));

        $this->await($this->adapter->deleteDir('nested'));

        $this->assertFalse(
            $this->await($this->adapter->has('nested/dir/path.txt'))
        );

        clearstatcache();

        $this->assertFalse(is_dir(__DIR__ . '/files/nested/dir'));
    }

    public function testVisibilityPublicFile()
    {
        if (IS_WINDOWS) {
            $this->markTestSkipped("Visibility not supported on Windows.");
        }

        $this->await($this->adapter->write('path.txt', 'contents', new Config()));
        $this->await($this->adapter->setVisibility('path.txt', 'public'));
        $output = $this->await($this->adapter->getVisibility('path.txt'));
        $this->assertIsArray($output);
        $this->assertArrayHasKey('visibility', $output);
        $this->assertEquals('public', $output['visibility']);

        $this->assertEquals("0644", substr(sprintf('%o', fileperms($this->root . 'path.txt')), -4));
    }

    public function testVisibilityPublicDir()
    {
        if (IS_WINDOWS) {
            $this->markTestSkipped("Visibility not supported on Windows.");
        }

        $this->await($this->adapter->createDir('public-dir', new Config()));
        $this->await(
            $this->adapter->setVisibility('public-dir', 'public')
        );
        $output = $this->await(
            $this->adapter->getVisibility('public-dir')
        );
        $this->assertIsArray($output);
        $this->assertArrayHasKey('visibility', $output);
        $this->assertEquals('public', $output['visibility']);
    }

    public function testVisibilityPrivateFile()
    {
        if (IS_WINDOWS) {
            $this->markTestSkipped("Visibility not supported on Windows.");
        }

        $this->await(
            $this->adapter->write('path.txt', 'contents', new Config())
        );
        $this->await(
            $this->adapter->setVisibility('path.txt', 'private')
        );
        $output = $this->await(
            $this->adapter->getVisibility('path.txt')
        );

        $this->assertIsArray($output);
        $this->assertArrayHasKey('visibility', $output);
        $this->assertEquals('private', $output['visibility']);
        $this->assertEquals("0600", substr(sprintf('%o', fileperms($this->root . 'path.txt')), -4));
    }

    public function testVisibilityPrivateDir()
    {
        if (IS_WINDOWS) {
            $this->markTestSkipped("Visibility not supported on Windows.");
        }

        $this->await(
            $this->adapter->createDir('private-dir', new Config())
        );
        $this->await(
            $this->adapter->setVisibility('private-dir', 'private')
        );
        $output = $this->await(
            $this->adapter->getVisibility('private-dir')
        );
        $this->assertIsArray($output);
        $this->assertArrayHasKey('visibility', $output);
        $this->assertEquals('private', $output['visibility']);
    }

    public function testVisibilityFail()
    {
        $this->assertFalse(
            $this->await(
                $this->adapter->setVisibility('chmod.fail', 'public')
            )
        );
    }

    public function testUnknownVisibility()
    {
        if (IS_WINDOWS) {
            $this->markTestSkipped("Visibility not supported on Windows.");
        }

        $umask = umask(0);
        mkdir($this->root . 'subdir', 0750);
        umask($umask);

        $output = $this->await($this->adapter->getVisibility('subdir'));

        $this->assertNotEquals('private', $output['visibility']);  // private is 0700 not 0750
        $this->assertNotEquals('public', $output['visibility']);  // public is 0755 not 0750
        $this->assertEquals('0750', $output['visibility']);
    }

    public function testCustomizedVisibility()
    {
        if (IS_WINDOWS) {
            $this->markTestSkipped("Visibility not supported on Windows.");
        }

        // override a permission mapping
        $permissions = [
            'dir' => [
                'private' => 0770,  // private to me and the gang
            ],
        ];

        $adapter = new Local($this->loop, $this->root, LOCK_EX, Local::DISALLOW_LINKS, $permissions);

        $this->await($adapter->createDir('private-dir', new Config()));
        $this->await($adapter->setVisibility('private-dir', 'private'));

        $output = $this->await($adapter->getVisibility('private-dir'));

        $this->assertEquals('private', $output['visibility']);
        $this->assertEquals('0770', substr(sprintf('%o', fileperms($this->root . 'private-dir')), -4));
    }

    public function testCustomVisibility()
    {
        if (IS_WINDOWS) {
            $this->markTestSkipped("Visibility not supported on Windows.");
        }

        // add a permission mapping
        $permissions = [
            'dir' => [
                'yolo' => 0777,
            ],
        ];

        $adapter = new Local($this->loop, $this->root, LOCK_EX, Local::DISALLOW_LINKS, $permissions);

        $this->await($adapter->createDir('yolo-dir', new Config()));
        $this->await($adapter->setVisibility('yolo-dir', 'yolo'));

        $location = $this->root . 'yolo-dir';

        $output = $this->await($adapter->getVisibility('yolo-dir'));
        $this->assertEquals('yolo', $output['visibility']);
        $this->assertEquals('0777', substr(sprintf('%o', fileperms($location)), -4));
    }

    public function testFirstVisibilityOctet()
    {
        if (IS_WINDOWS) {
            $this->markTestSkipped("Visibility not supported on Windows.");
        }

        $permissions = [
            'file' => [
                'public' => 0644,
                'private' => 0600,
            ],
            'dir' => [
                'sticky' => 01777,
                'public' => 0755,
                'private' => 0700,
            ],
        ];

        $adapter = new Local($this->loop, $this->root, LOCK_EX, Local::DISALLOW_LINKS, $permissions);

        $this->await($adapter->createDir('sticky-dir', new Config()));
        $this->await($adapter->setVisibility('sticky-dir', 'sticky'));

        $output = $this->await($adapter->getVisibility('sticky-dir'));
        $this->assertEquals('sticky', $output['visibility']);
        $this->assertEquals('1777', substr(sprintf('%o', fileperms($this->root . 'sticky-dir')), -4));
    }

    public function testApplyPathPrefix()
    {
        $this->adapter->setPathPrefix('');
        $this->assertEquals('', $this->adapter->applyPathPrefix(''));
    }

    public function testConstructorWithLink()
    {
        if (IS_WINDOWS) {
            $this->markTestSkipped("File permissions not supported on Windows.");
        }

        $target = $this->root;
        $link = __DIR__ . DIRECTORY_SEPARATOR . 'link_to_files';
        symlink($target, $link);

        $adapter = new Local($this->loop, $link);
        $this->assertEquals($target, $adapter->getPathPrefix());
        unlink($link);
    }

    /**
     * @expectedException \League\Flysystem\NotSupportedException
     */
    public function testLinkCausedUnsupportedException()
    {
        $this->expectException(NotSupportedException::class);
        $original = $this->root . 'original.txt';
        $link = $this->root . 'link.txt';
        file_put_contents($original, 'something');
        symlink($original, $link);
        $this->await($this->adapter->listContents());
    }

    public function testLinkIsSkipped()
    {
        $original = $this->root . 'original.txt';
        $link = $this->root . 'link.txt';

        file_put_contents($original, 'something');

        symlink($original, $link);

        $adapter = new Local($this->loop, $this->root, LOCK_EX, Local::SKIP_LINKS);
        $result = $this->await($adapter->listContents());

        $this->assertCount(1, $result);
    }

    public function testLinksAreDeletedDuringDeleteDir()
    {
        mkdir($this->root . 'subdir', 0777, true);
        $original = $this->root . 'original.txt';
        $link = $this->root . 'subdir/link.txt';
        file_put_contents($original, 'something');
        symlink($original, $link);
        $adapter = new Local($this->loop, $this->root, LOCK_EX, Local::SKIP_LINKS);

        $this->assertTrue(is_link($link));

        $this->await(
            $adapter->deleteDir('subdir')
        );

        clearstatcache();

        $this->assertFalse(is_link($link));
    }

    public function testUnreadableFilesCauseAnError()
    {
        $this->expectException('League\Flysystem\UnreadableFileException');

        $adapter = new Local($this->loop, $this->root, LOCK_EX, Local::SKIP_LINKS);
        $reflection = new ReflectionClass($adapter);
        $method = $reflection->getMethod('guardAgainstUnreadableFileInfo');
        $method->setAccessible(true);
        /** @var ExtendedFileInterface $fileInfo */
        $fileInfo = $this->prophesize(ExtendedFileInterface::class);
        $fileInfo->getPath()->willReturn('somewhere');
        $fileInfo->isReadable()->willReturn(resolve(false));

        $this->await($method->invoke($adapter, $fileInfo->reveal()));
    }

    public function testMimetypeFallbackOnExtension()
    {
        $this->await($this->adapter->write('test.xlsx', '', new Config()));
        $this->assertEquals(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $this->await($this->adapter->getMimetype('test.xlsx'))['mimetype']
        );
    }

    public function testDeleteFileShouldReturnTrue()
    {
        $original = $this->root . 'delete.txt';
        file_put_contents($original, 'something');
        $this->assertTrue($this->await($this->adapter->delete('delete.txt')));
    }

    public function testDeleteMissingFileShouldReturnFalse()
    {
        $this->assertFalse($this->await($this->adapter->delete('missing.txt')));
    }


    public function testRootDirectoryCreationProblemCausesAnError()
    {
        $this->markTestSkipped("Test unsupported for now");
        $this->expectException(Exception::class);

        $root = $this->root . 'fail.plz';
        new Local($this->loop, $root);
    }
}