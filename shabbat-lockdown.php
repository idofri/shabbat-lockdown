<?php

/**
 * Plugin Name: Shabbat Lockdown
 * Version:     1.0.4
 * Author:      Ido Friedlander
 * Author URI:  https://github.com/idofri
 */

class Shabbat_Lockdown
{
    const FORMAT = 'json';

    const GEO_CITY = 'Jerusalem';

    const HAVDALAH_MINUTES  = 50;

    const API_BASE_URL = 'https://www.hebcal.com/shabbat/';

    const TRANSIENT = 'candle_lighting_time';

    public static function instance()
    {
        static $instance;
        return $instance ?? ($instance = new static);
    }

    public function __construct()
    {
        add_action('shabbat_schedule', [$this, 'schedule']);
        add_action('template_redirect', [$this, 'observe']);
        add_action('deleted_transient', [$this, 'activate']);
    }

    public function activate($transient = '')
    {
        if ($transient && $transient != self::TRANSIENT) {
            return;
        }

        list($startTime, $endTime) = $this->fetchNextSchedule();
        if ($startTime && $endTime) {
            wp_schedule_single_event($startTime, 'shabbat_schedule', [$endTime]);
        }
    }

    public function schedule($endTime)
    {
        $expiration = $endTime - (new DateTime(date_i18n('c')))->getTimestamp();
        set_transient(self::TRANSIENT, $endTime, $expiration);
    }

    public function observe()
    {
        if (false === ($endTime = get_transient(self::TRANSIENT))) {
            return;
        }

        do_action('shabbat_lockdown/template_redirect', $endTime);
        add_filter('template_include', function($template) use ($endTime) {
            return apply_filters('shabbat_lockdown/template_include', $template, $endTime);
        });
    }

    public function fetchNextSchedule()
    {
        $response = wp_remote_get(
            add_query_arg([
                'cfg' => self::FORMAT,
                'city' => self::GEO_CITY,
                'm' => self::HAVDALAH_MINUTES
            ], self::API_BASE_URL)
        );

        if (is_wp_error($response)) {
            return [false, false];
        }

        $result = json_decode(wp_remote_retrieve_body($response), true);
        $schedule = wp_list_pluck($result['items'] ?? [], 'date', 'category');

        return [
            strtotime($schedule['candles'] ?? null),
            strtotime($schedule['havdalah'] ?? null),
        ];
    }
}

$plugin = Shabbat_Lockdown::instance();
register_activation_hook(__FILE__, [$plugin, 'activate']);
