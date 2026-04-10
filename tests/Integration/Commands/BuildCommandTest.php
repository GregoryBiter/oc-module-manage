<?php

namespace Tests\Integration\Commands;

use Ocm\Commands\BuildCommand;
use Ocm\Base\Input;

class BuildCommandTest extends CommandTestCase {
    public function testHandleBuildsModuleFromPatterns() {
        // Create a fake module structure
        mkdir($this->testDir . '/admin/controller', 0777, true);
        file_put_contents($this->testDir . '/admin/controller/test.php', '<?php // admin');
        mkdir($this->testDir . '/catalog/view', 0777, true);
        file_put_contents($this->testDir . '/catalog/view/test.twig', '{{ catalog }}');

        // Create .build-module file
        file_put_contents($this->testDir . '/.build-module', "admin/controller/*.php\ncatalog/view/*.twig");

        $command = new BuildCommand();
        $command->setApplication($this->app);
        
        $input = new Input(['ocm', 'build']);
        $command->handle($input, $this->output);

        $buildUploadDir = $this->testDir . '/build-module/upload';
        $this->assertTrue(file_exists($buildUploadDir . '/admin/controller/test.php'));
        $this->assertTrue(file_exists($buildUploadDir . '/catalog/view/test.twig'));
    }
}
