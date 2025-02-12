<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Flex\Tests\Configurator;

use Composer\Composer;
use Composer\Installer\InstallationManager;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Flex\Configurator\CopyFromPackageConfigurator;
use Symfony\Flex\Lock;
use Symfony\Flex\Options;
use Symfony\Flex\Recipe;

class CopyDirectoryFromPackageConfiguratorTest extends TestCase
{
    private $sourceFiles = [];
    private $sourceDirectory;
    private $sourceFileRelativePath;
    private $targetFiles = [];
    private $targetFileRelativePath;
    private $targetDirectory;
    private $io;
    private $recipe;

    public function testConfigureDirectory()
    {
        if (!is_dir($this->sourceDirectory)) {
            mkdir($this->sourceDirectory, 0777, true);
        }
        foreach ($this->sourceFiles as $sourceFile) {
            if (!file_exists($sourceFile)) {
                file_put_contents($sourceFile, '');
            }
        }

        foreach ($this->targetFiles as $targetFile) {
            $this->assertFileDoesNotExist($targetFile);
        }
        $lock = $this->getMockBuilder(Lock::class)->disableOriginalConstructor()->getMock();
        $this->createConfigurator()->configure($this->recipe, [
            $this->sourceFileRelativePath => $this->targetFileRelativePath,
        ], $lock);
        foreach ($this->targetFiles as $targetFile) {
            $this->assertFileExists($targetFile);
        }
    }

    /**
     * @dataProvider providerTestConfigureDirectoryWithExistingFiles
     */
    public function testConfigureDirectoryWithExistingFiles(bool $force, string $sourceFileContent, string $existingTargetFileContent, string $expectedFinalTargetFileContent)
    {
        if (!is_dir($this->sourceDirectory)) {
            mkdir($this->sourceDirectory, 0777, true);
        }
        foreach ($this->sourceFiles as $sourceFile) {
            if (!file_exists($sourceFile)) {
                file_put_contents($sourceFile, $sourceFileContent);
            }
        }

        if (!is_dir($this->targetDirectory)) {
            mkdir($this->targetDirectory, 0777, true);
        }

        foreach ($this->targetFiles as $targetFile) {
            file_put_contents($targetFile, $existingTargetFileContent);
        }

        $this->createConfigurator()->configure(
            $this->recipe,
            [$this->sourceFileRelativePath => $this->targetFileRelativePath],
            $this->getMockBuilder(Lock::class)->disableOriginalConstructor()->getMock(),
            ['force' => $force]
        );

        foreach ($this->targetFiles as $targetFile) {
            $this->assertFileExists($targetFile);
            $content = file_get_contents($targetFile);
            $this->assertEquals($expectedFinalTargetFileContent, $content);
        }
    }

    public function providerTestConfigureDirectoryWithExistingFiles(): array
    {
        return [
            [true, 'NEW_CONTENT', 'OLD_CONTENT', 'NEW_CONTENT'],
            [false, 'NEW_CONTENT', 'OLD_CONTENT', 'OLD_CONTENT'],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->sourceDirectory = FLEX_TEST_DIR.'/package/files';
        $this->sourceFileRelativePath = 'package/files/';
        $this->sourceFiles = [
            $this->sourceDirectory.'/file1',
            $this->sourceDirectory.'/file2',
        ];

        $this->targetDirectory = FLEX_TEST_DIR.'/public/files';
        $this->targetFileRelativePath = 'public/files/';
        $this->targetFiles = [
            $this->targetDirectory.'/file1',
            $this->targetDirectory.'/file2',
        ];

        $this->io = $this->getMockBuilder(IOInterface::class)->getMock();
        $this->io->method('askConfirmation')->willReturn(true);

        $package = $this->getMockBuilder(PackageInterface::class)->getMock();
        $this->recipe = $this->getMockBuilder(Recipe::class)->disableOriginalConstructor()->getMock();
        $this->recipe->expects($this->exactly(1))->method('getPackage')->willReturn($package);

        $installationManager = $this->getMockBuilder(InstallationManager::class)->disableOriginalConstructor()->getMock();
        $installationManager->expects($this->exactly(1))
            ->method('getInstallPath')
            ->with($package)
            ->willReturn(FLEX_TEST_DIR)
        ;
        $this->composer = $this->getMockBuilder(Composer::class)->getMock();
        $this->composer->expects($this->exactly(1))
            ->method('getInstallationManager')
            ->willReturn($installationManager)
        ;

        $this->cleanUpTargetFiles();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        foreach ($this->sourceFiles as $sourceFile) {
            @unlink($sourceFile);
        }
        $this->cleanUpTargetFiles();
    }

    private function createConfigurator(): CopyFromPackageConfigurator
    {
        return new CopyFromPackageConfigurator($this->composer, $this->io, new Options(['root-dir' => FLEX_TEST_DIR], $this->io));
    }

    private function cleanUpTargetFiles()
    {
        $this->rrmdir(FLEX_TEST_DIR.'/package');
        $this->rrmdir(FLEX_TEST_DIR.'/public');
    }

    /**
     * Courtesy of http://php.net/manual/en/function.rmdir.php#98622.
     */
    private function rrmdir($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ('.' !== $object && '..' !== $object) {
                    if ('dir' == filetype($dir.'/'.$object)) {
                        $this->rrmdir($dir.'/'.$object);
                    } else {
                        unlink($dir.'/'.$object);
                    }
                }
            }
            reset($objects);
            rmdir($dir);
        }
    }
}
