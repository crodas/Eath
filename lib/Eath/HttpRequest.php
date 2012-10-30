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

class HttpRequest extends BaseClass
{
    protected $url;
    protected $file;
    protected $header = array();

    protected $meta;
    protected $response;
    protected $isCached;

    public function __construct($url = '')
    {
        $this->setUrl($url);
    }

    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    public function setOutputFile($file)
    {
        $this->file = $file;
        return $this;
    }

    public function isCached()
    {
        return $this->isCached;
    }

    public function getStatus()
    {
        return $this->meta['status'];
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function run()
    {
        $writer = $this->env->getOutput();
        $writer->writeLn("Fetching: {$this->url}");

        $fs = $this->env->get('fs');
        $cache = $this->env->getHomePath() . 'HttpCache/' . sha1($this->url);
        if (!is_dir(dirname($cache))) {
            $fs->mkdir(dirname($cache));
        }

        $args = array(
            CURLOPT_URL => $this->url,
            CURLOPT_FOLLOWLOCATION  => TRUE,
            CURLOPT_NOPROGRESS      => TRUE, 
            CURLOPT_MAXREDIRS       => 5,
            CURLOPT_BUFFERSIZE      => 64000, 
        );

        $args[CURLOPT_FILE] = fopen($cache . '.tmp', 'w+');

        $header = array();
        if (is_file($cache . '.meta') && filesize($cache . '.meta') > 0) {
            $header[] = "If-Modified-Since: " . gmdate('D, d M Y H:i:s \G\M\T', filemtime($cache . '.meta'));
        }

        if (count($header)) {
            $args[CURLOPT_HTTPHEADER] = $header;
        }

        $ch = curl_init();
        curl_setopt_array($ch, $args);

        $meta = array(
            'curl_status'   => curl_exec($ch),
            'status'        => curl_getinfo($ch, CURLINFO_HTTP_CODE),
            'contentType'   => curl_getinfo($ch, CURLINFO_CONTENT_TYPE),
            'responseFile'  => $cache,
        );

        switch ($meta['status']) {
        case 304:
            // hit cache
            $fs->remove($cache . '.tmp');
            $cachedMeta = unserialize(file_get_contents($cache . '.meta'));

            if (!is_array($cachedMeta) || count($meta) !== count($cachedMeta)) {
                $fs->remove($cache . '.meta');
                return $this->run();
            }
            $this->isCached = true;
            $meta = $cachedMeta;
            $writer->writeLn("\tCached");
            break;

        case 200:
            $this->isCached = false;
            file_put_contents($cache . '.meta', serialize($meta));
            $fs->copy($cache . '.tmp', $cache, true);
            $fs->remove($cache . '.tmp');
            break;

        default:
            throw new \RuntimeException("HTTP Status " . $meta['status']);
        }

        if (!empty($this->file)) {
            $fs->copy($cache, $this->file);
        } else {
            $content = file_get_contents($cache);
            if (strpos($meta['contentType'], 'json') !== false) {
                $content = json_decode($content, true);
            }
            $this->response = $content;
        }

        $this->meta = $meta;
        curl_close($ch);

        return $this;
    }
}
