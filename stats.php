<?php
/**
 * 詳細統計・アナリティクス ページ。
 * 個人ランキング（得点王・MVP）、スキットル命中分析、チーム連勝（Form Guide）を表示。
 */
require_once 'db.php';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/* チーム名マップ */
$teams = $pdo->query("SELECT id, name FROM teams ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$teamNames = [];
foreach ($teams as $t) {
    $teamNames[(int) $t['id']] = $t['name'];
}

/* 生徒名マップ */
$students = $pdo->query("SELECT id, name FROM students")->fetchAll(PDO::FETCH_ASSOC);
$studentNames = [];
foreach ($students as $s) {
    $studentNames[(int) $s['id']] = $s['name'];
}

/* 投球履歴テーブルの列拡張 */
$pdo->exec("CREATE TABLE IF NOT EXISTS `molkky_throws` (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    match_id INTEGER NOT NULL,
    team1_score INTEGER NOT NULL,
    team2_score INTEGER NOT NULL,
    team1_misses INTEGER NOT NULL,
    team2_misses INTEGER NOT NULL,
    team1_player_idx INTEGER NOT NULL,
    team2_player_idx INTEGER NOT NULL,
    current_turn VARCHAR(10) NOT NULL,
    status VARCHAR(20) NOT NULL,
    winner_team_id INTEGER NULL,
    player_name TEXT NULL,
    points_scored INTEGER DEFAULT 0,
    pins_hit TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

foreach ([
    "ADD COLUMN `player_name` TEXT NULL",
    "ADD COLUMN `points_scored` INTEGER DEFAULT 0",
    "ADD COLUMN `pins_hit` TEXT NULL",
] as $alter) {
    try { $pdo->exec("ALTER TABLE `molkky_throws` $alter"); } catch (PDOException $e) {}
}

/* 個人スタッツ集計 */
$playerStats = [];
$hasThrows = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='molkky_throws'")->fetch();
if ($hasThrows) {
    $throws = $pdo->query(
        "SELECT player_name, points_scored, pins_hit FROM molkky_throws WHERE player_name IS NOT NULL AND player_name != ''"
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($throws as $t) {
        $p = $t['player_name'];
        $pts = (int) $t['points_scored'];
        if (!isset($playerStats[$p])) {
            $playerStats[$p] = [
                'name' => $p,
                'throws' => 0,
                'points' => 0,
                'max_single' => 0,
                'misses' => 0,
            ];
        }
        $playerStats[$p]['throws']++;
        $playerStats[$p]['points'] += $pts;
        if ($pts > $playerStats[$p]['max_single']) {
            $playerStats[$p]['max_single'] = $pts;
        }
        if ($pts === 0) {
            $playerStats[$p]['misses']++;
        }
    }
}

/* 得点順（降順）でソート */
usort($playerStats, fn($a, $b) => $b['points'] <=> $a['points']);

/* スキットル命中頻度集計 (1-12) */
$skittleHits = array_fill(1, 12, 0);
if ($hasThrows) {
    $pinsRaw = $pdo->query("SELECT pins_hit FROM molkky_throws WHERE pins_hit IS NOT NULL AND pins_hit != ''")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($pinsRaw as $pStr) {
        $pins = array_filter(array_map('intval', explode(',', $pStr)));
        foreach ($pins as $pin) {
            if ($pin >= 1 && $pin <= 12) {
                $skittleHits[$pin]++;
            }
        }
    }
}
$maxSkittleHit = max($skittleHits) ?: 1;

/* チーム連勝ガイド (Form Guide) */
$teamForms = [];
$hasMatches = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='molkky_matches'")->fetch();
if ($hasMatches) {
    $matches = $pdo->query("SELECT * FROM molkky_matches WHERE status = 'finished' ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($matches as $m) {
        $t1 = (int) $m['team1_id'];
        $t2 = (int) $m['team2_id'];
        $w = (int) $m['winner_team_id'];

        if ($t1 > 0) $teamForms[$t1][] = ($w === $t1) ? 'W' : 'L';
        if ($t2 > 0) $teamForms[$t2][] = ($w === $t2) ? 'W' : 'L';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>詳細統計＆アナリティクス — モルック</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'nav.php'; ?>
    <div class="content_box content_box--wide">
        <div class="page-title">
            <h2>📊 詳細統計 ＆ アナリティクス</h2>
            <p class="subtitle">個人得点ランキング・スキットル命中率・チーム連勝ガイド</p>
        </div>

        <!-- 1. 個人成績ランキング -->
        <div class="card">
            <div class="table-toolbar">
                <h3>👑 個人得点ランキング (Top Players)</h3>
                <div class="table-toolbar__actions">
                    <button type="button" class="btn-icon" onclick="window.print()">🖨️ 印刷</button>
                </div>
            </div>

            <?php if (empty($playerStats)): ?>
                <p class="empty">まだ投球データが記録されていません。試合を進めると個人統計が表示されます。</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="standings-table">
                        <thead>
                            <tr>
                                <th class="col-rank">#</th>
                                <th class="col-team">選手名</th>
                                <th class="col-num">投球数</th>
                                <th class="col-num">総得点</th>
                                <th class="col-num">平均</th>
                                <th class="col-num">最高</th>
                                <th class="col-num">ミス</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($playerStats, 0, 15) as $rank => $ps):
                                $r = $rank + 1;
                                $avg = $ps['throws'] > 0 ? round($ps['points'] / $ps['throws'], 1) : 0;
                                $medal = $r === 1 ? '🥇 MVP' : ($r === 2 ? '🥈 2位' : ($r === 3 ? '🥉 3位' : $r));
                            ?>
                                <tr class="<?php echo $r === 1 ? 'rank-gold' : ($r === 2 ? 'rank-silver' : ($r === 3 ? 'rank-bronze' : '')); ?>">
                                    <td class="col-rank"><strong><?php echo $medal; ?></strong></td>
                                    <td class="col-team"><strong><?php echo e($ps['name']); ?></strong></td>
                                    <td class="col-num"><?php echo $ps['throws']; ?></td>
                                    <td class="col-num col-wins"><?php echo $ps['points']; ?> 点</td>
                                    <td class="col-num"><?php echo $avg; ?></td>
                                    <td class="col-num"><?php echo $ps['max_single']; ?></td>
                                    <td class="col-num col-losses"><?php echo $ps['misses']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- 2. チーム Form Guide (調子) -->
        <div class="card">
            <h3>🔥 チーム直近フォーム (Form Guide)</h3>
            <?php if (empty($teams)): ?>
                <p class="empty">チームが登録されていません。</p>
            <?php else: ?>
                <div class="form-guide-list">
                    <?php foreach ($teams as $t):
                        $tid = (int) $t['id'];
                        $history = $teamForms[$tid] ?? [];
                        $recent = array_slice($history, -5);
                    ?>
                        <div class="form-guide-item">
                            <span class="form-guide-team"><?php echo e($t['name']); ?></span>
                            <div class="form-guide-badges">
                                <?php if (empty($recent)): ?>
                                    <span class="form-badge form-badge--none">未試合</span>
                                <?php else: ?>
                                    <?php foreach ($recent as $res): ?>
                                        <span class="form-badge form-badge--<?php echo strtolower($res); ?>">
                                            <?php echo $res === 'W' ? '勝' : '負'; ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- 3. スキットル命中チャート -->
        <div class="card">
            <h3>🎯 スキットル別 命中チャート (#1 ~ #12)</h3>
            <p class="subtitle" style="margin-bottom:14px;">どのピンが何回倒されたかの頻度グラフ</p>
            <div class="skittle-chart-grid">
                <?php for ($pin = 1; $pin <= 12; $pin++):
                    $count = $skittleHits[$pin];
                    $percent = round(($count / $maxSkittleHit) * 100);
                ?>
                    <div class="skittle-bar-item">
                        <div class="skittle-bar-wrapper">
                            <div class="skittle-bar-fill" style="height: <?php echo max($percent, 6); ?>%">
                                <span class="skittle-bar-count"><?php echo $count; ?></span>
                            </div>
                        </div>
                        <span class="skittle-bar-label">#<?php echo $pin; ?></span>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <div class="links">
            <a href="certificates.php">🏆 賞状発行</a>
            <a href="standings.php">順位表</a>
            <a href="index.php">ダッシュボードへ</a>
        </div>
    </div>
</body>
</html>
