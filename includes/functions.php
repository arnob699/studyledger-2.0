<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth.php';

/* ---------- formatting helpers ---------- */

function minutes_to_hm(int $minutes): string
{
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    if ($h === 0) return $m . 'm';
    if ($m === 0) return $h . 'h';
    return $h . 'h ' . $m . 'm';
}

function friendly_date(?string $date): string
{
    if (!$date) return '—';
    $d = new DateTime($date);
    $today = new DateTime('today');
    $diff = (int)$today->diff($d)->format('%r%a');
    if ($diff === 0) return 'Today';
    if ($diff === -1) return 'Yesterday';
    return $d->format('M j');
}

/* ---------- subjects ---------- */

function get_subjects(bool $includeArchived = false): array
{
    $pdo = get_db();
    // Computed live from cycles rather than the subject_stats cache table —
    // keeps this in sync even if the update triggers ever fall behind.
    $sql = "SELECT s.*,
                   COUNT(c.id)                                        AS total_cycles,
                   COALESCE(SUM(c.study_minutes), 0)                  AS total_study_minutes,
                   COALESCE(SUM(c.revision_minutes), 0)               AS total_revision_minutes,
                   MAX(c.created_at)                                  AS last_studied_at
            FROM subjects s
            LEFT JOIN cycles c ON c.subject_id = s.id AND c.user_id = s.user_id
            WHERE s.user_id = ?"
        . ($includeArchived ? '' : ' AND s.is_archived = 0')
        . ' GROUP BY s.id ORDER BY s.name ASC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([current_user_id()]);
    return $stmt->fetchAll();
}

function get_subject(int $id): ?array
{
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM subjects WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, current_user_id()]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/* ---------- materials ---------- */

function get_materials(?int $subjectId = null): array
{
    $pdo = get_db();
    $baseSql = 'SELECT m.*, s.name AS subject_name, s.color_hex,
                       COUNT(c.id) AS total_cycles,
                       COALESCE(SUM(c.study_minutes), 0) AS total_study_minutes,
                       COALESCE(SUM(c.revision_minutes), 0) AS total_revision_minutes,
                       COALESCE(SUM(c.study_minutes + c.revision_minutes), 0) AS total_minutes
                FROM materials m
                JOIN subjects s ON s.id = m.subject_id AND s.user_id = m.user_id
                LEFT JOIN cycles c ON c.material_id = m.id AND c.user_id = m.user_id';
    if ($subjectId) {
        $stmt = $pdo->prepare(
            $baseSql . ' WHERE m.subject_id = ? AND m.user_id = ?
             GROUP BY m.id
             ORDER BY FIELD(m.status,"in_progress","not_started","learned","mastered"), m.updated_at DESC'
        );
        $stmt->execute([$subjectId, current_user_id()]);
    } else {
        $stmt = $pdo->prepare(
            $baseSql . ' WHERE m.user_id = ?
             GROUP BY m.id
             ORDER BY FIELD(m.status,"in_progress","not_started","learned","mastered"), m.updated_at DESC'
        );
        $stmt->execute([current_user_id()]);
    }
    return $stmt->fetchAll();
}

function get_unassigned_material_time(int $subjectId): array
{
    // Cycles logged against a subject with no specific material picked
    // ("No specific material" in the start-cycle / manual-log forms).
    // These never show up in get_materials() since they have no material_id,
    // so the dashboard treats this as a pseudo material row of its own.
    $pdo = get_db();
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS total_cycles,
                COALESCE(SUM(study_minutes), 0) AS total_study_minutes,
                COALESCE(SUM(revision_minutes), 0) AS total_revision_minutes,
                COALESCE(SUM(study_minutes + revision_minutes), 0) AS total_minutes
         FROM cycles
         WHERE subject_id = ? AND material_id IS NULL AND user_id = ?'
    );
    $stmt->execute([$subjectId, current_user_id()]);
    $row = $stmt->fetch() ?: [];
    return [
        'total_cycles'           => (int)($row['total_cycles'] ?? 0),
        'total_study_minutes'    => (int)($row['total_study_minutes'] ?? 0),
        'total_revision_minutes' => (int)($row['total_revision_minutes'] ?? 0),
        'total_minutes'          => (int)($row['total_minutes'] ?? 0),
    ];
}

