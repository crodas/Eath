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

use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Filesystem\Filesystem;

class Environment
{
    protected $output;
    protected $pwd;
    protected $global = false;
    protected $dir    = 'packages';
    protected $singleton = array();
    protected $home;

    public function __construct()
    {
        $localPackage = $this->get('Package', getcwd());

        if ($packageDir = $localPackage->getInfo('packageDirectory')) {
            $this->setPackageDirectory($packageDir);
        }

        $this->singleton['fs'] = new Filesystem;
        $this->singleton['localPackage'] = $localPackage;

        $home = $_SERVER['HOME'];
        if (empty($home)) {
            throw new \RuntimeException("Cannot guest the home directory");
        }
        
        $home .= DIRECTORY_SEPARATOR . '.eath' . DIRECTORY_SEPARATOR;
        if (!is_dir($home)) {
            $this->get('fs')->mkdir($home);
        }

        $this->home = $home;
    }

    public function getHomePath()
    {
        return $this->home;
    }


    public function setOutput(ConsoleOutput $output)
    {
        $this->output = $output;
    }

    public function getOutput()
    {
        return $this->output;
    }

    public function getPHPBin()
    {
        return $_SERVER['_'];
    }

    public function setPackageDirectory($dir)
    {
        if (!preg_match('@^[a-z0-9_]+$@', $dir)) {
            throw new \RuntimeException("invalid package directory {$dir}");
        }
        $this->dir = $dir;
        return $this;
    }

    public function getPackageDirectory()
    {
        return $this->dir;
    }

    public function getGlobalPath($tryToCreate = true)
    {
        static $pwd = NULL;

        if (!empty($pwd)) {
            return $pwd;
        }

        if (DIRECTORY_SEPARATOR == '/') {
            $pwd = "/usr/local/eath/";
            if (!is_dir($pwd)) {
                if (!$tryToCreate) {
                    return NULL;
                }
                if (!is_writable(dirname($pwd))) {
                    throw new \RuntimeException("Cannot create global directory, are you root?");
                }
                mkdir($pwd);
            }
        } else {
            throw new \RuntimeException("Global is not working on windows yet");
        }
        
        return $pwd;
    }

    public function getLocalPath()
    {
       return getcwd() . DIRECTORY_SEPARATOR . $this->getPackageDirectory() . DIRECTORY_SEPARATOR;
    }

    public function setGlobal()
    {
        $this->global = true;
    }

    public function isGlobal()
    {
        return (boolean)$this->global;
    }

    public function get($class)
    {
        if (isset($this->singleton[$class])) {
            return $this->singleton[$class];
        }
        $args = func_get_args();
        array_shift($args);
        $class = "\\Eath\\$class";

        if (is_callable(array($class, 'getInstance'))) {
            array_unshift($args, $this);
            return call_user_func_array(array($class, 'getInstance'), $args);
        }

        if (count($args) > 0) {
            $ref = new \ReflectionClass($class);
            $obj = $ref->newInstanceArgs($args);
        } else {
            $obj = new $class;
        }
        $obj->setEnvironment($this);
        
        if (is_callable(array($obj, 'init'))) {
            call_user_func_array(array($obj, 'init'), $args);
        }

        return $obj;
    }

    public function getTempFile()
    {
        $name = tempnam(sys_get_temp_dir(), "eath_");
        register_shutdown_function(function() use ($name) {
            $fs = new Filesystem();
            $fs->remove($name);
        });
        return $name;
    }

    public function getTempDir()
    {
        $dir = $this->getTempFile();
        unlink($dir);
        mkdir($dir);
        return $dir . DIRECTORY_SEPARATOR;
    }

    public function getBinPath()
    {
        foreach (explode(PATH_SEPARATOR, $_SERVER['PATH']) as $dir) {
            if (is_readable($dir)) {
                return $dir . DIRECTORY_SEPARATOR;
            }
        }

        throw new \RuntimeException("Cannot find a writable bin");
    }

    public function getcwd($forceLocal = false)
    {
        return empty($this->pwd) || $forceLocal ? getcwd() : $this->pwd;
    }

    public function getBootstrap($global = false)
    {
        return $this->getInstallPath($global) . 'autoload.php';
    }

    public function getInstallPath($global = false)
    {

        $dir = ($global ? $this->getGlobalPath() : $this->getLocalPath());
        if (!is_dir($dir)) {
            if (!is_writable(dirname($dir))) {
                $this->output->writeln("Cannot create directory {$dir}");
                exit;
            }
            mkdir($dir);
        }

        return $dir;
    }

    public function ini_set($key, $newvalue, $name = 'eath')
    {
        if (ini_get($key) === $newvalue) {
            return false;
        }

        if ($filelist = php_ini_scanned_files()) {
            $inis = array_map('trim', explode(",", $filelist));
            $dir  = dirname($inis[0]);
            $ini  = $dir . "/{$name}.ini";

            if (!is_writable($dir)) {
                throw new \RuntimeException("You cannot modify {$ini} file");
            }

            $data = array();
            if (is_file($ini)) {
                $data = parse_ini_file($ini);
            }

            $data[$key] = $newvalue;

            $content = "";
            foreach ($data as $k => $v) {
                $content .= "{$k} = {$v}\n";
            }

            file_put_contents($ini, $content);
        }

        // worst thing we can do, modify the global php.ini
        $ini = php_ini_loaded_file();
        if (!is_writable($ini)) {
            throw new \RuntimeException("You cannot modify {$ini} file");
        }

    }

}
