<?php

namespace App\Support;

use Illuminate\Foundation\Vite;

class LanAwareVite extends Vite
{
    /**
     * Rewrite the Vite dev-server origin to the host the browser actually used.
     *
     * Docker 上の Vite は hot ファイルに http://localhost:5174 を書く。
     * スマホから LAN IP で開くとそのままでは端末自身の localhost を見にいってしまう。
     */
    protected function hotAsset($asset)
    {
        $url = rtrim((string) file_get_contents($this->hotFile()));
        $host = request()->getHost();

        if ($host !== '' && ! in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            $parts = parse_url($url);

            if (isset($parts['scheme'], $parts['host'])) {
                $url = $parts['scheme'].'://'.$host.(isset($parts['port']) ? ':'.$parts['port'] : '');
            }
        }

        return $url.'/'.$asset;
    }
}
