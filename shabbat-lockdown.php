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
        add_action('shabbat_lockdown', [$this, 'schedule']);
        add_action('template_redirect', [$this, 'lockdown']);

        if (!wp_next_scheduled('shabbat_lockdown')) {
            $startTime = $this->getStartTime();
            if ($startTime) {
                wp_schedule_single_event($startTime, 'shabbat_lockdown');
            }
        }
    }

    public function schedule()
    {
        $endTime = $this->getEndTime();
        if ($endTime) {
            $expiration = $endTime - (new DateTime(date_i18n('c')))->getTimestamp();
            set_transient('lockdown', true, $expiration);
        }
    }

    public function lockdown()
    {
        global $wp_query;

        if (false != get_transient('lockdown')) {
            $wp_query->set_404();
            status_header(503);
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
        return wp_list_pluck($result['items'] ?? [], 'date', 'category');
    }

    public function getStartTime()
    {
        return strtotime($this->getNextSchedule()['candles'] ?? null);
    }

    public function getEndTime()
    {
        return strtotime($this->getNextSchedule()['havdalah'] ?? null);
    }
}

add_action('plugins_loaded', function () {
    Shabbat_Lockdown::instance();
});