/* ---------- cycles ---------- */

function get_active_cycle(): ?array
{
    $pdo = get_db();
    $stmt = $pdo->prepare(
        "SELECT c.*, s.name AS subject_name, s.color_hex, m.title AS material_title
         FROM cycles c
         JOIN subjects s ON s.id = c.subject_id AND s.user_id = c.user_id
         LEFT JOIN materials m ON m.id = c.material_id AND m.user_id = c.user_id
            WHERE c.status = 'in_progress' AND c.user_id = ?
         ORDER BY c.created_at DESC LIMIT 1"
    );
        $stmt->execute([current_user_id()]);
        $row = $stmt->fetch();
    return $row ?: null;
}

function get_recent_cycles(int $limit = 8): array
{
    $pdo = get_db();
        $stmt = $pdo->prepare(
        "SELECT c.*, s.name AS subject_name, s.color_hex, m.title AS material_title
         FROM cycles c
            JOIN subjects s ON s.id = c.subject_id AND s.user_id = c.user_id
            LEFT JOIN materials m ON m.id = c.material_id AND m.user_id = c.user_id
            WHERE c.user_id = ?
         ORDER BY c.created_at DESC LIMIT ?"
    );
        $stmt->bindValue(1, current_user_id(), PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
    return $stmt->fetchAll();
}

/* ---------- stats ----------
   Everything below is computed live from `cycles` rather than the
   trigger-maintained cache tables (daily_stats / subject_stats / streaks).
   That cache can silently drift out of sync (e.g. if the DB triggers
   weren't created, or were edited after the fact), which is what was
   causing counters on the dashboard to stop updating. Live aggregation
   is cheap enough for a single-user tracker and can never go stale. */

function get_today_stats(): array
{
    $pdo = get_db();
    $stmt = $pdo->prepare(
        "SELECT
            COUNT(*)                                    AS total_cycles,
            COALESCE(SUM(status = 'completed'), 0)      AS completed_cycles,
            COALESCE(SUM(study_minutes), 0)              AS total_study_minutes,
            COALESCE(SUM(revision_minutes), 0)            AS total_revision_minutes,
            COALESCE(SUM(study_minutes + revision_minutes), 0) AS total_minutes,
            COUNT(DISTINCT subject_id)                  AS subjects_touched
            FROM cycles WHERE cycle_date = CURDATE() AND user_id = ?"
    );
        $stmt->execute([current_user_id()]);
        return $stmt->fetch() ?: [
        'total_cycles' => 0, 'completed_cycles' => 0, 'total_study_minutes' => 0,
        'total_revision_minutes' => 0, 'total_minutes' => 0, 'subjects_touched' => 0,
    ];
}

function get_week_stats(): array
{
    $pdo = get_db();
    // Calendar week, Monday-start. WEEKDAY() is 0 for Monday .. 6 for Sunday.
    $stmt = $pdo->prepare(
        "SELECT
            COUNT(*)                                     AS total_cycles,
            COALESCE(SUM(status = 'completed'), 0)       AS completed_cycles,
            COALESCE(SUM(study_minutes + revision_minutes), 0) AS total_minutes
         FROM cycles
         WHERE user_id = ? AND cycle_date BETWEEN DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
                              AND DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 6 DAY)"
    );
    $stmt->execute([current_user_id()]);
    $row = $stmt->fetch() ?: [];
    return [
        'total_cycles'     => (int)($row['total_cycles'] ?? 0),
        'completed_cycles' => (int)($row['completed_cycles'] ?? 0),
        'total_minutes'    => (int)($row['total_minutes'] ?? 0),
        'week_start'       => date('Y-m-d', strtotime('monday this week')),
        'week_end'         => date('Y-m-d', strtotime('sunday this week')),
    ];
}

