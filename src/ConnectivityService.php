<?php

namespace Techparse\OfflineSync;

use Illuminate\Support\Facades\Http;

class ConnectivityService
{
    protected bool $isOnline = false;
    protected ?int $lastCheckTimestamp = null;

    /**
     * Check if the app is online
     */
    public function isOnline(): bool
    {
        // 5-second cache
        if ($this->lastCheckTimestamp && (time() - $this->lastCheckTimestamp) < 5) {
            return $this->isOnline;
        }

        $this->isOnline = $this->checkConnection();
        $this->lastCheckTimestamp = time();

        return $this->isOnline;
    }

    /**
     * Ping the server
     */
    protected function checkConnection(): bool
    {
        try {
            $response = Http::timeout(5)->get(
                config('offline-sync.api_url') . '/sync/ping'
            );
            
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Wait for connection (with timeout)
     */
    public function waitForConnection(int $timeoutSeconds = 30): bool
    {
        $start = time();
        
        while ((time() - $start) < $timeoutSeconds) {
            if ($this->isOnline()) {
                return true;
            }
            sleep(2);
        }
        
        return false;
    }
}
