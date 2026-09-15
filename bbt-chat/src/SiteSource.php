<?php
declare(strict_types=1);

namespace BBTChat;

/**
 * Where page content comes from.
 *
 * Inside WordPress the plugin reads the database directly. Outside it (CLI,
 * local testing) we pull the same pages over the public REST API, so the local
 * index is built from byte-identical content to production.
 */
final class SiteSource
{
    /**
     * @return list<array{id:string,url:string,title:string,html:string}>
     */
    public static function fromRestApi(string $siteUrl, int $perPage = 100): array
    {
        $endpoint = rtrim($siteUrl, '/')
            . '/wp-json/wp/v2/pages?per_page=' . $perPage . '&_fields=id,link,title,content';

        $response = self::get($endpoint);
        $decoded  = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Unexpected response from ' . $endpoint);
        }

        $pages = [];
        foreach ($decoded as $page) {
            $html = (string) ($page['content']['rendered'] ?? '');
            if (trim($html) === '') {
                continue;
            }
            $pages[] = [
                'id'    => 'p' . (string) ($page['id'] ?? count($pages)),
                'url'   => (string) ($page['link'] ?? ''),
                'title' => Text::inline((string) ($page['title']['rendered'] ?? '')),
                'html'  => $html,
            ];
        }

        return $pages;
    }

    private static function get(string $url): string
    {
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_USERAGENT      => 'bbt-chat-indexer/1.0',
        ]);
        // Windows PHP builds often ship without a CA bundle; hosting PHP has one.
        $caBundle = getenv('BBT_CA_BUNDLE');
        if (is_string($caBundle) && $caBundle !== '' && is_file($caBundle)) {
            curl_setopt($handle, CURLOPT_CAINFO, $caBundle);
        }
        $body   = curl_exec($handle);
        $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error  = curl_error($handle);
        curl_close($handle);

        if ($body === false || $status >= 400) {
            throw new \RuntimeException("GET $url failed (HTTP $status) $error");
        }

        return (string) $body;
    }
}
