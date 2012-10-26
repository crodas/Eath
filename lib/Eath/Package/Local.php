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

use Eath\Package;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Dumper;

class Local extends Package
{
    protected $newest;

    public function __construct($dir)
    {
        parent::__construct($dir);
        $finder = new Finder();
        $finder->files()->in($this->path)
            ->name('package.yml')
            ->depth(0);

        $this->info = array('name' => '', 'version' => 'dev', 'source' => $this->path);
        foreach ($finder as $package) {
            $this->loadPackageInfo($package);
        }
    }

    public function init($installed)
    {
        $this->newest = !$installed || version_compare($installed->getVersion(), $this->getVersion(), '<');
    }

    public function getDeps($install = false)
    {
        if (empty($this->info['dependencies'])) {
            return array();
        }

        $pkgs =  $this->env->get('Packages');
        $all  = array();
        foreach ($pkgs->getAll($this->info['dependencies'], $install)  as $dep) {
            $all[] = $dep;
            $all   = array_merge($all, $dep->getDeps($install));
        }

        return array_unique($all);
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

    public function getFiles()
    {
        if (empty($this->info['files'])) {
            $finder = new Finder();
            return iterator_to_array($finder->files()->in($this->path));
        }

        $files  = array();

        foreach (array_merge(array('package.yml'), $this->info['files']) as $file) {
            if (!file_exists($this->path . DIRECTORY_SEPARATOR . $file)) {
                throw new \RuntimeException("Cannot find file/directory {$file}");
            }
            $path = $this->path . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $finder = new Finder;
                $finder->files()->in($path);
                foreach ($finder as $file) {
                    $rel = substr($file, strlen($this->path));
                    if ($rel[0] == DIRECTORY_SEPARATOR) {
                        $rel = substr($rel, 1);
                    }
                    $files[$rel] = new SplFileInfo($file, dirname($rel), $rel);
                }
            } else {
                $files[$file] = new SplFileInfo($path, dirname($file), $file);
            }
        }

        return $files;
    }

    public function isLocal()
    {
        return strpos($this->path, $this->env->getLocalPath()) === 0;
    }

    /**
     *  Install the current package either locally or globally 
     *  (depends on what Env tells us to do)
     */
    public function install()
    { 
        if (!$this->newest) {
            // nothing to install
            return false;
        }

        $env   = $this->env;
        $fs    = $this->env->get('fs');
        $this->buildPackageInfo();
        $this->processDependencies();

        $pwd  = $env->isGlobal() ? $env->getGlobalPath() : $env->getLocalPath();
        $dest = $pwd . $this->getFolderName();

        if (is_dir($dest)) {
            if (empty($installed)) {
                $fs->remove($dest);
            } else {
                $oldFiles = array_keys($installed->getFiles());
                $newFiles = array_keys($this->getFiles());

                $toDelete = array_map(function($path) use ($dest) {
                    return $dest . DIRECTORY_SEPARATOR . $path;
                }, array_diff($oldFiles, $newFiles));
            
                if (count($toDelete) > 0) {
                    $fs->remove($toDelete);
                }
            }

        } else {
            $fs->mkdir($dest);
        }

        foreach ($this->getFiles() as $file) {
            $fs->copy((string)$file, $dest . DIRECTORY_SEPARATOR . $file->getRelativePathName());
        }

        $this->env->get('Packages')->register($dest);
        return true;
    }


}
