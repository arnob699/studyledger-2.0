<?php
require_once __DIR__ . '/../includes/functions.php';
require_auth(true);

$month = $_GET['month'] ?? null;
$day = $_GET['day'] ?? null;
$quarterYear = (isset($_GET['quarter_year']) && is_numeric($_GET['quarter_year'])) ? (int)$_GET['quarter_year'] : null;
$heatmapYear = (isset($_GET['heatmap_year']) && is_numeric($_GET['heatmap_year'])) ? (int)$_GET['heatmap_year'] : null;

if ($day !== null) {
    json_out([
        'date' => $day,
        'breakdown' => get_day_breakdown($day),
    ]);
}

$today  = get_today_stats();
$goal   = get_active_goal('daily');
$streak = get_streak();

json_out([
    'today'        => $today,
    'monthly'      => get_monthly_stats($month),
    'quarterly'    => get_quarterly_stats($quarterYear),
    'heatmap'      => get_activity_heatmap($heatmapYear),
    'week'         => get_week_stats(),
    'distribution' => get_subject_distribution(),
    'neglected'    => get_neglected_subjects(5),
    'recent'       => get_recent_cycles(8),
    'goal'         => $goal,
    'weekly_goal'  => get_active_goal('weekly'),
    'streak'       => $streak,
    'active_cycle' => get_active_cycle(),
]);

