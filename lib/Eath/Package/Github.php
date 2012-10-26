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

class Github extends Dummy
{

    public function fetch()
    {
        $package = $this->env->get('Package', "https://github.com/{$this->url}/zipball/{$this->branch}", $this->installed)
            ->setUrl($this->path)
            ->setVersion(time());

        if ($version = $this->getVersion()) {
            $package->setVersion($version);
        }

        if ($package->getName() == $this->branch) {
            $package->setName($this->path, true)
                ->setFolderName($this->url);
        }
        return $package;
    }

    public function init($installed)
    {
        list(, $url)  = explode(':', $this->path);
        $url    = trim($url, '/');
        $branch = 'master';


        $this->url       = $url;
        $this->branch    = $branch;
        $this->installed = $installed;

        if (!$installed) {
            return $this->fetch();
        }

        $installedVersion = $installed->getVersion();
        $lastModified     = $installed->getInfo('Last-Modified');

        $httpClient = $this->env->get('HttpRequest')->setLastMod($lastModified);

        $httpClient->setUrl("https://raw.github.com/{$url}/{$branch}/package.yml")
            ->run();

        switch ($httpClient->getStatus()) {
        case 304:
            return;
        case 200:
            $this->loadPackageInfo($httpClient->getResponse());
            break;
        default:
            try {
                $httpClient->setUrl("https://api.github.com/repos/{$url}/git/refs/heads/{$branch}")
                    ->run();
                if ($httpClient->getStatus() == 304) {
                    return;
                } else if ($httpClient->getStatus() !== 200) {
                    // rate limits
                    throw new \Exception;
                }

                $ref  = $httpClient->getResponse();
                $tree = $httpClient->setUrl($ref['object']['url'])->run()->getResponse();
                if ($httpClient->getStatus() == 304) {
                    return;
                } else if ($httpClient->getStatus() !== 200) {
                    // rate limits
                    throw new \Exception;
                }

                $version = strtotime($tree['committer']['date']);
                $this->loadPackageInfo(array('version' => $version));
            } catch (\Exception $e) {
                // Probably rate limits
                return $this->fetch();
            }
        }

        if ($installedVersion && version_compare($installedVersion, $this->getVersion(), '>=')) {
            // nothing to install :-)
            return;
        }

        return $this->fetch();
    }
}
