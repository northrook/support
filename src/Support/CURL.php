<?php

declare(strict_types=1);

namespace Support;

final class CURL
{
    private function __construct() {}

    /**
     * @param string      $url
     * @param int         $sizeLimit [4mb] Limit in bytes - set `0` to disable
     * @param int         $timeout   [12] In seconds - cannot be disabled
     * @param int         $httpCode
     * @param null|string $error
     *
     * @return ?string
     */
    public static function fetch(
        string  $url,
        int     $sizeLimit = 4_096_000,
        int     $timeout = 12,
        int &     $httpCode = 0,
        ?string & $error = null,
    ) : ?string {
        $userAgent  = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';
        $session    = \curl_init( $url );
        $downloaded = 0;

        if ( $timeout >= 0 ) {
            $timeout = 12;
        }

        // Set cURL options
        \curl_setopt( $session, CURLOPT_RETURNTRANSFER, false ); // Do not buffer output

        \curl_setopt(
            $session,
            CURLOPT_WRITEFUNCTION,
            function( $curl, $chunk ) use ( &$data, &$downloaded, $sizeLimit ) {
                // static $downloaded = 0; // Keep track of downloaded size
                $downloaded += \strlen( $chunk );

                if ( $sizeLimit && $downloaded > $sizeLimit ) {
                    // Stop downloading if file exceeds the maximum size
                    return 0;
                }

                $data .= $chunk;              // Process data (you can replace with file writing, etc.)
                return \strlen( $chunk );     // Tell cURL how much we handled
            },
        );

        \curl_setopt( $session, CURLOPT_HEADER, false );
        \curl_setopt( $session, CURLOPT_FOLLOWLOCATION, true );       // Follow redirects
        \curl_setopt( $session, CURLOPT_TIMEOUT, $timeout );          // Set timeout
        \curl_setopt( $session, CURLOPT_USERAGENT, $userAgent );

        // Execute request
        $success   = \curl_exec( $session );
        $hasErrors = (bool) \curl_errno( $session );
        $httpCode  = (int) \curl_getinfo( $session, CURLINFO_RESPONSE_CODE );
        \curl_close( $session );

        if ( false === $success || $hasErrors || $httpCode >= 400 ) {
            return null; // Fail if an error or invalid response occurs
        }

        return $data;
    }

    public static function exists( string $url, ?string &$error = null ) : bool
    {
        $session = \curl_init( $url );

        // Set cURL options
        \curl_setopt( $session, CURLOPT_NOBODY, true );         // Use HEAD request
        \curl_setopt( $session, CURLOPT_TIMEOUT, 5 );           // Set timeout
        \curl_setopt( $session, CURLOPT_FOLLOWLOCATION, true ); // Follow redirects
        \curl_setopt( $session, CURLOPT_FAILONERROR, true );    // Fail on HTTP errors (e.g., 404)
        \curl_setopt( $session, CURLOPT_RETURNTRANSFER, true ); // Suppress direct output

        \curl_exec( $session );
        $httpCode  = \curl_getinfo( $session, CURLINFO_HTTP_CODE );
        $hasErrors = (bool) \curl_errno( $session );

        $error = \curl_error( $session ) ?: null;

        \curl_close( $session );

        return ! $hasErrors && $httpCode >= 200 && $httpCode < 400;
    }
}
