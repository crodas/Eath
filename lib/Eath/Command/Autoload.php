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
namespace Eath\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;


class Autoload extends BaseApp
{
    protected function definition()
    {
       $this->setDescription("Build autoloader for the current project");
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $package = $this->env->get('localPackage');
        $files = array($package->getFiles());
        foreach ($package->getDeps(true) as $dep) {
            if ($dep->isLocal()) {
                $files[] = $dep->getFiles();
            }
        }
        $files = call_user_func_array('array_merge', $files);

        $output->writeLn('<info>Building autoloader</info>');

        $dir = $this->env->getLocalPath();
        $autoloader = new \Autoloader\Generator;
        $autoloader->multipleFiles()
            ->relativePaths()
            ->multipleFiles()
            ->setPathCallback(function($class, $prefix) use ($dir) {
                if (!is_dir($dir .  "/.autoloader/")) {
                    mkdir("{$dir}/.autoloader");
                }
                $file = trim(str_replace("\\", "-", $class), "-");
                return "{$dir}/.autoloader/{$file}.php";
            })
            ->setScanPath($files);

        $autoloader->generate( $this->env->getBootstrap(), $this->env->getInstallPath() . 'autoload.cache' );

        $includePath = $package->getInfo('include_path');
        if ($includePath) {
            $path = "";
            foreach ((array)$includePath as $package) {
                $pdir  = explode(DIRECTORY_SEPARATOR, $this->env->get('Package', $package)->getInstalledPath());
                $path .= '__DIR__ . ' .var_export(end($pdir), true)  . ' . PATH_SEPARATOR .';
            }
            file_put_contents($this->env->getBootstrap(), "set_include_path($path get_include_path());", FILE_APPEND | LOCK_EX);
        }



        if ($this->env->isGlobal()) {
            $files = array();
            foreach ($this->env->get('Packages')->getAll() as $dep) {
                if (!$dep->isLocal()) {
                    $files[] = $dep->getFiles();
                }
            }
            
            $files = call_user_func_array('array_merge', $files);

            if (count($files) == 0) {
                return;
            }

            $output->writeLn('<info>Building global autoloader</info>');

            $dir = $this->env->getGlobalPath();
            $autoloader->setScanPath($files);
            $autoloader->generate($this->env->getBootstrap(true), $path . 'autoload.cache');
        }
    } 
}
