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
namespace Eath\Archive;

use Eath\BaseClass,
    PharData;

class Tar extends BaseClass
{

    // PHP iUnTAR Version 3.0
    // license: Revised BSD license
    function untar($tarfile,$outdir,$chmod=null) 
    {
        $TarSize = filesize($tarfile);
        $TarSizeEnd = $TarSize - 1024;
        if($outdir!="" && !file_exists($outdir)) {
            mkdir($outdir,0777); 
        }
        
        $thandle = fopen($tarfile, "r");
        while (ftell($thandle)<$TarSizeEnd) 
        {
            $FileName = $outdir.trim(fread($thandle,100));
            $FileMode = trim(fread($thandle,8));
            if($chmod === null) {
                $FileCHMOD = octdec("0".substr($FileMode,-3)); 
            }
            if($chmod !== null) {
                $FileCHMOD = $chmod; 
            }
            $OwnerID  = trim(fread($thandle,8));
            $GroupID  = trim(fread($thandle,8));
            $FileSize = octdec(trim(fread($thandle,12)));
            $LastEdit = trim(fread($thandle,12));
            $Checksum = trim(fread($thandle,8));
            $FileType = trim(fread($thandle,1));
            $LinkedFile = trim(fread($thandle,100));
            fseek($thandle,255, SEEK_CUR);
            if($FileType=="0") {
                $FileContent = fread($thandle,$FileSize); 
            }
            if($FileType=="1") {
                $FileContent = null; 
            }
            if($FileType=="2") {
                $FileContent = null; 
            }
            if($FileType=="5") {
                $FileContent = null; 
            }
            if($FileType=="0") {
                $dir = dirname($FileName);
                if (!is_dir($dir)) {
                    $this->env->get('fs')->mkdir($dir);
                }

                $subhandle = fopen($FileName, "a+");
                fwrite($subhandle,$FileContent,$FileSize);
                fclose($subhandle);
                chmod($FileName,$FileCHMOD); 
            }
            if($FileType=="1") {
                link($FileName,$LinkedFile); 
            }
            if($FileType=="2") {
                symlink($LinkedFile,$FileName); 
            }
            if($FileType=="5") {
                mkdir($FileName, $FileCHMOD); 
            }
                //touch($FileName,$LastEdit);
            if($FileType=="0") {
                $CheckSize = 512;
                while ($CheckSize<$FileSize) {
                    if($CheckSize<$FileSize) {
                        $CheckSize = $CheckSize + 512; 
                    }
                }
                $SeekSize = $CheckSize - $FileSize;
                fseek($thandle,$SeekSize,SEEK_CUR); 
            } 
        }
        fclose($thandle);
        return true;
    }

    public function extractTo($file, $dir)
    {
        if (substr($file, -4) != ".tar") {
            rename($file, $file . '.tar');
        }
        $archive = new \PharData($file);
        return $archive->extractTo($dir);
    }
}
