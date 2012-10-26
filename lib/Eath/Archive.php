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

class Archive extends BaseClass
{
    protected $file;
    protected $type;
    protected $handler;

    public function __construct($file)
    {
        if (!is_file($file)) {
            throw new \RuntimeException("{$file} is not a valid file");
        }

        $this->file = $file;
    }

    public function init()
    {
        if (!$this->guessType()) {
            throw new \RuntimeException("file format is not supported");
        }

        $this->handler = $this->env->get('Archive\\' . ucfirst($this->type));
    }

    public function extractTo($dir = '', $packagePath = '', $packageName = '')
    {
        $dir    = $dir ?: $this->env->getTempDir();
        $return = $this->handler->extractTo($this->file, $dir, $packagePath, $packageName);
        if ($return instanceof Package) {
            return $return;
        }

        if (!$return) {
            throw new \RuntimeException("Failed extraction on {$this->file}");
        }

        $tmpdir = $dir;
        $finder = new Finder();
        $finder->files()->in($tmpdir)
            ->depth(0);

        if (count($finder) == 0) {
            $finder->directories()->depth(0);
            if (count($finder) == 1) {
                $dir = iterator_to_array($finder);
                $tmpdir = current($dir)->getRealPath();
            }
        }


        return $this->env->get('Package', $tmpdir)->setUrl($packagePath)->setName($packageName);
    }

    protected function guessType()
    {
        $codes = array(
            'bzip'  => "BZ",
            'gz'    => "\x1f\x8b",
            'tar'   => "\x75\x73\x74\x61\x72",
            'zip'   => "PK\x03\x04",
        );

        $fp = fopen($this->file, 'r');
        $header = fread($fp, 1024);
        fclose($fp);

        foreach ($codes as $type => $magic) {
            if (strncmp($header, $magic, strlen($magic)) === 0) {
                $this->type = $type;
                return true;
            }
        }

        // asume it is a TAR
        $this->type = 'tar';
        return true;
    }
}
