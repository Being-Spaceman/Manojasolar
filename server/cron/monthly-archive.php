<?php
/**
 * Runs on the 1st of each month via hPanel's cron scheduler (see
 * server/README.md for the exact expression). Archives *last* month's leads
 * — on the 1st, "this month" has nothing in it yet. Same underlying writer
 * as the manual "run it now" button on /admin/archive.php.
 */

declare(strict_types=1);

require __DIR__ . '/../lib/archive.php';

$result = mnj_run_monthly_archive();

// Cron output normally goes nowhere (or into hPanel's cron log, if it keeps
// one) — this line is for that log, not for a human waiting on the request.
echo "Archived {$result['count']} leads to {$result['filename']}\n";
