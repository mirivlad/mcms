<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use MCMS\Core\Packages\ZipPackage;

final class ZipPackageTest extends CIUnitTestCase
{
    public function testImplicitDirectoriesCannotHaveConflictingCase(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'mcms-zip-');
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('theme.json', '{"id":"probe"}');
        $zip->addFromString('Shared/first.php', '<?php');
        $zip->addFromString('shared/second.php', '<?php');
        $zip->close();
        try {
            $this->expectExceptionMessage('Case-conflicting archive directory or file');
            ZipPackage::inspectArchive($path, 'theme.json');
        } finally {
            unlink($path);
        }
    }
}
