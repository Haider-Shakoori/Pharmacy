<?php

namespace App\Services\Offline;

class OfflineNetworkIdentity
{
    public function domains(): array
    {
        $domains = [];
        $hostname = trim((string) gethostname());

        if ($hostname !== '') {
            $domains[] = $hostname;
        }

        $resolved = $hostname !== '' ? gethostbynamel($hostname) : false;

        if (is_array($resolved)) {
            foreach ($resolved as $address) {
                if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) &&
                    ! str_starts_with($address, '127.')) {
                    $domains[] = $address;
                }
            }
        }

        return array_values(array_unique(array_filter($domains)));
    }

    public function urls(): array
    {
        $port = (int) config('offline.http_port', 8090);

        return array_map(
            fn (string $domain): string => 'http://'.$domain.($port === 80 ? '' : ':'.$port),
            $this->domains(),
        );
    }
}
