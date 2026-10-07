<?php
/**
 * Wraps OpenStreetMap's free Nominatim geocoding service. Isolated in
 * its own class so swapping providers later (LocationIQ, Mapbox) if
 * volume ever outgrows Nominatim's usage policy means changing this
 * one file, nothing that calls it.
 *
 * Nominatim's usage policy requires: a descriptive User-Agent
 * identifying the application (not a browser UA), no bulk/automated
 * scripted use, and attribution wherever results are shown. This is
 * only ever called one address at a time, triggered by a human
 * clicking "Verify location" on the task form — never in a loop —
 * and results are cached so the same address is never looked up twice.
 */

if (! defined('ABSPATH')) {
    exit;
}

class PMS_Geocoding
{
    private const ENDPOINT = 'https://nominatim.openstreetmap.org/search';
    private const CACHE_TTL = MONTH_IN_SECONDS; // addresses don't move; cache aggressively

    /**
     * @return array{lat:float,lng:float,display_name:string}|WP_Error
     */
    public static function geocode(string $address)
    {
        $address = trim($address);

        if ($address === '') {
            return new WP_Error('pms_geocode_empty', __('Enter an address first.', 'pms'));
        }

        $cache_key = 'pms_geocode_' . md5(strtolower($address));
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $url = add_query_arg([
            'q'              => $address,
            'format'         => 'json',
            'limit'          => 1,
            'addressdetails' => 0,
        ], self::ENDPOINT);

        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => [
                // Required by Nominatim's usage policy — identifies the
                // application, not a masquerading browser user-agent.
                'User-Agent' => 'ProjectManagementSystem-WordPressPlugin/' . (defined('PMS_VERSION') ? PMS_VERSION : '1.0') . ' (' . home_url('/') . ')',
            ],
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('pms_geocode_failed', __('Could not reach the location service. Please try again.', 'pms'));
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body) || ! isset($body[0]['lat'], $body[0]['lon'])) {
            return new WP_Error('pms_geocode_not_found', __('That address could not be found. Try being more specific.', 'pms'));
        }

        $result = [
            'lat'          => (float) $body[0]['lat'],
            'lng'          => (float) $body[0]['lon'],
            'display_name' => $body[0]['display_name'] ?? $address,
        ];

        set_transient($cache_key, $result, self::CACHE_TTL);

        return $result;
    }
}
