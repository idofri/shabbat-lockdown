<?php

/**
 * Plugin Name: Shabbat Lockdown
 * Version:     1.0.0
 * Author:      Ido Friedlander
 * Author URI:  https://github.com/idofri
 */

class Shabbat_Lockdown
{
    const FORMAT = 'json';

    const GEO_CITY = 'Jerusalem';

    const HAVDALAH_MINUTES  = 50;

    const API_BASE_URL = 'https://www.hebcal.com/shabbat/';

    public static function instance()
    {
        static $instance;
        return $instance ?? ($instance = new static);
    }

    public function __construct()
    {
        global $wp_query;

        $schedule = wp_list_pluck($this->getNextSchedule(), 'date', 'category');
        $candles = strtotime($schedule['candles'] ?? null);
        $havdalah = strtotime($schedule['havdalah'] ?? null);

        if ($candles && $havdalah) {
            $now = (new DateTime(date_i18n('c')))->getTimestamp();
            if ($now > $candles && $now < $havdalah) {
                $wp_query->set_404();
                status_header(503);
            }
        }
    }

    public function getNextSchedule()
    {
        $response = wp_remote_get(
            add_query_arg([
                'cfg' => self::FORMAT,
                'city' => self::GEO_CITY,
                'm' => self::HAVDALAH_MINUTES
            ], self::API_BASE_URL)
        );

        if (is_wp_error($response)) {
            return [];
        }

        $result = json_decode(wp_remote_retrieve_body($response), true);
        return $result['items'] ?? [];
    }
}

add_action('template_redirect', function () {
    Shabbat_Lockdown::instance();
});
