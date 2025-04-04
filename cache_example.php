
<?php

class CacheSystem {
    private string $cacheDir;
    private int $defaultExpiration;

    /**
     * Constructor.
     *
     * @param string $cacheDir The directory to store cache files.
     * @param int $defaultExpiration The default expiration time in seconds (default: 5 minutes).
     */
    public function __construct(string $cacheDir = 'cache', int $defaultExpiration = 300) {
        $this->cacheDir = $cacheDir;
        $this->defaultExpiration = $defaultExpiration;
        $this->ensureCacheDirectoryExists();
    }

    /**
     * Ensures the cache directory exists.
     */
    private function ensureCacheDirectoryExists(): void {
        if (!is_dir($this->cacheDir)) {
            if (!mkdir($this->cacheDir, 0777, true)) {
                throw new \Exception(sprintf('Failed to create cache directory "%s"', $this->cacheDir));
            }
        }
    }

    /**
     * Saves data to the cache.
     *
     * @param string $key The unique key for the cached data.
     * @param mixed $data The data to cache.
     * @param int|null $expiration The expiration time in seconds (optional, uses default if null).
     * @return bool True on success, false on failure.
     */
    public function save(string $key, mixed $data, ?int $expiration = null): bool {
        $cacheFile = $this->getCacheFilePath($key);
        $expirationTime = time() + ($expiration ?? $this->defaultExpiration);
        $dataToStore = serialize(['expiration' => $expirationTime, 'data' => $data]);

        try {
            return (bool) file_put_contents($cacheFile, $dataToStore, LOCK_EX);
        } catch (\Exception $e) {
            error_log(sprintf('Error saving cache for key "%s": %s', $key, $e->getMessage()));
            return false;
        }
    }

    /**
     * Retrieves data from the cache if it's not expired.
     *
     * @param string $key The unique key for the cached data.
     * @return mixed The cached data if valid, otherwise null.
     */
    public function get(string $key): mixed {
        $cacheFile = $this->getCacheFilePath($key);

        if (!file_exists($cacheFile)) {
            return null;
        }

        try {
            $data = unserialize(file_get_contents($cacheFile));
            if ($data === false || !isset($data['expiration']) || !isset($data['data'])) {
                $this->deleteCacheFile($cacheFile);
                return null;
            }

            if (time() > $data['expiration']) {
                $this->deleteCacheFile($cacheFile);
                return null;
            }

            return $data['data'];
        } catch (\Exception $e) {
            error_log(sprintf('Error reading cache for key "%s": %s', $key, $e->getMessage()));
            $this->deleteCacheFile($cacheFile);
            return null;
        }
    }

    /**
     * Cleans up expired cache files.
     *
     * @return void
     */
    public function clean(): void {
        if (!is_dir($this->cacheDir)) {
            return;
        }

        $files = scandir($this->cacheDir);
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $cacheFile = $this->cacheDir . '/' . $file;
            if (is_file($cacheFile)) {
                try {
                    $data = @unserialize(file_get_contents($cacheFile));
                    if ($data !== false && isset($data['expiration']) && time() > $data['expiration']) {
                        $this->deleteCacheFile($cacheFile);
                    }
                } catch (\Exception $e) {
                    // Ignore unserialize errors, likely not a valid cache file
                }
            }
        }
    }

    /**
     * Gets the full path to the cache file.
     *
     * @param string $key The cache key.
     * @return string The full file path.
     */
    private function getCacheFilePath(string $key): string {
        return $this->cacheDir . '/' . md5($key) . '.cache';
    }

    /**
     * Deletes a cache file.
     *
     * @param string $filePath The path to the cache file.
     * @return void
     */
    private function deleteCacheFile(string $filePath): void {
        try {
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        } catch (\Exception $e) {
            error_log(sprintf('Error deleting cache file "%s": %s', $filePath, $e->getMessage()));
        }
    }
}

/**
 * Function to simulate fetching API data and caching it.
 *
 * @param string $apiUrl The URL of the API to fetch.
 * @param CacheSystem $cache The CacheSystem instance to use.
 * @return array|null The API response data (from cache or fetched).
 */
function fetchAndCacheApiData(string $apiUrl, CacheSystem $cache): ?array {
    $cacheKey = 'api_data_' . md5($apiUrl); // Create a unique key based on the API URL

    // Try to get data from cache
    $cachedData = $cache->get($cacheKey);
    if ($cachedData !== null) {
        echo "Data retrieved from cache for: " . $apiUrl . "\n";
        return $cachedData;
    }

    echo "Fetching API data for: " . $apiUrl . "...\n";

    // Simulate fetching data from an API
    // In a real scenario, you would use file_get_contents, curl, or a dedicated HTTP client
    $apiResponse = fetchDataFromApi($apiUrl);

    if ($apiResponse) {
        // Save the fetched data to the cache (you can customize the expiration time here)
        $cache->save($cacheKey, $apiResponse, 300); // Cache for 5 minutes
        return $apiResponse;
    }

    return null; // Return null if API fetch failed
}

/**
 * Simulates fetching data from an API endpoint.
 *
 * @param string $apiUrl The URL to "fetch".
 * @return array|bool An array representing the API response, or false on failure.
 */
function fetchDataFromApi(string $apiUrl): array|bool {
    // In a real application, this would involve making an HTTP request
    // For demonstration purposes, we'll just return some dummy data
    if ($apiUrl === 'https://example.com/api/users') {
        return [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ];
    } elseif ($apiUrl === 'https://example.com/api/products') {
        return [
            ['id' => 101, 'name' => 'Laptop'],
            ['id' => 102, 'name' => 'Mouse'],
        ];
    } else {
        return false;
    }
}

// Example usage:
$cacheSystem = new CacheSystem('my_app_cache', 600); // Custom cache directory and 10-minute default expiration

// First request (will fetch from the "API" and cache)
$usersData = fetchAndCacheApiData('https://example.com/api/users', $cacheSystem);
print_r($usersData);

echo "\n";

// Second request (will retrieve from cache)
$usersDataFromCache = fetchAndCacheApiData('https://example.com/api/users', $cacheSystem);
print_r($usersDataFromCache);

echo "\n";

// Another API endpoint
$productsData = fetchAndCacheApiData('https://example.com/api/products', $cacheSystem);
print_r($productsData);

echo "\n";

// Simulate time passing (more than the default expiration of the CacheSystem - 600 seconds in this example, but the specific cache for 'users' was set to 300 seconds)
sleep(305);

// Third request for users (cache should be expired based on the fetchAndCacheApiData function's explicit 300-second cache duration)
$usersDataAfterExpiration = fetchAndCacheApiData('https://example.com/api/users', $cacheSystem);
print_r($usersDataAfterExpiration);

echo "\n";

// Clean up any other expired cache files
$cacheSystem->clean();

?> 