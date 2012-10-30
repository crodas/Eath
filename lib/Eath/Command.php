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

use Symfony\Component\Console\Application as App;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

class Command extends App
{
    protected $env;

    protected function getDefaultInputDefinition()
    {
        $def = parent::getDefaultInputDefinition();
        $def->addOptions(array(
            new InputOption('--global',  '-g',      InputOption::VALUE_NONE, 'Eath will modify the global repository'),
            new InputOption('--dir', '-d',          InputOption::VALUE_REQUIRED, 'Tell to eath to install the packages in a diferent'),
            new InputOption('--autoloader', '-a',   InputOption::VALUE_REQUIRED, 'Tell to eath to build the package boostrap file in a different location'),
        ));
        return $def;
    }

    public function __construct(Environment $env)
    {
        parent::__construct("Eath CLI app", "0.0.1");
        $this->env = $env;
        $this->registerApp('Install');
        $this->registerApp('Autoload');
        $this->registerApp('Pack');
        $this->registerApp('Phar');
        $this->registerApp('Createrepo');
        $this->registerApp('InstallBin', 'install-bin');

        $package = $env->get('localPackage');
        $scripts = $package->getInfo('scripts');
        if (is_array($scripts)) {
            foreach ($scripts as $name => $alias) {
                $this->registerApp('Script', array($name, $alias));
            }
        }

    }

    public function main()
    {
        $input  = new ArgvInput();
        $output = new ConsoleOutput();
        if ($input->hasParameterOption(array('-g', '--global'))) {
            $this->env->setGlobal();
        }
        $this->env->setOutput($output);
        parent::run($input, $output);
    }

    protected function registerApp($name, $appName = NULL)
    {
        $this->add($this->env->get("Command\\$name", $appName));
    }

}
