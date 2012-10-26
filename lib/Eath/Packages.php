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
namespace Eath;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;

/**
 *  Packages
 *
 *  This class manages all the installed classes, either locally
 *  or globally.
 */
class Packages extends BaseClass
{
    protected $global;
    protected $local;

    public function init()
    {
        $env    = $this->env;
        $finder = new Finder();
        $finder->files()
            ->name('package.yml')
            ->depth(1);

        $hasPkgs = false;
        if (is_dir($dir = $env->getGlobalPath(false))) {
            $finder->in($dir);
            $hasPkgs = true;
        }

        if (is_dir($dir = $env->getLocalPath())) {
            $hasPkgs = true;
            $finder->in($dir);
        }

        $packages = array();
        if ($hasPkgs) {
            foreach ($finder as $file) {
                $pkg = $this->env->get('Package', dirname($file));
                $pkg->isLocal( strpos((string)$file, $env->getLocalPath()) === 0);
                $packages[] =  $pkg;
            }
        }
        $this->packages = $packages;
    }

    public function register($package)
    {
        $this->packages[] = $this->env->get('Package', $package);
    }

    public function getAll(Array $names = array(), $install = true)
    {
        if (empty($names)) {
            return $this->packages;
        }
        $packages = array();
        foreach ($names as $name) {
            $package = $this->get($name, !$install);
            if (!$package) {
                $package = $this->env->get('Package', $name);
                $package->install();
            }
            $packages[] = $package;
        }
        return $packages;
    }

    public function get($name, $throw = false)
    {
        foreach ($this->packages as $package) {
            if ($package->getName() === $name || $package->getSource() === $name) {
                return $package;
            }
        }

        if ($throw) {
            throw new \RuntimeException("Cannot find package {$name}. Try with install all dependencies");
        }
        return NULL;
    }

}
