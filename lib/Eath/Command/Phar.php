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
use Symfony\Component\Process\Process;
use Phar as PharClass;

class Phar extends BaseApp
{
    protected function definition()
    {
       $this->setDescription("Creates a phar files for each bin entry");
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $pwd = $this->env->getcwd() . '/';
        $package = $this->env->get('Package', $pwd);

        $phars = array();

        if (ini_get('phar.readonly')) {
            // re-spawn another PHP process disabling phar.readonly
            $args = array_merge(array('-d', 'phar.readonly=0'), $GLOBALS['argv']);
            $process = new Process($_SERVER['_'] . ' ' . implode(' ', $args));
            $process->run(function($type, $buffer) {
                echo $buffer;
            });
            return array();
        }

        foreach ($package->getBins() as $cmd => $entryPoint) {
            $dest = $pwd . $cmd . '.phar';
            @unlink($dest);
            $phar = new PharClass($dest, 0);
            $output->writeLn("<info>Generating {$dest}</info>");

            $dir = $this->env->getTempDir();
            $fs  = $this->env->get('fs');

            $output->writeLn("<info>\tCopying source code</info>");
            foreach ($package->getFiles() as $file) {
                $fs->copy($file, $dir . $file->getRelativePathname());
            }

            $output->writeLn("<info>\tPacking deps packages</info>");
            foreach ($package->getDeps(true) as $pkg) {
                foreach ($pkg->getFiles() as $file) {
                    $fs->copy($file, $dir . 'packages/' . $pkg->getName() . '/' . $file->getRelativePathname());
                }
            }

            $output->writeLn("<info>\tGenerating autoloader</info>");
            $autoload = new \Autoloader\Generator($dir);
            $autoload->relativePaths()->generate($dir . 'packages/autoload.php');

            $phar->buildFromDirectory($dir);
            $phar->addFile($entryPoint, $entryPoint);
            $phar->setStub("#!/usr/bin/env php\n"
                . $phar->createDefaultStub($entryPoint)
            );

            $phars[] = $dest;
        }
        return $phars;
    }
}
