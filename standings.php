<?php
/**
 * 順位表（リーダーボード）ページ。全試合結果を集計して、チームごとの
 * 勝敗・得点・勝率をランキング表示する。
 */
require_once 'db.php';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/* チーム一覧を取得する。 */
$teams = $pdo->query("SELECT id, name FROM teams ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$teamNames = [];
foreach ($teams as $t) {
    $teamNames[(int) $t['id']] = $t['name'];
}

/* 試合結果を集計する。 */
$standings = [];
foreach ($teams as $t) {
    $standings[(int) $t['id']] = [
        'name'          => $t['name'],
        'played'        => 0,
        'wins'          => 0,
        'losses'        => 0,
        'points_for'    => 0,
        'points_against'=> 0,
    ];
}

$hasTable = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='molkky_matches'")->fetch();
if ($hasTable) {
    $matches = $pdo->query("SELECT * FROM molkky_matches WHERE status = 'finished'")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($matches as $m) {
        $t1 = (int) $m['team1_id'];
        $t2 = (int) $m['team2_id'];
        $s1 = (int) $m['team1_score'];
        $s2 = (int) $m['team2_score'];
        $winner = (int) $m['winner_team_id'];

        if (isset($standings[$t1])) {
            $standings[$t1]['played']++;
            $standings[$t1]['points_for'] += $s1;
            $standings[$t1]['points_against'] += $s2;
            if ($winner === $t1) { $standings[$t1]['wins']++; }
            else                 { $standings[$t1]['losses']++; }
        }
        if (isset($standings[$t2])) {
            $standings[$t2]['played']++;
            $standings[$t2]['points_for'] += $s2;
            $standings[$t2]['points_against'] += $s1;
            if ($winner === $t2) { $standings[$t2]['wins']++; }
            else                 { $standings[$t2]['losses']++; }
        }
    }
}

/* 勝数(降順) → 得失点差(降順) → 得点(降順) でソートする。 */
usort($standings, function ($a, $b) {
    $cmp = $b['wins'] <=> $a['wins'];
    if ($cmp !== 0) return $cmp;
    $diffA = $a['points_for'] - $a['points_against'];
    $diffB = $b['points_for'] - $b['points_against'];
    $cmp = $diffB <=> $diffA;
    if ($cmp !== 0) return $cmp;
    return $b['points_for'] <=> $a['points_for'];
});
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>順位表 — モルック大会</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'nav.php'; ?>
    <div class="content_box content_box--wide">
        <div class="page-title">
            <h2>順位表</h2>
            <p class="subtitle">全試合結果に基づくチームランキング</p>
        </div>

        <?php if (empty($standings)): ?>
            <div class="card">
                <p class="empty">まだチームがありません。ダッシュボードでチームを作成して下さい。</p>
                <div class="links"><a href="index.php">ダッシュボードへ</a></div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="table-toolbar">
                    <h3>🏆 リーダーボード</h3>
                    <div class="table-toolbar__actions">
                        <button type="button" class="btn-icon" onclick="window.print()" title="印刷">
                            🖨️ <span class="btn-icon__text">印刷</span>
                        </button>
                        <a href="export.php?type=standings" class="btn-icon" title="CSV出力">
                            📊 <span class="btn-icon__text">CSV</span>
                        </a>
                    </div>
                </div>

                <div class="table-wrap">
                    <table class="standings-table" id="standingsTable">
                        <thead>
                            <tr>
                                <th class="col-rank">#</th>
                                <th class="col-team">チーム</th>
                                <th class="col-num">試合</th>
                                <th class="col-num">勝</th>
                                <th class="col-num">負</th>
                                <th class="col-num">得点</th>
                                <th class="col-num">失点</th>
                                <th class="col-num">差</th>
                                <th class="col-rate">勝率</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($standings as $rank => $s):
                                $r = $rank + 1;
                                $diff = $s['points_for'] - $s['points_against'];
                                $diffStr = ($diff > 0 ? '+' : '') . $diff;
                                $rate = $s['played'] > 0
                                    ? round(($s['wins'] / $s['played']) * 100) . '%'
                                    : '—';
                                $rankClass = '';
                                if ($r === 1 && $s['wins'] > 0) $rankClass = 'rank-gold';
                                elseif ($r === 2 && $s['wins'] > 0) $rankClass = 'rank-silver';
                                elseif ($r === 3 && $s['wins'] > 0) $rankClass = 'rank-bronze';
                            ?>
                                <tr class="<?php echo $rankClass; ?>">
                                    <td class="col-rank">
                                        <?php if ($r <= 3 && $s['wins'] > 0): ?>
                                            <span class="rank-medal"><?php echo ['🥇','🥈','🥉'][$r - 1]; ?></span>
                                        <?php else: ?>
                                            <?php echo $r; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-team"><?php echo e($s['name']); ?></td>
                                    <td class="col-num"><?php echo $s['played']; ?></td>
                                    <td class="col-num col-wins"><?php echo $s['wins']; ?></td>
                                    <td class="col-num col-losses"><?php echo $s['losses']; ?></td>
                                    <td class="col-num"><?php echo $s['points_for']; ?></td>
                                    <td class="col-num"><?php echo $s['points_against']; ?></td>
                                    <td class="col-num <?php echo $diff > 0 ? 'col-diff-pos' : ($diff < 0 ? 'col-diff-neg' : ''); ?>">
                                        <?php echo $diffStr; ?>
                                    </td>
                                    <td class="col-rate"><?php echo $rate; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <div class="links">
            <a href="history.php">試合履歴</a>
            <a href="index.php">ダッシュボードへ</a>
        </div>
    </div>
</body>
</html>
