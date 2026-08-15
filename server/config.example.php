<?php
/**
 * Copy this file to config.php and fill in real values. config.php is
 * gitignored — it never gets committed.
 */

return [
    // --- database ----------------------------------------------------------
    'db' => [
        'host' => '127.0.0.1',
        'name' => 'CHANGE_ME_db_name',
        'user' => 'CHANGE_ME_db_user',
        'pass' => 'CHANGE_ME_db_password',
        'charset' => 'utf8mb4',
    ],

    // --- mail ----------------------------------------------------------------
    // Digest recipient (server/cron/digest.php). Switch to a domain mailbox
    // (e.g. sales@manojasolar.in) once one exists.
    'mail_to' => 'manojaagencies.solar@gmail.com',
    'mail_from' => 'no-reply@manojasolar.in',

    // --- auth ------------------------------------------------------------
    // bcrypt hashes only — never a plaintext password.
    // Generate with: php -r "echo password_hash('yourpassword', PASSWORD_BCRYPT), PHP_EOL;"
    'desk_password_hash' => '$2y$10$CHANGE.ME.CHANGE.ME.CHANGE.ME.CHANGE.ME.CHANGE.MEuq',
    'admin_password_hash' => '$2y$10$CHANGE.ME.CHANGE.ME.CHANGE.ME.CHANGE.ME.CHANGE.MEuq',

    // --- privacy -----------------------------------------------------------
    // Pepper mixed into the IP hash before it is stored. Never the raw IP.
    // Generate with: php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
    'ip_hash_salt' => 'CHANGE_ME_to_a_random_64_char_hex_string',
];
