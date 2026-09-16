<?php
/**
 * Drop-in: wp-content/db.php — keep trying the database for a moment when the host is full.
 *
 * The hosting account allows only about fifteen database connections at the same
 * time and refuses the next one outright ("2002 Operation not permitted"). WordPress
 * gives up on the first refusal and shows "Error establishing a database connection",
 * although a place frees up a few hundred milliseconds later. This waits and tries
 * again, six times over about two seconds, before letting WordPress give up.
 *
 * Source: themes/sc_events/inc/dropins/db.php. Copy it to wp-content/db.php; delete
 * that copy to go back to WordPress's own behaviour.
 */

defined('ABSPATH') || exit;

class SC_Patient_WPDB extends wpdb {
    public function db_connect($allow_bail = true) {
        $waits = array(80, 160, 240, 320, 480, 640); // milliseconds, about two seconds in all
        foreach ($waits as $i => $wait) {
            if (parent::db_connect(false)) {
                return true;
            }
            usleep(($wait + mt_rand(0, 60)) * 1000);
        }
        return parent::db_connect($allow_bail);
    }
}

$wpdb = new SC_Patient_WPDB(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
