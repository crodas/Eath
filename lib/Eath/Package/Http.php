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

class Http extends Dummy
{
    public function init($installed)
    {
        $parts = parse_url($this->path);
        if ($parts['host'] == 'github.com' || $parts['host'] == 'www.github.com') {
            if (!empty($parts['path']) && substr_count($parts['path'], '/') == 2) {
                return $this->env->get('Package', 'github:' . substr($parts['path'], 1));
            }
        }

        $tmpFile = $this->env->getTempFile();
        $tmpDir  = $this->env->getTempDir();

        $httpClient = $this->env->get('HttpRequest', $this->path)
            ->setOutputFile($tmpFile);

        if ($installed) {
            $httpClient->setLastMod($installed->getInfo('Last-Modified'));
        }

        $httpClient->run();

        switch ($httpClient->getStatus()) {
        case 304:
            // nothing to update!
            return new Package\Dummy;
        case 200:
            $archive = $this->env->get('Archive', $tmpFile);
            return $archive->extractTo($tmpDir, $this->path, basename($this->path))
                ->setInfo('Last-Modified', time());
        default:
            throw new \RuntimeException("HTTP Status: {$httpClient->getStatus()}");
        } 

    }
}
