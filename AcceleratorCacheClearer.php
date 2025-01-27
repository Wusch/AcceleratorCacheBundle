<?php

namespace SmartCore\Bundle\AcceleratorCacheBundle;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
class AcceleratorCacheClearer
{
    /**
     * @param bool $user
     * @param bool $opcode
     *
     * @return array
     */
    public static function clearCache(bool $user = true, bool $opcode = true): array
    {
        if (!$user && !$opcode) {
            throw new \InvalidArgumentException('No caches to clear.');
        }

        $messages = array('Clear PHP Accelerator Cache...');
        $success = true;

        if ($user) {
            try {
                $messages[] = self::clearUserCache();
            } catch (\RuntimeException $e) {
                $success = false;
                $messages[] = $e->getMessage();
            }
        }

        if ($opcode) {
            try {
                $messages[] = self::clearOpcodeCache();
            } catch (\RuntimeException $e) {
                $success = false;
                $messages[] = $e->getMessage();
            }
        }

        return array('success' => $success, 'message' => implode(' ', $messages));
    }

    /**
     * @return string
     */
    private static function clearUserCache(): string
    {
        if (function_exists('wincache_ucache_clear') && wincache_ucache_clear()) {
            return 'Wincache User Cache: success.';
        }

        if (function_exists('apcu_clear_cache') && apcu_clear_cache()) {
            return 'APC User Cache: success.';
        }

        if (function_exists('apc_clear_cache') && function_exists('opcache_reset') && apc_clear_cache()) {
            return 'APC User Cache: success.';
        }

        if (function_exists('apc_clear_cache') && apc_clear_cache('user')) {
            return 'APC User Cache: success.';
        }

        if (function_exists('xcache_clear_cache')) {
            $cnt = xcache_count(XC_TYPE_VAR);
            for ($i=0; $i < $cnt; $i++) {
                xcache_clear_cache(XC_TYPE_VAR, $i);
            }

            return 'XCache User Cache: success.';
        }

        return 'User Cache: failure.';
    }

    /**
     * @return string
     */
    private static function clearOpcodeCache(): string
    {
        if (function_exists('opcache_reset') && opcache_reset()) {
            return 'Zend OPcache: success.';
        }

        if (function_exists('apc_clear_cache') && apc_clear_cache('opcode')) {
            return 'APC Opcode Cache: success.';
        }

        if (function_exists('xcache_clear_cache')) {
            $cnt = xcache_count(XC_TYPE_PHP);
            for ($i=0; $i < $cnt; $i++) {
                xcache_clear_cache(XC_TYPE_PHP, $i);
            }

            return 'XCache Opcode Cache: success.';
        }

        return 'Opcode Cache: failure.';
    }
}
