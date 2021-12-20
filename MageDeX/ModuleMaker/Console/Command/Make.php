<?php

namespace MageDeX\ModuleMaker\Console\Command;

use Magento\Framework\Filesystem\DriverPool;
use Magento\Framework\Module\Dir\Reader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Directory\WriteFactory;

class Make extends Command
{
    const VENDOR_NAME_ARGUMENT = "vendor's name";
    const MODULE_NAME_ARGUMENT = "module's name";
    const AUTHOR_NAME_ARGUMENT = "author's name";

    /**
     * @var Reader
     */
    protected $moduleDirectory;

    /**
     * @var DirectoryList
     */
    protected $directoryList;

    /**
     * @var WriteFactory
     */
    protected $write;

    protected $rootPath;

    public function __construct(
        Reader $moduleDirectory,
        DirectoryList $directoryList,
        WriteFactory $write,
        string $name = null
    ) {
        parent::__construct($name);
        $this->directoryList = $directoryList;
        $this->moduleDirectory = $moduleDirectory;
        $this->write = $write;
        $this->rootPath = $this->directoryList->getRoot();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ) {
        $vendorName = $input->getArgument(self::VENDOR_NAME_ARGUMENT);
        $moduleName = $input->getArgument(self::MODULE_NAME_ARGUMENT);
        $authorName = $input->getArgument(self::AUTHOR_NAME_ARGUMENT);

        while (!$vendorName) {
            $output->writeln("What Vendor name for this new module?");
            $handle = fopen("php://stdin", "r");
            $vendorName = trim(fgets($handle));
        }

        $correctedVendorName = $this->cleanModuleName($vendorName);
        if ($correctedVendorName !== $vendorName) {
            $output->writeln("Vendor's name has been modified this way to comply with PSR: " . $correctedVendorName);
        }

        while (!$moduleName) {
            $output->writeln("What Module name?");
            $handle = fopen("php://stdin", "r");
            $moduleName = trim(fgets($handle));
        }

        $correctedModuleName = $this->cleanModuleName($moduleName);
        if ($correctedModuleName !== $vendorName) {
            $output->writeln("Vendor's name has been modified this way to comply with PSR: " . $correctedModuleName);
        }

        if (!$authorName) {
            $output->writeln("An author name for the copyright?");
            $handle = fopen("php://stdin", "r");
            $authorName = trim(fgets($handle));
        }

        $license = false;
        if ($authorName !== '') {
            $output->writeln("Add a MIT license file? [Y/n]");
            $handle = fopen("php://stdin", "r");
            $handle = trim(fgets($handle));
            switch (strtolower($handle)) {
                case "":
                case "y":
                case "yes":
                    $license = true;
                    break;
            }
        }

        if ($this->createModule($correctedVendorName, $correctedModuleName, $authorName, $license, $output)) {
            $output->writeln("Please welcome " . $correctedVendorName . "_" . $correctedModuleName . "!");
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName("modulemaker:make");
        $this->setDescription("Quickly create a module without messing with bothering stuffs");
        $this->setDefinition([
            new InputArgument(self::VENDOR_NAME_ARGUMENT, InputArgument::OPTIONAL, "Vendor's Name"),
            new InputArgument(self::MODULE_NAME_ARGUMENT, InputArgument::OPTIONAL, "Module's Name"),
            new InputArgument(self::AUTHOR_NAME_ARGUMENT, InputArgument::OPTIONAL, "author's name"),
        ]);
        parent::configure();
    }

    /**
     * Cleans Module Name
     *
     * @param string $value
     * @return string
     */
    private function cleanModuleName(string $value): string
    {
        $value = trim($value);
        $value = str_replace(['-', '_', '.', ':', '!'], ' ', $value);
        $value = preg_replace('/[^a-zA-Z]/', ' ', $value);
        $value = ucwords($value);
        $value = str_replace(' ', '', $value);
        $value = ucfirst($value);

        return $value;
    }

    /**
     * @param string $vendorName
     * @param string $moduleName
     * @return bool
     */
    private function createModule(
        string $vendorName,
        string $moduleName,
        string $authorName,
        bool $license,
        OutputInterface $output
    ) : bool {

        $vendorPath = $this->rootPath . '/app/code/' . $vendorName;
        $fullPath = $vendorPath . '/' . $moduleName;

        if($this->isModuleAlreadyExisting(
            $vendorPath,
            $fullPath,
            $output
        )) {
            // etc/module.xml
            $moduleXml = [
                'filename' => 'module.xml',
                'content' => '<?xml version="1.0"?>' . "\n" .
                             '<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'. "\n" .
                             '        xsi:noNamespaceSchemaLocation="urn:magento:framework:Module/etc/module.xsd">'. "\n" .
                             '    <module name="'. $vendorName . '_' . $moduleName . '" setup_version="0.0.1"/>'. "\n" .
                             '</config>' . "\n"
            ];

            $this->createFile($fullPath . '/' . \Magento\Framework\Module\Dir::MODULE_ETC_DIR, $moduleXml);
            $composerJson = [
                'filename' => 'composer.json',
                'content' => '{'."\n".
                             '    "name": "'.strtolower($vendorName).'/'. strtolower($moduleName).",',"."\n".
                             '    "description": "",'."\n".
                             '    "type": "magento2-module",'."\n".
                             '    "version": "0.1.0",'."\n".
                             '    "license": ['."\n".
                             ''."\n".
                             '    ],'."\n".
                             '    "autoload": {'."\n".
                             '        "files": ['."\n".
                             '            "registration.php"'."\n".
                             '        ],'."\n".
                             '        "psr-4": {'."\n".
                             '            "'. $vendorName .'\\'.$moduleName.'\\": ""'."\n".
                             '        }'."\n".
                             '    },'."\n".
                             '    "extra": {'."\n".
                             '        "map": ['."\n".
                             '            ['."\n".
                             '                "*",'."\n".
                             '                "'. $vendorName .'/'.$moduleName.'"'."\n".
                             '            ]'."\n".
                             '        ]'."\n".
                             '    }'."\n".
                             '}'
            ];

            $this->createFile($fullPath, $composerJson);

            if ($authorName && $authorName !== '') {
                $copyright = ' * Copyright © '. $authorName .'. All rights reserved.'."\n" .
                             ' * See COPYING.txt for license details.'."\n";
            }
            $registrationPhp = [
                'filename' => 'registration.php',
                'content' => '<?php'."\n".
                             '/**'."\n".
                             $copyright.
                             ' */'."\n".
                             ''."\n".
                             'use Magento\Framework\Component\ComponentRegistrar;'."\n".
                             ''."\n".
                             'ComponentRegistrar::register('."\n".
                             '    ComponentRegistrar::MODULE,'."\n".
                             '    \''. $vendorName .'_'. $moduleName.'\','."\n".
                             '    __DIR__'."\n".
                             ');'."\n".
                             ''."\n"
            ];

            $this->createFile($fullPath, $registrationPhp);

            if ($license) {
                $licenseMd = [
                    'filename' => 'license.md',
                    'content' => "The MIT License\n".
                    "\n".
                    "Copyright (c) " . date("Y") . " " . $authorName . "\n".
                    "\n".
                    "Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the \"Software\"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:\n".
                    "\n".
                    "The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.\n".
                    "\n".
                    "THE SOFTWARE IS PROVIDED \"AS IS\", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.\n".
                    ""
                ];
                $this->createFile($fullPath, $licenseMd);
            }

            $readMeMd = [
                'filename' => 'README.md',
                'content' => "# ". $moduleName."\n".
                             "\n".
                             "## Introductio\n".
                             $moduleName . " is a module for Magento 2. Enjoy !\n"
            ];

            $this->createFile($fullPath, $readMeMd);

            return true;
        } else {
            return false;
        }
    }

    /**
     * @param string $vendorPath
     * @param string $modulePath
     * @return bool
     */
    private function isModuleAlreadyExisting(
        string $vendorPath,
        string $fullPath,
        OutputInterface $output
    ) : bool {
        //Tester si le répertoire du vendor existe
        if (!file_exists($vendorPath)) {
            $output->writeln($vendorPath);
            mkdir($vendorPath);
        }

        //Tester si le répertoire du module existe => si c’est le cas retourner faux avec message d’erreur
        if (!file_exists($fullPath)) {
            mkdir($fullPath);
            mkdir($fullPath . '/' . \Magento\Framework\Module\Dir::MODULE_ETC_DIR);
        } else {
            $output->writeln('A module with the same name already exists');
            return false;
        }
        return true;
    }

    private function createFile(string $path, array $data) : bool
    {
        try {
            $newFile = $this->write->create($path ,DriverPool::FILE);
            $newFile->writeFile($data['filename'], $data['content']);
        } catch (\Exception $e) {
            echo $e->getMessage();
            return false;
        }

        return true;
    }
}
