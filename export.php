<?php
/**
 * CSV エクスポートエンドポイント。?type= で出力対象を切り替える。
 *   - teams     : チーム名とメンバー一覧
 *   - matches   : 全試合結果
 *   - students  : 生徒名簿
 *   - standings  : 順位表（集計済み）
 */
require_once 'db.php';

$type = $_GET['type'] ?? '';

switch ($type) {
    case 'teams':
        exportTeams($pdo);
        break;
    case 'matches':
        exportMatches($pdo);
        break;
    case 'students':
        exportStudents($pdo);
        break;
    case 'standings':
        exportStandings($pdo);
        break;
    default:
        http_response_code(400);
        echo 'Invalid export type. Use ?type=teams|matches|students|standings';
        exit;
}

/* --- エクスポート関数群 --- */

function sendCsvHeaders(string $filename): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');
    // UTF-8 BOM（Excel で日本語を文字化けさせない）。
    echo "\xEF\xBB\xBF";
}

function exportTeams(PDO $pdo): void
{
    sendCsvHeaders('molkky_teams_' . date('Ymd_His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['チーム名', 'メンバー名', '学籍番号']);

    $stmt = $pdo->query(
        "SELECT t.name as team_name, s.name as student_name, s.id as student_id
         FROM teams t
         JOIN team_members tm ON t.id = tm.team_id
         JOIN students s ON tm.student_id = s.id
         ORDER BY t.id, s.id"
    );
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, [$row['team_name'], $row['student_name'], $row['student_id']]);
    }
    fclose($out);
    exit;
}

function exportMatches(PDO $pdo): void
{
    sendCsvHeaders('molkky_matches_' . date('Ymd_His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['試合ID', 'チーム1', 'スコア1', 'チーム2', 'スコア2', '勝者', 'ステータス', '日時']);

    $hasTable = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='molkky_matches'")->fetch();
    if (!$hasTable) { fclose($out); exit; }

    /* チーム名マップ */
    $teamNames = [];
    foreach ($pdo->query("SELECT id, name FROM teams")->fetchAll(PDO::FETCH_ASSOC) as $t) {
        $teamNames[(int) $t['id']] = $t['name'];
    }

    $stmt = $pdo->query("SELECT * FROM molkky_matches ORDER BY id DESC");
    while ($m = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $t1 = (int) $m['team1_id'];
        $t2 = (int) $m['team2_id'];
        $winner = '';
        if ($m['status'] === 'finished' && $m['winner_team_id']) {
            $winner = $teamNames[(int) $m['winner_team_id']] ?? 'Team ' . $m['winner_team_id'];
        }
        $statusJp = $m['status'] === 'finished' ? '完了' : ($m['status'] === 'ongoing' ? '進行中' : $m['status']);
        fputcsv($out, [
            $m['id'],
            $teamNames[$t1] ?? 'Team ' . $t1,
            $m['team1_score'],
            $teamNames[$t2] ?? 'Team ' . $t2,
            $m['team2_score'],
            $winner,
            $statusJp,
            $m['created_at'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

function exportStudents(PDO $pdo): void
{
    sendCsvHeaders('molkky_students_' . date('Ymd_His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['学籍番号', '名前', 'クラス', '参加状態']);

    $stmt = $pdo->query(
        "SELECT s.id, s.name, c.name as class_name, s.is_active
         FROM students s
         LEFT JOIN classes c ON s.class_id = c.id
         ORDER BY s.id"
    );
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, [
            $row['id'],
            $row['name'],
            $row['class_name'] ?? '',
            $row['is_active'] ? '参加中' : '未参加',
        ]);
    }
    fclose($out);
    exit;
}

function exportStandings(PDO $pdo): void
{
    sendCsvHeaders('molkky_standings_' . date('Ymd_His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['順位', 'チーム名', '試合数', '勝', '負', '得点', '失点', '得失点差', '勝率']);

    /* チーム集計 */
    $teams = $pdo->query("SELECT id, name FROM teams ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $standings = [];
    foreach ($teams as $t) {
        $standings[(int) $t['id']] = [
            'name' => $t['name'], 'played' => 0, 'wins' => 0, 'losses' => 0,
            'pf' => 0, 'pa' => 0,
        ];
    }

    $hasTable = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='molkky_matches'")->fetch();
    if ($hasTable) {
        $matches = $pdo->query("SELECT * FROM molkky_matches WHERE status = 'finished'")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($matches as $m) {
            $t1 = (int) $m['team1_id']; $t2 = (int) $m['team2_id'];
            $w = (int) $m['winner_team_id'];
            if (isset($standings[$t1])) {
                $standings[$t1]['played']++; $standings[$t1]['pf'] += (int)$m['team1_score']; $standings[$t1]['pa'] += (int)$m['team2_score'];
                if ($w === $t1) $standings[$t1]['wins']++; else $standings[$t1]['losses']++;
            }
            if (isset($standings[$t2])) {
                $standings[$t2]['played']++; $standings[$t2]['pf'] += (int)$m['team2_score']; $standings[$t2]['pa'] += (int)$m['team1_score'];
                if ($w === $t2) $standings[$t2]['wins']++; else $standings[$t2]['losses']++;
            }
        }
    }

    usort($standings, function ($a, $b) {
        $c = $b['wins'] <=> $a['wins'];
        return $c !== 0 ? $c : ($b['pf'] - $b['pa']) <=> ($a['pf'] - $a['pa']);
    });

    foreach ($standings as $rank => $s) {
        $diff = $s['pf'] - $s['pa'];
        $rate = $s['played'] > 0 ? round(($s['wins'] / $s['played']) * 100) . '%' : '—';
        fputcsv($out, [$rank + 1, $s['name'], $s['played'], $s['wins'], $s['losses'], $s['pf'], $s['pa'], ($diff > 0 ? '+' : '') . $diff, $rate]);
    }
    fclose($out);
    exit;
}
