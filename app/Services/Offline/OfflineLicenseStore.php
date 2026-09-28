<?php

namespace App\Services\Offline;

use Illuminate\Support\Facades\File;
use RuntimeException;

class OfflineLicenseStore
{
    public function __construct(
        private readonly OfflineInstallationIdentity $identity,
    ) {}

    public function exists(): bool
    {
        return File::exists($this->path());
    }

    public function read(): string
    {
        if (! $this->exists()) {
            throw new RuntimeException('This installation has not been activated.');
        }

        $token = trim((string) File::get($this->path()));

        if ($token === '') {
            throw new RuntimeException('The local offline license file is empty.');
        }

        return $token;
    }

    public function write(string $token): void
    {
        File::ensureDirectoryExists($this->identity->dataRoot());
        $temporary = $this->path().'.tmp';
        File::put($temporary, trim($token), true);

        if (! @rename($temporary, $this->path())) {
            @unlink($temporary);
            throw new RuntimeException('The offline license file could not be saved.');
        }
    }

    public function delete(): void
    {
        File::delete($this->path());
    }

    public function path(): string
    {
        return $this->identity->dataRoot().DIRECTORY_SEPARATOR.'license.boslic';
    }
}
