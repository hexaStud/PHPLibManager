<?php

namespace HexaStudio\Libary\Manager;

class Lib
{
    private bool $close = false;
    private array $remoteLib;
    private array|false $lib;
    private string $repo;
    private string $dest;
    private bool $canBeUpdated;

    private static function removeDir(string $dir)
    {
        if (is_dir($dir)) {
            $files = scandir($dir);
            foreach ($files as $file) {
                if ($file != "." && $file != "..") {
                    Lib::removeDir("$dir/$file");
                }
            }
            rmdir($dir);
        } else if (file_exists($dir)) {
            unlink($dir);
        }
    }

    private static function copyFolder(string $src, string $dst)
    {
        if (file_exists($dst)) {
            Lib::removeDir($dst);
        }
        if (is_dir($src)) {
            mkdir($dst);
            $files = scandir($src);
            foreach ($files as $file) {
                if ($file != "." && $file != "..") {
                    Lib::copyFolder("$src/$file", "$dst/$file");
                }
            }
        } else if (file_exists($src)) {
            copy($src, $dst);
        }
    }

    private static function getRemoteLib(string $repoName): array|false
    {
        // https://raw.githubusercontent.com/hexaStud/PHPBaseCollection/master/lib.json
        try {
            $lib = file_get_contents("https://raw.githubusercontent.com/$repoName/master/lib.json");
            if (!$lib) {
                return false;
            }
            $arr = json_decode($lib, true);
            if ($arr) {
                return $arr;
            } else {
                return false;
            }
        } catch (\Exception $err) {
            return false;
        }
    }

    private static function getLibs(string $dest): array
    {
        if (file_exists("$dest/libs.json")) {
            return json_decode(file_get_contents("$dest/libs.json"), true);
        } else {
            return [];
        }
    }

    public static function checkLib(string $repoName, string $dest): Lib|false
    {
        $remoteLib = Lib::getRemoteLib($repoName);
        if (!$remoteLib) {
            return false;
        }
        $libs = Lib::getLibs($dest);

        foreach ($libs as $lib) {
            if (isset($lib["name"]) && isset($lib["version"])) {
                if ($lib["name"] === $remoteLib["name"]) {
                    $remoteIsNew = version_compare($lib["version"], $remoteLib["version"]) === -1;

                    return new Lib($remoteIsNew, $repoName, $dest, $remoteLib, $lib);
                }
            }
        }


        return new Lib(true, $repoName, $dest, $remoteLib, false);
    }

    public static function init(string $dest): void
    {
        if (!file_exists($dest . "/libs.json")) {
            file_put_contents($dest . "/libs.json", "[]");
        }
    }

    private function __construct(bool $remoteIsNew, string $repo, string $dest, array $remoteLib, array|false $lib)
    {
        $this->canBeUpdated = $remoteIsNew;
        $this->repo = $repo;
        $this->dest = $dest;
        $this->remoteLib = $remoteLib;
        $this->lib = $lib;
    }

    private function updateLibFile(): void
    {
        $libs = Lib::getLibs($this->dest);
        if ($this->lib) {
            for ($i = 0; $i < count($libs); $i++) {
                if ($libs[$i]["name"] === $this->lib["name"]) {
                    $libs[$i] = $this->remoteLib;
                }
            }
        } else {
            array_push($libs, $this->remoteLib);
        }

        file_put_contents($this->dest . "/libs.json", json_encode($libs, JSON_PRETTY_PRINT));
    }

    public function updateExists(): bool
    {
        if ($this->close) {
            return false;
        }
        return $this->canBeUpdated;
    }

    public function update(): bool
    {
        if ($this->close) {
            return false;
        }
        // https://github.com/hexaStud/PHPBaseCollection/archive/refs/heads/master.zip

        file_put_contents(__DIR__ . "/tmp.zip", file_get_contents("https://github.com/{$this->repo}/archive/refs/heads/master.zip"));
        $zip = new \ZipArchive();
        if ($zip->open(__DIR__ . "/tmp.zip") === true) {
            if (!file_exists("{$this->dest}/{$this->remoteLib["name"]}/") || is_file("{$this->dest}/{$this->remoteLib["name"]}/")) {
                mkdir("{$this->dest}/{$this->remoteLib["name"]}/");
            }
            if (!file_exists("{$this->dest}/__BIN__/") || is_file("{$this->dest}/__BIN__/")) {
                mkdir("{$this->dest}/__BIN__/");
            }

            $zip->extractTo("{$this->dest}/__BIN__/");
            $zip->close();

            Lib::copyFolder("{$this->dest}/__BIN__/" . scandir("{$this->dest}/__BIN__/")[2], "{$this->dest}/{$this->remoteLib["name"]}/");
            unlink(__DIR__ . "/tmp.zip");
            Lib::removeDir("{$this->dest}/__BIN__/");
            $this->updateLibFile();
            $this->close = true;
            return true;
        } else {
            return false;
        }
    }

    public function isClose(): bool
    {
        return $this->close;
    }
}