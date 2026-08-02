<?php
/**
 * 試合履歴ページ。全試合を新しい順に一覧表示し、進行中の試合への復帰と
 * 完了試合の結果確認を行える。ステータスフィルター機能付き。
 */
require_once 'db.php';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/* チーム名を取得する。 */
$teams = $pdo->query("SELECT id, name FROM teams ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$teamNames = [];
foreach ($teams as $t) {
    $teamNames[(int) $t['id']] = $t['name'];
}

/* フィルター。 */
$filter = $_GET['filter'] ?? 'all';
if (!in_array($filter, ['all', 'ongoing', 'finished'], true)) {
    $filter = 'all';
}

/* 試合一覧を取得する。 */
$matches = [];
$hasTable = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='molkky_matches'")->fetch();
if ($hasTable) {
    $sql = "SELECT * FROM molkky_matches";
    if ($filter !== 'all') {
        $sql .= " WHERE status = " . $pdo->quote($filter);
    }
    $sql .= " ORDER BY id DESC";
    $matches = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

$counts = ['all' => 0, 'ongoing' => 0, 'finished' => 0];
if ($hasTable) {
    $counts['all'] = (int) $pdo->query("SELECT COUNT(*) FROM molkky_matches")->fetchColumn();
    $counts['ongoing'] = (int) $pdo->query("SELECT COUNT(*) FROM molkky_matches WHERE status='ongoing'")->fetchColumn();
    $counts['finished'] = (int) $pdo->query("SELECT COUNT(*) FROM molkky_matches WHERE status='finished'")->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>試合履歴 — モルック大会</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'nav.php'; ?>
    <div class="content_box content_box--wide">
        <div class="page-title">
            <h2>試合履歴</h2>
            <p class="subtitle">全試合の結果を時系列で確認</p>
        </div>

        <div class="card">
            <div class="table-toolbar">
                <h3>📋 試合一覧</h3>
                <div class="table-toolbar__actions">
                    <button type="button" class="btn-icon" onclick="window.print()" title="印刷">
                        🖨️ <span class="btn-icon__text">印刷</span>
                    </button>
                    <a href="export.php?type=matches" class="btn-icon" title="CSV出力">
                        📊 <span class="btn-icon__text">CSV</span>
                    </a>
                </div>
            </div>

            <div class="history-filters">
                <a href="history.php?filter=all"
                   class="filter-chip <?php echo $filter === 'all' ? 'is-active' : ''; ?>">
                    すべて <span class="filter-chip__count"><?php echo $counts['all']; ?></span>
                </a>
                <a href="history.php?filter=ongoing"
                   class="filter-chip filter-chip--ongoing <?php echo $filter === 'ongoing' ? 'is-active' : ''; ?>">
                    進行中 <span class="filter-chip__count"><?php echo $counts['ongoing']; ?></span>
                </a>
                <a href="history.php?filter=finished"
                   class="filter-chip filter-chip--finished <?php echo $filter === 'finished' ? 'is-active' : ''; ?>">
                    完了 <span class="filter-chip__count"><?php echo $counts['finished']; ?></span>
                </a>
            </div>

            <?php if (empty($matches)): ?>
                <p class="empty">
                    <?php if ($filter === 'all'): ?>
                        まだ試合が記録されていません。
                    <?php else: ?>
                        「<?php echo $filter === 'ongoing' ? '進行中' : '完了'; ?>」の試合はありません。
                    <?php endif; ?>
                </p>
            <?php else: ?>
                <div class="history-list">
                    <?php foreach ($matches as $m):
                        $t1 = (int) $m['team1_id'];
                        $t2 = (int) $m['team2_id'];
                        $name1 = $teamNames[$t1] ?? ('Team ' . $t1);
                        $name2 = $teamNames[$t2] ?? ('Team ' . $t2);
                        $s1 = (int) $m['team1_score'];
                        $s2 = (int) $m['team2_score'];
                        $finished = $m['status'] === 'finished';
                        $winnerId = (int) $m['winner_team_id'];
                        $winnerName = $winnerId === $t1 ? $name1 : $name2;
                        $date = date('Y/m/d H:i', strtotime($m['created_at']));
                    ?>
                        <div class="history-item <?php echo $finished ? 'is-finished' : 'is-ongoing'; ?>">
                            <div class="history-item__status">
                                <?php if ($finished): ?>
                                    <span class="status-badge status-badge--finished">完了</span>
                                <?php else: ?>
                                    <span class="status-badge status-badge--ongoing">進行中</span>
                                <?php endif; ?>
                                <span class="history-item__date"><?php echo $date; ?></span>
                            </div>
                            <div class="history-item__teams">
                                <span class="history-team <?php echo $finished && $winnerId === $t1 ? 'is-winner' : ''; ?>">
                                    <?php echo e($name1); ?>
                                </span>
                                <span class="history-score">
                                    <strong><?php echo $s1; ?></strong>
                                    <span class="history-vs">-</span>
                                    <strong><?php echo $s2; ?></strong>
                                </span>
                                <span class="history-team <?php echo $finished && $winnerId === $t2 ? 'is-winner' : ''; ?>">
                                    <?php echo e($name2); ?>
                                </span>
                            </div>
                            <?php if ($finished): ?>
                                <div class="history-item__winner">🏆 <?php echo e($winnerName); ?></div>
                            <?php endif; ?>
                            <div class="history-item__actions">
                                <a href="match.php?id=<?php echo (int) $m['id']; ?>" class="btn-sm">
                                    <?php echo $finished ? '結果を見る' : '試合を再開'; ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="links">
            <a href="standings.php">順位表</a>
            <a href="match.php">新しい試合</a>
            <a href="index.php">ダッシュボードへ</a>
        </div>
    </div>
</body>
</html>
