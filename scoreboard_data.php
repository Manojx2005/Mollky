<?php
/**
 * OBS 用オーバーレイ(overlay.php)が定期取得するスコアボードデータを JSON で返す。
 * 進行中の試合を優先し、無ければ直近の試合の状態を返す。
 */
require 'db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');

/* モルックのルール定数（match.php と同じ値）。 */
const TARGET_SCORE = 50;
const MAX_MISSES   = 3;

/* チーム名を取得する（無ければ「Team {id}」で代替）。 */
function teamName(PDO $pdo, int $teamId): string
{
    $stmt = $pdo->prepare("SELECT name FROM teams WHERE id = ?");
    $stmt->execute([$teamId]);
    $name = $stmt->fetchColumn();
    return $name !== false ? (string) $name : ('Team ' . $teamId);
}

/* チームの選手名を id 順（＝投げる順番）で取得する。 */
function teamPlayers(PDO $pdo, int $teamId): array
{
    $stmt = $pdo->prepare(
        "SELECT s.name FROM team_members tm
         JOIN students s ON s.id = tm.student_id
         WHERE tm.team_id = ? ORDER BY s.id"
    );
    $stmt->execute([$teamId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

$response = ['state' => 'idle', 'target' => TARGET_SCORE, 'maxMisses' => MAX_MISSES];

/* molkky_matches が未作成なら待機(idle)状態を返す。 */
$hasTable = $pdo
    ->query("SELECT name FROM sqlite_master WHERE type='table' AND name='molkky_matches'")
    ->fetch();

if ($hasTable) {
    /* 進行中の試合を優先し、無ければ直近の試合を結果表示用に取得する。 */
    $match = $pdo->query(
        "SELECT * FROM molkky_matches WHERE status = 'ongoing' ORDER BY id DESC LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);
    if (!$match) {
        $match = $pdo->query(
            "SELECT * FROM molkky_matches ORDER BY id DESC LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC);
    }

    if ($match) {
        $t1 = (int) $match['team1_id'];
        $t2 = (int) $match['team2_id'];
        $finished = $match['status'] === 'finished';
        $winnerId = (int) $match['winner_team_id'];
        $turn = ($match['current_turn'] ?? 'team1') === 'team2' ? 'team2' : 'team1';

        /* 今投げる選手を特定する。 */
        $currentPlayer = null;
        if (!$finished) {
            $activeTeamId = $turn === 'team1' ? $t1 : $t2;
            $idx = (int) ($turn === 'team1'
                ? ($match['team1_player_idx'] ?? 0)
                : ($match['team2_player_idx'] ?? 0));
            $players = teamPlayers($pdo, $activeTeamId);
            if ($players) {
                $currentPlayer = $players[$idx % count($players)];
            }
        }

        $response['state']         = $finished ? 'finished' : 'live';
        $response['currentPlayer'] = $currentPlayer;
        $response['team1'] = [
            'name'   => teamName($pdo, $t1),
            'score'  => (int) $match['team1_score'],
            'misses' => (int) ($match['team1_misses'] ?? 0),
            'active' => !$finished && $turn === 'team1',
            'winner' => $finished && $winnerId === $t1,
        ];
        $response['team2'] = [
            'name'   => teamName($pdo, $t2),
            'score'  => (int) $match['team2_score'],
            'misses' => (int) ($match['team2_misses'] ?? 0),
            'active' => !$finished && $turn === 'team2',
            'winner' => $finished && $winnerId === $t2,
        ];
    }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
