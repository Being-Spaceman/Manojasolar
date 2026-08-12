<?php
/**
 * Shared query building for the leads table — used by /leads, /leads/downloads
 * and /admin so the three screens can never disagree about what a given set
 * of filters means.
 */

declare(strict_types=1);

/**
 * Build a WHERE clause + bound params from the filter fields every screen
 * shares: date range, lead type, status. All optional; an empty filter set
 * returns every lead.
 *
 * @return array{sql: string, params: array<string, mixed>}
 */
function mnj_lead_filters(array $input): array
{
    $clauses = [];
    $params = [];

    $from = trim((string) ($input['date_from'] ?? ''));
    if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $clauses[] = 'created_at >= :date_from';
        $params['date_from'] = $from . ' 00:00:00';
    }

    $to = trim((string) ($input['date_to'] ?? ''));
    if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $clauses[] = 'created_at <= :date_to';
        $params['date_to'] = $to . ' 23:59:59';
    }

    $type = (string) ($input['lead_type'] ?? '');
    if (in_array($type, ['business', 'individual'], true)) {
        $clauses[] = 'lead_type = :lead_type';
        $params['lead_type'] = $type;
    }

    $status = (string) ($input['status'] ?? '');
    if (in_array($status, ['new', 'exported', 'contacted'], true)) {
        $clauses[] = 'status = :status';
        $params['status'] = $status;
    }

    return [
        'sql' => $clauses ? ('WHERE ' . implode(' AND ', $clauses)) : '',
        'params' => $params,
    ];
}

/** First and last calendar day of the current month, for the downloads default. */
function mnj_current_month_range(): array
{
    return [date('Y-m-01'), date('Y-m-t')];
}

function mnj_status_label(string $status, string $locale = 'en'): string
{
    $labels = [
        'en' => ['new' => 'New', 'exported' => 'Exported', 'contacted' => 'Contacted'],
        'mr' => ['new' => 'नवीन', 'exported' => 'एक्सपोर्ट झालं', 'contacted' => 'संपर्क झाला'],
    ];
    return $labels[$locale][$status] ?? $status;
}

function mnj_lead_type_label(string $type, string $locale = 'en'): string
{
    $labels = [
        'en' => ['business' => 'Business', 'individual' => 'Individual'],
        'mr' => ['business' => 'व्यवसाय', 'individual' => 'वैयक्तिक'],
    ];
    return $labels[$locale][$type] ?? $type;
}
