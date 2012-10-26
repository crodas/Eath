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
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Parser;


abstract class Package extends BaseClass
{
    protected $path;
    protected $isLocal      = true;
    protected $info         = array();
    protected $folderName   = NULL;

    public static function getInstance($env, $url, Package $prevInstalled = NULL)
    {
        $installed = $prevInstalled;
        if (is_dir($url)) {
            $finder = new Finder();
            $finder->files()->in($url)
                ->name('config.m4')
                ->depth('< 2');

            if (count($finder) == 1) {
                $configm4 = iterator_to_array($finder);
                $obj = new Package\Extension($url, current($configm4));
            } else if (count($finder) > 1) {
                throw new \RuntimeException("Only one config.m4 is allowed per package");
            } else {
                $obj = new Package\Local($url);
            }
        } else {
            $installed = $env->get('Packages')->get($url) ?: $installed;

            if (strpos($url, ':') !== false) {
                list($handler, ) = explode(':', $url, 2);
                $class = 'Eath\\Package\\' . ucfirst($handler);
                $obj = new $class($url);
            } else if (is_file($url)) {
                $obj = new Package\Archive($url);
            } else if (strpos($url, 'ext-') === 0) {
                $obj = new Package\Http("http://pecl.php.net/get/" . substr($url, 4));
            } else {
                throw new \RuntimeException("Don't know how to treat package");
            }
        }

        $obj->setEnvironment($env);
        $return = $obj->init($installed);
        if ($return instanceof self) {
            return $return;
        }

        return $obj;
    }

    public function __construct($path)
    {
        $this->path = $path;
    }

    public function setUrl($url)
    {
        $this->info['source'] = $url;
        return $this;
    }

    public function setVersion($version)
    {
        $this->info['version'] = $version;
        return $this;
    }

    public function setInfo($name, $value)
    {
        $this->info[$name] = $value;
        return $this;
    }

    public function getInfo($name)
    {
        if (array_key_exists($name, $this->info)) {
            return $this->info[$name];
        }
        return NULL;
    }


    public function setName($name, $force = false)
    {
        if (empty($this->info['name']) || $force) {
            $this->info['name'] = $name;
        }
        return $this;
    }

    public function setFolderName($name)
    {
        $this->folderName = $name;
        return $this;
    }

    public function getFolderName()
    {
        if (!empty($this->folderName)) {
            $dir = $this->folderName;
        } else {
            $dir = $this->getName();
        }

        return trim(str_replace(array('\\', '/'), '_', $dir), '_');
    }

    public function loadPackageInfo($data)
    {
        if (!is_array($data)) {
            if (is_file($data)) {
                $data = Yaml::parse($data);
            } else {
                $parser = new Parser();
                $data   = $parser->parse($data);
            }
        }

        $this->info = array_merge($this->info, $data);
    }

    public function getAuthor()
    {
        if (empty($this->info['author'])) {
            return array();
        }
        return $this->info['author'];
    }

    public function getSource()
    {
        return $this->info['source'];
    }


    public function getName()
    {
        $name = $this->info['name'];
        if (empty($name)) {
            throw new \RuntimeException("Package has no name");
        }
        return $name;
    }

    public function getPath()
    {
        return $this->dir;
    }

    public function getBins()
    {
        if (empty($this->info['bin'])) {
            return array();
        }
        $bins = array();
        return (array)$this->info['bin'];
    }

    public function getVersion()
    {
        if (empty($this->info['version'])) {
            return 'dev';
        }
        return $this->info['version'];
    }

    public function processDependencies()
    {
        if (empty($this->info['dependencies'])) {
            return false;
        }

        $packages     = $this->env->get('Packages');
        $hasInstalled = false;
        foreach ($this->info['dependencies'] as $dep) {
            $this->env->get('Package', $dep)
                ->install();
            $hasInstalled = true;
        }
        return $hasInstalled;
    }

    public function buildPackageInfo()
    {
        if (empty($this->info['source'])) {
            throw new \RuntimeException("Cannot install a package without a source or a name");
        }

        $this->info['installed_files'] = array();
        foreach ($this->getFiles() as $file) {
            $this->info['installed_files'][$file->getRelativePathName()] = sha1_file($file);
        }

        $this->updatePackageInfo();
    }
    
    abstract public function init($installed);

    abstract public function getDeps($install = false);

    abstract public function isLocal();
    
    abstract public function getFiles();

    abstract public function updatePackageInfo();
    
    abstract public function install();

    public function __toString()
    {
        return $this->getSource() . '@' . $this->getVersion();
    }
}
