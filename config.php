<?php

/**
 * Konfigurationsdatei für den Server.
 * Kann eingebunden werden z.B. via $config = include('filename.php');
 */

return array(
    // Datenbank
    'db_host' => 'localhost',
    'db_user' => 'root',
    'db_password' => '',
    'db_name' => 'streamsite_manager',

    // defaults
    'welcome_message_default' => 'Guten Tag und herzlich willkommen bei den Perspektiven 2022 – unserer digitalen Jahresauftaktveranstaltung. Wir freuen uns über Ihre Teilnahme und wünschen Ihnen eine schöne Veranstaltung!',
    'moderation' => true,

);