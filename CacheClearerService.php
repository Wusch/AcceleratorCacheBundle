<?php

namespace SmartCore\Bundle\AcceleratorCacheBundle;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
class CacheClearerService
{
    const MODE_FOPEN = 'fopen';
    const MODE_CURL = 'curl';

    private string $host;
    private string $webDir;
    private string $template;
    private string $mode;
    private array $options;

    /**
     * @param string $host
     * @param string $webDir
     * @param string $template
     * @param string $mode
     * @param array  $options
     */
    public function __construct(string $host, string $webDir, string $template, string $mode = self::MODE_FOPEN, array $options = array())
    {
        if (!in_array($mode, array(self::MODE_FOPEN, self::MODE_CURL))) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a valid mode.', $mode));
        }

        $this->host = $host;
        $this->webDir = $webDir;
        $this->template = $template;
        $this->mode = $mode;
        $this->options = $options;
    }

    /**
     * @param bool $user
     * @param bool $opcode
     * @param string|null $authentication
     *
     * @return array
     *
     * @throws \Exception
     */
    public function clearCache(bool $user = true, bool $opcode = true, string $authentication = null): array
    {
        $filename = $this->createTemporaryFile($user, $opcode);
        $url = sprintf('%s/%s', $this->host, basename($filename));

        try {
            $result = $this->sendRequest($url, $authentication);
        } catch (\Exception $e) {
            unlink($filename);

            throw $e;
        }

        unlink($filename);

        return json_decode($result, true);
    }

    /**
     * @param string $url
     * @param string|null $authentication
     *
     * @return string
     */
    private function sendRequest(string $url, string $authentication = null): string
    {
        if (self::MODE_FOPEN === $this->mode) {
            return $this->sendFopenRequest($url, $authentication);
        }

        return $this->sendCurlRequest($url, $authentication);
    }

    /**
     * @param string $url
     * @param string|null $authentication
     *
     * @return string
     */
    private function sendFopenRequest(string $url, string $authentication = null): string
    {
        $context = null;

        if (null !== $authentication) {
            $context = stream_context_create(array('http' => array(
                'header' => 'Authorization: Basic '.base64_encode($authentication),
            )));
        }

        for ($i = 0; $i < 5; $i++) {
            if ($result = @file_get_contents($url, null, $context)) {
                break;
            }

            sleep(1);
        }

        if (false === $result) {
            throw new \RuntimeException(sprintf('Unable to read "%s", does the host locally resolve?', $url));
        }

        return $result;
    }

    /**
     * @param string $url
     * @param string|null $authentication
     *
     * @return string
     */
    private function sendCurlRequest(string $url, string $authentication = null): string
    {
        $handle = curl_init($url);

        curl_setopt_array($handle, array_replace($this->options, array(
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FAILONERROR => true,
        )));

        if (null !== $authentication) {
            curl_setopt($handle, CURLOPT_USERPWD, $authentication);
        }

        $result = curl_exec($handle);

        if (curl_errno($handle)) {
            $error = curl_error($handle);
            curl_close($handle);

            throw new \RuntimeException(sprintf('Curl error reading "%s": %s', $url, $error));
        }

        curl_close($handle);

        return $result;
    }

    /**
     * Create the temporary file and return the filename.
     *
     * @param bool $user
     * @param bool $opcode
     *
     * @return string
     */
    private function createTemporaryFile(bool $user, bool $opcode): string
    {
        if (!is_dir($this->webDir)) {
            throw new \InvalidArgumentException(sprintf('Web dir does not exist "%s"', $this->webDir));
        }

        if (!is_writable($this->webDir)) {
            throw new \InvalidArgumentException(sprintf('Web dir is not writable "%s"', $this->webDir));
        }

        $filename = sprintf('%s/%s', $this->webDir, 'apc-'.md5(uniqid().mt_rand(0, 9999999).php_uname()).'.php');
        $contents = strtr($this->template, array(
            '%clearer_code%' => file_get_contents(__DIR__.'/AcceleratorCacheClearer.php'),
            '%user%' => var_export($user, true),
            '%opcode%' => var_export($opcode, true),
        ));

        if (false === $handle = fopen($filename, 'w+')) {
            throw new \RuntimeException(sprintf('Can\'t open "%s"', $filename));
        }

        fwrite($handle, $contents);
        fclose($handle);

        return $filename;
    }
}
