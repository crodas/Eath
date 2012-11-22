<?php
/*
  +---------------------------------------------------------------------------------+
  | Copyright (c) 2012 César Rodas                                                  |
  +---------------------------------------------------------------------------------+
  | Redistribution and use in source and binary forms, with or without              |
  | modification, are permitted provided that the following conditions are met:     |
  | 1. Redistributions of source code must retain the above copyright               |
  |    notice, this list of conditions and the following disclaimer.                |
  |                                                                                 |
  | 2. Redistributions in binary form must reproduce the above copyright            |
  |    notice, this list of conditions and the following disclaimer in the          |
  |    documentation and/or other materials provided with the distribution.         |
  |                                                                                 |
  | 3. All advertising materials mentioning features or use of this software        |
  |    must display the following acknowledgement:                                  |
  |    This product includes software developed by César D. Rodas.                  |
  |                                                                                 |
  | 4. Neither the name of the César D. Rodas nor the                               |
  |    names of its contributors may be used to endorse or promote products         |
  |    derived from this software without specific prior written permission.        |
  |                                                                                 |
  | THIS SOFTWARE IS PROVIDED BY CÉSAR D. RODAS ''AS IS'' AND ANY                   |
  | EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED       |
  | WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE          |
  | DISCLAIMED. IN NO EVENT SHALL CÉSAR D. RODAS BE LIABLE FOR ANY                  |
  | DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES      |
  | (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;    |
  | LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND     |
  | ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT      |
  | (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS   |
  | SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE                     |
  +---------------------------------------------------------------------------------+
  | Authors: César Rodas <crodas@php.net>                                           |
  +---------------------------------------------------------------------------------+
*/
namespace Eath\Package;

use Symfony\Component\Process\Process;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Dumper;


class Extension extends Dummy
{
    protected $configm4 = NULL;

    protected function getMacroValue($macro)
    {
        $finder = new Finder();
        $finder->name('*.h')
            ->name('*.c')
            ->in($this->dir)
            ->files();

        foreach ($finder as $file) {
            $content = file_get_contents((string)$file);
            preg_match("/{$macro}\W+\"([^\"]+)/i", $content, $matches);
            if (count($matches) > 0) {
                return $matches[1];
            }
        }
        return NULL;
    }

    protected function readExtensionVersion($name)
    {
        $version = $this->getMacroValue("php_{$name}_version");
        if (empty($version)) {
            $finder = new Finder();
            $finder->name('*.h')
                ->name('*.c')
                ->in($this->dir)
                ->files();

            foreach ($finder as $file) {
                $content = file_get_contents((string)$file);
                preg_match('/zend_module_entry[^;{]+{([^}]+)/sm', $content, $matches);
                if (!empty($matches)) {
                    $code = preg_replace('@/\*([^\*]+\*/)+@', '', $matches[1]);
                    $args = array_map('trim', explode(",", $code));

                    if ($args[0] == 'STANDARD_MODULE_HEADER') {
                        $version = $args[8];
                    } else {
                        foreach ($args as $id => $arg) {
                            if (stripos($arg, 'minfo')) {
                                $version = $args[$id+1];
                            }
                        }
                    }

                    if (!empty($version)) {
                        if ($version[0] == '"') {
                            $version = trim($version, '"');
                        } else {
                            if (substr(trim($version), 0, 3) == '#if') {
                                list(, $version, ) = explode("\n", $version, 3);
                            }
                            $version = $this->getMacroValue(trim($version));
                        }
                    }
                }
            }
            if (empty($version)) {
                throw new \RuntimeException("Cannot guess package version. Can't find PHP_" . strtoupper($name) . "_VERSION macro");
            }
        }
        return $version;
    }

    public function __construct($dir, $configm4, $source = '', $name = '')
    {
        $this->dir      = $dir;
        $this->source   = $source;
        $this->configm4 = $configm4;

        $m4 = file_get_contents($this->configm4);
        if (preg_match('@PHP_NEW_EXTENSION\\(([^,]+),@', $m4, $matches) === 0) {
            throw new \RuntimeException("Cannot guess exception name");
        }

        $name = trim($matches[1]);
        $version = $this->readExtensionVersion($name);


        $this->info = compact('name', 'version', 'source');
    }

    public function updatePackageInfo()
    {
        $dumper = new Dumper;
        $info   = $this->info;
        if (empty($info['version'])) {
            $info['version'] = $this->getVersion();
        }

        if (empty($info['name'])) {
            $info['name'] = $this->getName();
        }

        $yaml = $dumper->dump($info, 3);
        file_put_contents($this->path . '/package.yml', $yaml);
    }

    public function install()
    {
        $installed = phpversion($this->getName());
        if ($installed && version_compare($installed, $this->getVersion(), '>=')) {
            // nothing to install
            return false;
        }

        $config  = $this->configm4;
        $print   = $this->env->getOutput();

        $print->writeLn("<info>\tBuilding</info>");
        $process = new Process('/usr/bin/phpize', dirname($config));
        $process->run(function($type, $output) {
            file_put_contents('php://std' . $type, $output);
        });


        if (!$process->isSuccessful()) {
            throw new \RuntimeException("phpize execution failed");
        }

        $print->writeLn("<info>\tConfiguring</info>");
        $process = new Process('./configure', dirname($config));
        $process->run(function($type, $output) {
            file_put_contents('php://std' . $type, $output);
        });

        if (!$process->isSuccessful()) {
            throw new \RuntimeException("./configure failed");
        }

        $print->writeLn("<info>\tCompiling</info>");
        $process = new Process('make install', dirname($config));
        $process->run(function($type, $output) {
            file_put_contents('php://std' . $type, $output);
        });

        if (!$process->isSuccessful()) {
            throw new \RuntimeException("make install failed");
        }

        $this->env->ini_set('extension', $this->getName() . '.' . PHP_SHLIB_SUFFIX, $this->getName());
        $print->writeLn("<info>\tInstalled</info>");

        // copying package info to avoid redownloading
        $pwd  = $this->env->getGlobalPath();
        $dest = $pwd . $this->getFolderName();

        die($dest);

        // done :-)
        return true;
    }

    public function isLocal()
    {
        // extensions are never ever local
        return false;
    }

}