function get_monthly_stats(?string $month = null): array
{
    $pdo = get_db();
    $month = ($month && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) ? $month : date('Y-m');
    $monthStart = $month . '-01';

    // Every day of the chosen month (offsets 0-39 via a x b cross join,
    // trimmed to the real month in WHERE), left-joined against cycles so
    // days with nothing logged still show up as a zero-height bar.
        $stmt = $pdo->prepare(
          "SELECT DATE_FORMAT(d.day, '%Y-%m-%d')            AS stat_date,
                DAY(d.day)                                 AS day_num,
                COALESCE(SUM(c.study_minutes), 0)           AS study_minutes,
                COALESCE(SUM(c.study_minutes + c.revision_minutes), 0) AS total_minutes
           FROM (
            SELECT DATE_ADD(?, INTERVAL (a.n + b.n*10) DAY) AS day
            FROM (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
                UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a
            CROSS JOIN (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3) b
           ) d
           LEFT JOIN cycles c ON c.cycle_date = d.day AND c.user_id = ?
           WHERE d.day BETWEEN ? AND LAST_DAY(?)
           GROUP BY d.day
           ORDER BY d.day ASC"
        );
        $stmt->execute([$monthStart, current_user_id(), $monthStart, $monthStart]);
    $rows = $stmt->fetchAll();

    $cumulative = 0;
    foreach ($rows as &$r) {
        $cumulative += (int)$r['total_minutes'];
        $r['cumulative_minutes'] = $cumulative;
    }
    unset($r);

    return ['month' => $month, 'days' => $rows];
}

function get_day_breakdown(string $date): array
{
    $parsed = DateTime::createFromFormat('Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) return [];

    $pdo = get_db();
    $stmt = $pdo->prepare(
        "SELECT s.name AS subject_name, s.color_hex,
                COALESCE(m.title, 'No material') AS material_title,
                COUNT(c.id) AS total_cycles,
                SUM(c.study_minutes) AS study_minutes,
                SUM(c.revision_minutes) AS revision_minutes,
                SUM(c.study_minutes + c.revision_minutes) AS total_minutes
         FROM cycles c
         JOIN subjects s ON s.id = c.subject_id AND s.user_id = c.user_id
         LEFT JOIN materials m ON m.id = c.material_id AND m.user_id = c.user_id
         WHERE c.user_id = ? AND c.cycle_date = ?
         GROUP BY s.id, s.name, s.color_hex, m.id, m.title
         ORDER BY total_minutes DESC, s.name ASC, material_title ASC"
    );
    $stmt->execute([current_user_id(), $date]);
    return $stmt->fetchAll();
}

function get_streak(): array
{
    $pdo = get_db();
    $stmt = $pdo->prepare(
        "SELECT DISTINCT cycle_date FROM cycles WHERE status = 'completed' AND user_id = ? ORDER BY cycle_date DESC"
    );
    $stmt->execute([current_user_id()]);
    $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (!$dates) {
        return ['current_streak' => 0, 'longest_streak' => 0, 'last_active_date' => null];
    }

    // Current streak: only counts if the most recent active day was today or yesterday,
    // then walks backward while each date is exactly one day before the last.
    $current = 0;
    $today = new DateTime('today');
    $mostRecent = new DateTime($dates[0]);
    $gapToToday = (int)$mostRecent->diff($today)->format('%a');
    if ($mostRecent <= $today && $gapToToday <= 1) {
        $current = 1;
        $expect = clone $mostRecent;
        for ($i = 1, $n = count($dates); $i < $n; $i++) {
            $expect->modify('-1 day');
            if ($dates[$i] === $expect->format('Y-m-d')) {
                $current++;
            } else {
                break;
            }
        }
    }

    // Longest streak: walk chronologically forward through distinct active dates.
    $longest = 0;
    $run = 0;
    $prev = null;
    foreach (array_reverse($dates) as $d) {
        $dt = new DateTime($d);
        $run = ($prev !== null && (int)$prev->diff($dt)->format('%a') === 1) ? $run + 1 : 1;
        $longest = max($longest, $run);
        $prev = $dt;
    }

    return [
        'current_streak'   => $current,
        'longest_streak'   => $longest,
        'last_active_date' => $dates[0],
    ];
}

function get_active_goal(string $type = 'daily'): ?array
{
    $pdo = get_db();
    $stmt = $pdo->prepare(
         "SELECT * FROM goals WHERE goal_type = ? AND is_active = 1 AND user_id = ?
         AND start_date <= CURDATE() AND (end_date IS NULL OR end_date >= CURDATE())
         ORDER BY created_at DESC LIMIT 1"
    );
    $stmt->execute([$type, current_user_id()]);
    return $stmt->fetch() ?: null;
}

function get_neglected_subjects(int $limit = 5): array
{
    $pdo = get_db();
    $stmt = $pdo->prepare(
        "SELECT s.id, s.name,
                COUNT(c.id)                       AS total_cycles,
                COALESCE(SUM(c.study_minutes), 0) AS total_study_minutes,
                MAX(c.created_at)                 AS last_studied_at,
                DATEDIFF(NOW(), MAX(c.created_at)) AS days_since_studied
         FROM subjects s
         LEFT JOIN cycles c ON c.subject_id = s.id AND c.user_id = s.user_id
         WHERE s.is_archived = 0 AND s.user_id = ?
         GROUP BY s.id, s.name
         ORDER BY MAX(c.created_at) IS NULL DESC, MAX(c.created_at) ASC
         LIMIT ?"
    );
    $stmt->bindValue(1, current_user_id(), PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function get_subject_distribution(): array
{
    $pdo = get_db();
    $stmt = $pdo->prepare(
           "SELECT s.id, s.name, s.color_hex,
                   COALESCE(SUM(c.study_minutes + c.revision_minutes), 0) AS total_minutes
            FROM subjects s
                LEFT JOIN cycles c ON c.subject_id = s.id AND c.user_id = s.user_id
                WHERE s.is_archived = 0 AND s.user_id = ?
            GROUP BY s.id, s.name, s.color_hex
            HAVING total_minutes > 0
            ORDER BY total_minutes DESC"
        );
        $stmt->execute([current_user_id()]);
    return $stmt->fetchAll();
}

function get_activity_heatmap(?int $year = null): array
{
    $pdo = get_db();
    $userId = current_user_id();
    $today = new DateTime('today');

    if ($year !== null && $year > 2000) {
        $start = new DateTime("$year-01-01");
        // Align start to preceding Sunday
        while ((int)$start->format('w') !== 0) {
            $start->modify('-1 day');
        }
        $end = new DateTime("$year-12-31");
        // Align end to following Saturday
        while ((int)$end->format('w') !== 6) {
            $end->modify('+1 day');
        }
    } else {
        // Rolling 52-53 weeks ending on the current week's Saturday
        $end = clone $today;
        $dayOfWeek = (int)$end->format('w');
        $daysUntilSat = 6 - $dayOfWeek;
        if ($daysUntilSat > 0) {
            $end->modify("+{$daysUntilSat} days");
        }
        $start = clone $end;
        $start->modify('-364 days');
        while ((int)$start->format('w') !== 0) {
            $start->modify('-1 day');
        }
    }

    $stmt = $pdo->prepare(
        "SELECT
            cycle_date,
            COUNT(id) AS total_cycles,
            COALESCE(SUM(status = 'completed'), 0) AS completed_cycles,
            COALESCE(SUM(study_minutes), 0) AS study_minutes,
            COALESCE(SUM(revision_minutes), 0) AS revision_minutes,
            COALESCE(SUM(study_minutes + revision_minutes), 0) AS total_minutes
         FROM cycles
         WHERE user_id = ? AND cycle_date BETWEEN ? AND ?
         GROUP BY cycle_date"
    );
    $stmt->execute([$userId, $start->format('Y-m-d'), $end->format('Y-m-d')]);
    $rows = $stmt->fetchAll();
    $byDate = [];
    foreach ($rows as $r) {
        $byDate[$r['cycle_date']] = $r;
    }

    $subjStmt = $pdo->prepare(
        "SELECT
            c.cycle_date,
            s.name AS subject_name,
            s.color_hex,
            COALESCE(SUM(c.study_minutes + c.revision_minutes), 0) AS total_minutes
         FROM cycles c
         JOIN subjects s ON s.id = c.subject_id AND s.user_id = c.user_id
         WHERE c.user_id = ? AND c.cycle_date BETWEEN ? AND ?
         GROUP BY c.cycle_date, s.id, s.name, s.color_hex
         ORDER BY total_minutes DESC"
    );
    $subjStmt->execute([$userId, $start->format('Y-m-d'), $end->format('Y-m-d')]);
    $subjRows = $subjStmt->fetchAll();
    $subjectsByDate = [];
    foreach ($subjRows as $sr) {
        $subjectsByDate[$sr['cycle_date']][] = [
            'name'      => $sr['subject_name'],
            'color_hex' => $sr['color_hex'],
            'minutes'   => (int)$sr['total_minutes'],
        ];
    }

    $weeks = [];
    $currentWeek = [];
    $monthLabels = [];
    $lastMonth = null;
    $weekIndex = 0;

    $curr = clone $start;
    $totalActiveDays = 0;
    $totalCompletedCycles = 0;
    $totalAllCycles = 0;
    $totalMinutes = 0;
    $totalStudyMinutes = 0;
    $totalRevisionMinutes = 0;

    while ($curr <= $end) {
        $dateStr = $curr->format('Y-m-d');
        $dayOfWeek = (int)$curr->format('w');
        $monthName = $curr->format('M');
        $isFuture = ($curr > $today);

        if ($curr->format('j') === '1' || $lastMonth === null) {
            if ($lastMonth !== $monthName) {
                $monthLabels[] = [
                    'col'  => $weekIndex,
                    'name' => $monthName,
                ];
                $lastMonth = $monthName;
            }
        }

        $stat = $byDate[$dateStr] ?? null;
        $tMin = $stat ? (int)$stat['total_minutes'] : 0;
        $sMin = $stat ? (int)$stat['study_minutes'] : 0;
        $rMin = $stat ? (int)$stat['revision_minutes'] : 0;
        $cCycles = $stat ? (int)$stat['completed_cycles'] : 0;
        $allCycles = $stat ? (int)$stat['total_cycles'] : 0;

        $level = 0;
        if ($tMin > 0 && !$isFuture) {
            $totalActiveDays++;
            $totalMinutes += $tMin;
            $totalStudyMinutes += $sMin;
            $totalRevisionMinutes += $rMin;
            $totalCompletedCycles += $cCycles;
            $totalAllCycles += $allCycles;

            if ($tMin < 60) $level = 1;
            elseif ($tMin < 120) $level = 2;
            elseif ($tMin < 180) $level = 3;
            else $level = 4;
        }

        $currentWeek[] = [
            'date'             => $dateStr,
            'day'              => $dayOfWeek,
            'day_num'          => (int)$curr->format('j'),
            'month'            => (int)$curr->format('n'),
            'month_name'       => $monthName,
            'year'             => (int)$curr->format('Y'),
            'is_future'        => $isFuture,
            'is_today'         => ($dateStr === $today->format('Y-m-d')),
            'total_cycles'     => $allCycles,
            'completed_cycles' => $cCycles,
            'study_minutes'    => $sMin,
            'revision_minutes' => $rMin,
            'total_minutes'    => $tMin,
            'level'            => $level,
            'subjects'         => $subjectsByDate[$dateStr] ?? [],
        ];

        if (count($currentWeek) === 7) {
            $weeks[] = $currentWeek;
            $currentWeek = [];
            $weekIndex++;
        }

        $curr->modify('+1 day');
    }

    if (!empty($currentWeek)) {
        $weeks[] = $currentWeek;
    }

    $yrStmt = $pdo->prepare("SELECT DISTINCT YEAR(cycle_date) AS yr FROM cycles WHERE user_id = ? ORDER BY yr DESC");
    $yrStmt->execute([$userId]);
    $availableYears = $yrStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $currentYear = (int)$today->format('Y');
    if (!in_array($currentYear, $availableYears, true)) {
        array_unshift($availableYears, $currentYear);
    }

    return [
        'year'                 => $year,
        'start_date'           => $start->format('Y-m-d'),
        'end_date'             => $end->format('Y-m-d'),
        'available_years'      => $availableYears,
        'month_labels'         => $monthLabels,
        'weeks'                => $weeks,
        'summary'              => [
            'total_active_days'      => $totalActiveDays,
            'total_completed_cycles' => $totalCompletedCycles,
            'total_cycles'           => $totalAllCycles,
            'total_minutes'          => $totalMinutes,
            'total_study_minutes'    => $totalStudyMinutes,
            'total_revision_minutes' => $totalRevisionMinutes,
            'total_hours'            => round($totalMinutes / 60, 1),
        ],
    ];
}

function get_quarterly_stats(?int $year = null): array
{
    $pdo = get_db();
    $userId = current_user_id();
    $today = new DateTime('today');
    $currentYear = (int)$today->format('Y');
    $currentQuarter = (int)ceil((int)$today->format('n') / 3);
    $year = ($year !== null && $year > 2000) ? $year : $currentYear;

    $quarterDefs = [
        1 => ['label' => 'Q1', 'months_label' => 'Jan – Mar', 'months' => ['01' => 'Jan', '02' => 'Feb', '03' => 'Mar']],
        2 => ['label' => 'Q2', 'months_label' => 'Apr – Jun', 'months' => ['04' => 'Apr', '05' => 'May', '06' => 'Jun']],
        3 => ['label' => 'Q3', 'months_label' => 'Jul – Sep', 'months' => ['07' => 'Jul', '08' => 'Aug', '09' => 'Sep']],
        4 => ['label' => 'Q4', 'months_label' => 'Oct – Dec', 'months' => ['10' => 'Oct', '11' => 'Nov', '12' => 'Dec']],
    ];

    $stmt = $pdo->prepare(
        "SELECT
            QUARTER(cycle_date) AS q_num,
            DATE_FORMAT(cycle_date, '%m') AS m_num,
            COUNT(id) AS total_cycles,
            COALESCE(SUM(status = 'completed'), 0) AS completed_cycles,
            COALESCE(SUM(study_minutes), 0) AS study_minutes,
            COALESCE(SUM(revision_minutes), 0) AS revision_minutes,
            COALESCE(SUM(study_minutes + revision_minutes), 0) AS total_minutes,
            COUNT(DISTINCT cycle_date) AS active_days
         FROM cycles
         WHERE user_id = ? AND YEAR(cycle_date) = ?
         GROUP BY QUARTER(cycle_date), DATE_FORMAT(cycle_date, '%m')
         ORDER BY q_num ASC, m_num ASC"
    );
    $stmt->execute([$userId, $year]);
    $monthRows = $stmt->fetchAll();

    $monthlyData = [];
    foreach ($monthRows as $r) {
        $monthlyData[$r['m_num']] = $r;
    }

    $quarters = [];
    $annualStudyMinutes = 0;
    $annualRevisionMinutes = 0;
    $annualTotalMinutes = 0;
    $annualCycles = 0;
    $annualCompletedCycles = 0;
    $annualActiveDays = 0;
    $bestQuarterNum = 1;
    $bestQuarterMinutes = -1;

    foreach ($quarterDefs as $qNum => $def) {
        $qStudy = 0;
        $qRevision = 0;
        $qTotal = 0;
        $qCycles = 0;
        $qCompletedCycles = 0;
        $qActiveDays = 0;
        $monthsList = [];

        foreach ($def['months'] as $mStr => $mName) {
            $mData = $monthlyData[$mStr] ?? null;
            $mStudy = $mData ? (int)$mData['study_minutes'] : 0;
            $mRevision = $mData ? (int)$mData['revision_minutes'] : 0;
            $mTotal = $mData ? (int)$mData['total_minutes'] : 0;
            $mCycles = $mData ? (int)$mData['total_cycles'] : 0;
            $mCompCycles = $mData ? (int)$mData['completed_cycles'] : 0;
            $mDays = $mData ? (int)$mData['active_days'] : 0;

            $qStudy += $mStudy;
            $qRevision += $mRevision;
            $qTotal += $mTotal;
            $qCycles += $mCycles;
            $qCompletedCycles += $mCompCycles;
            $qActiveDays += $mDays;

            $monthsList[] = [
                'month_key'        => "$year-$mStr",
                'month_num'        => (int)$mStr,
                'name'             => $mName,
                'study_minutes'    => $mStudy,
                'revision_minutes' => $mRevision,
                'total_minutes'    => $mTotal,
                'study_hours'      => round($mStudy / 60, 2),
                'revision_hours'   => round($mRevision / 60, 2),
                'total_hours'      => round($mTotal / 60, 2),
                'total_cycles'     => $mCycles,
                'completed_cycles' => $mCompCycles,
                'active_days'      => $mDays,
            ];
        }

        $annualStudyMinutes += $qStudy;
        $annualRevisionMinutes += $qRevision;
        $annualTotalMinutes += $qTotal;
        $annualCycles += $qCycles;
        $annualCompletedCycles += $qCompletedCycles;
        $annualActiveDays += $qActiveDays;

        if ($qTotal > $bestQuarterMinutes) {
            $bestQuarterMinutes = $qTotal;
            $bestQuarterNum = $qNum;
        }

        $quarters[$qNum] = [
            'quarter'          => $qNum,
            'label'            => $def['label'],
            'months_label'     => $def['months_label'],
            'is_current'       => ($year === $currentYear && $qNum === $currentQuarter),
            'study_minutes'    => $qStudy,
            'revision_minutes' => $qRevision,
            'total_minutes'    => $qTotal,
            'study_hours'      => round($qStudy / 60, 2),
            'revision_hours'   => round($qRevision / 60, 2),
            'total_hours'      => round($qTotal / 60, 2),
            'total_cycles'     => $qCycles,
            'completed_cycles' => $qCompletedCycles,
            'active_days'      => $qActiveDays,
            'months'           => $monthsList,
        ];
    }

    $yrStmt = $pdo->prepare("SELECT DISTINCT YEAR(cycle_date) AS yr FROM cycles WHERE user_id = ? ORDER BY yr DESC");
    $yrStmt->execute([$userId]);
    $availableYears = $yrStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    if (!in_array($currentYear, $availableYears, true)) {
        array_unshift($availableYears, $currentYear);
    }

    return [
        'year'                    => $year,
        'current_year'            => $currentYear,
        'current_quarter'         => $currentQuarter,
        'available_years'         => $availableYears,
        'annual_study_minutes'    => $annualStudyMinutes,
        'annual_revision_minutes' => $annualRevisionMinutes,
        'annual_total_minutes'    => $annualTotalMinutes,
        'annual_study_hours'      => round($annualStudyMinutes / 60, 2),
        'annual_revision_hours'   => round($annualRevisionMinutes / 60, 2),
        'annual_total_hours'      => round($annualTotalMinutes / 60, 2),
        'annual_cycles'           => $annualCycles,
        'annual_completed_cycles' => $annualCompletedCycles,
        'annual_active_days'      => $annualActiveDays,
        'best_quarter'            => $bestQuarterNum,
        'quarters'                => array_values($quarters),
    ];
}

/* ---------- settings ---------- */

function get_app_settings(): array
{
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM app_settings WHERE user_id = ?');
    $stmt->execute([current_user_id()]);
    return $stmt->fetch() ?: [
        'theme' => 'light', 'default_study_minutes' => 90,
        'default_revision_minutes' => 30, 'timezone' => 'Asia/Dhaka',
    ];
}

/* ---------- misc ---------- */

function json_out($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}
