<?php
/**
 * 大会（トーナメント）ページ。
 * フットボール方式のノックアウト（トーナメント表）および
 * 総当たり戦（ラウンドロビン）の対戦表を自動生成・ビジュアル表示し、
 * 試合進行と自動勝者進出を管理する。
 */
require_once 'db.php';
session_start();

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$message = "";
$isError = false;

/* molkky_matches テーブル定義＆拡張列追加 */
$pdo->exec("CREATE TABLE IF NOT EXISTS `molkky_matches` (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tournament_id INTEGER NOT NULL,
    team1_id INTEGER NOT NULL DEFAULT 0,
    team2_id INTEGER NOT NULL DEFAULT 0,
    team1_score INTEGER NOT NULL DEFAULT 0,
    team2_score INTEGER NOT NULL DEFAULT 0,
    winner_team_id INTEGER NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    current_turn VARCHAR(10) NOT NULL DEFAULT 'team1',
    team1_misses INTEGER NOT NULL DEFAULT 0,
    team2_misses INTEGER NOT NULL DEFAULT 0,
    team1_player_idx INTEGER NOT NULL DEFAULT 0,
    team2_player_idx INTEGER NOT NULL DEFAULT 0,
    round_no INTEGER NOT NULL DEFAULT 1,
    next_match_id INTEGER NULL,
    next_match_slot INTEGER NULL,
    third_place_match_id INTEGER NULL,
    match_label VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

foreach ([
    "ADD COLUMN `round_no` INTEGER NOT NULL DEFAULT 1",
    "ADD COLUMN `next_match_id` INTEGER NULL",
    "ADD COLUMN `next_match_slot` INTEGER NULL",
    "ADD COLUMN `third_place_match_id` INTEGER NULL",
    "ADD COLUMN `match_label` VARCHAR(50) NULL",
] as $alter) {
    try { $pdo->exec("ALTER TABLE `molkky_matches` $alter"); } catch (PDOException $e) {}
}

/* 大会基本情報の取得 */
$tournament = $pdo->query("SELECT * FROM tournaments LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$format = $_GET['format'] ?? ($tournament['format'] ?? 'knockout');
if (!in_array($format, ['knockout', 'round_robin'], true)) {
    $format = 'knockout';
}

/* チーム一覧を取得する。 */
$teams = $pdo->query("SELECT id, name FROM teams ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$teamNames = [0 => '（TBD）'];
foreach ($teams as $t) {
    $teamNames[(int) $t['id']] = $t['name'];
}

/**
 * ラウンドロビンの対戦表を生成する。
 */
function generateRoundRobin(array $teamIds): array
{
    $n = count($teamIds);
    if ($n < 2) return [];

    if ($n % 2 !== 0) {
        $teamIds[] = 0; // BYE
        $n++;
    }

    $rounds = [];
    $fixed = $teamIds[0];
    $rotating = array_slice($teamIds, 1);

    for ($r = 0; $r < $n - 1; $r++) {
        $round = [];
        $current = array_merge([$fixed], $rotating);
        for ($i = 0; $i < $n / 2; $i++) {
            $home = $current[$i];
            $away = $current[$n - 1 - $i];
            if ($home !== 0 && $away !== 0) {
                $round[] = [$home, $away];
            }
        }
        $rounds[] = $round;
        array_unshift($rotating, array_pop($rotating));
    }
    return $rounds;
}

/**
 * フットボールスタイルのノックアウト（トーナメント戦）ツリーを生成する。
 */
function generateKnockoutBracket(PDO $pdo, int $tournamentId, array $teamIds): int
{
    $n = count($teamIds);
    if ($n < 2) return 0;

    shuffle($teamIds);

    /* 2の累乗に拡張する（2, 4, 8, 16 ...） */
    $bracketSize = 1;
    while ($bracketSize < $n) {
        $bracketSize *= 2;
    }

    /* BYE の数を計算 */
    $byes = $bracketSize - $n;
    $numRounds = (int) log($bracketSize, 2);

    /* ラウンドごとのラベル名 */
    $getRoundLabel = function (int $r, int $totalR) {
        $fromFinal = $totalR - $r;
        if ($fromFinal === 0) return '🏆 決勝戦';
        if ($fromFinal === 1) return '準決勝';
        if ($fromFinal === 2) return '準々決勝';
        return 'ラウンド ' . ($r + 1);
    };

    /* 既存の試合をクリア */
    $pdo->exec("DELETE FROM molkky_matches");
    if ($pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='molkky_throws'")->fetch()) {
        $pdo->exec("DELETE FROM molkky_throws");
    }

    /* 1. 決勝戦を作成 */
    $stmt = $pdo->prepare(
        "INSERT INTO molkky_matches (tournament_id, team1_id, team2_id, status, round_no, match_label)
         VALUES (?, 0, 0, 'pending', ?, '🏆 決勝戦')"
    );
    $stmt->execute([$tournamentId, $numRounds]);
    $finalMatchId = $pdo->lastInsertId();

    /* 2. 3位決定戦を作成（4チーム以上の場合） */
    $thirdPlaceMatchId = null;
    if ($bracketSize >= 4) {
        $stmt3 = $pdo->prepare(
            "INSERT INTO molkky_matches (tournament_id, team1_id, team2_id, status, round_no, match_label)
             VALUES (?, 0, 0, 'pending', ?, '🥉 3位決定戦')"
        );
        $stmt3->execute([$tournamentId, $numRounds]);
        $thirdPlaceMatchId = $pdo->lastInsertId();
    }

    /* ラウンドごとの Match ID リスト */
    $roundMatchIds = [];
    $roundMatchIds[$numRounds] = [$finalMatchId];

    /* 逆順でツリーを構築（決勝 → 準決勝 → ... → 1回戦） */
    for ($r = $numRounds - 1; $r >= 1; $r--) {
        $parentMatches = $roundMatchIds[$r + 1];
        $roundMatchIds[$r] = [];

        foreach ($parentMatches as $pMatchId) {
            /* スロット 1 の予選試合 */
            $rLabel = $getRoundLabel($r - 1, $numRounds);
            $stmt = $pdo->prepare(
                "INSERT INTO molkky_matches (tournament_id, team1_id, team2_id, status, round_no, next_match_id, next_match_slot, third_place_match_id, match_label)
                 VALUES (?, 0, 0, 'pending', ?, ?, 1, ?, ?)"
            );
            $stmt->execute([$tournamentId, $r, $pMatchId, ($r === $numRounds - 1 ? $thirdPlaceMatchId : null), $rLabel]);
            $m1 = $pdo->lastInsertId();

            /* スロット 2 の予選試合 */
            $stmt->execute([$tournamentId, $r, $pMatchId, ($r === $numRounds - 1 ? $thirdPlaceMatchId : null), $rLabel]);
            $m2 = $pdo->lastInsertId();

            $roundMatchIds[$r][] = $m1;
            $roundMatchIds[$r][] = $m2;
        }
    }

    /* 第1ラウンドの試合にチームを割り当てる */
    $firstRoundMatches = $roundMatchIds[1];
    $slots = [];
    for ($i = 0; $i < $bracketSize; $i++) {
        $slots[] = $i < $n ? $teamIds[$i] : 0; // 0 = BYE
    }

    /* チームを対戦カードにセット */
    for ($i = 0; $i < count($firstRoundMatches); $i++) {
        $mId = $firstRoundMatches[$i];
        $t1 = $slots[$i * 2];
        $t2 = $slots[$i * 2 + 1];

        /* BYE の自動不戦勝処理 */
        if ($t1 > 0 && $t2 === 0) {
            /* t1 不戦勝 */
            $upd = $pdo->prepare(
                "UPDATE molkky_matches SET team1_id = ?, team2_id = 0, winner_team_id = ?, status = 'finished' WHERE id = ?"
            );
            $upd->execute([$t1, $t1, $mId]);

            /* 親試合へ進出 */
            $fetchNext = $pdo->query("SELECT next_match_id, next_match_slot FROM molkky_matches WHERE id = $mId")->fetch(PDO::FETCH_ASSOC);
            if ($fetchNext && $fetchNext['next_match_id']) {
                $slotCol = (int)$fetchNext['next_match_slot'] === 2 ? 'team2_id' : 'team1_id';
                $pdo->prepare("UPDATE molkky_matches SET {$slotCol} = ? WHERE id = ?")->execute([$t1, $fetchNext['next_match_id']]);
            }
        } elseif ($t1 === 0 && $t2 > 0) {
            /* t2 不戦勝 */
            $upd = $pdo->prepare(
                "UPDATE molkky_matches SET team1_id = 0, team2_id = ?, winner_team_id = ?, status = 'finished' WHERE id = ?"
            );
            $upd->execute([$t2, $t2, $mId]);

            $fetchNext = $pdo->query("SELECT next_match_id, next_match_slot FROM molkky_matches WHERE id = $mId")->fetch(PDO::FETCH_ASSOC);
            if ($fetchNext && $fetchNext['next_match_id']) {
                $slotCol = (int)$fetchNext['next_match_slot'] === 2 ? 'team2_id' : 'team1_id';
                $pdo->prepare("UPDATE molkky_matches SET {$slotCol} = ? WHERE id = ?")->execute([$t2, $fetchNext['next_match_id']]);
            }
        } else {
            $upd = $pdo->prepare("UPDATE molkky_matches SET team1_id = ?, team2_id = ? WHERE id = ?");
            $upd->execute([$t1, $t2, $mId]);
        }
    }

    return count($firstRoundMatches);
}

/* --- 大会の対戦表生成要求 --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_tournament'])) {
    $newFormat = $_POST['format'] ?? 'knockout';
    if (count($teams) < 2) {
        $message = "チームが2つ以上必要です。";
        $isError = true;
    } else {
        $pdo->beginTransaction();
        try {
            $tournamentId = $tournament['id'];
            $pdo->prepare("UPDATE tournaments SET format = ?, status = 'running' WHERE id = ?")
                ->execute([$newFormat, $tournamentId]);

            $teamIds = array_column($teams, 'id');

            if ($newFormat === 'knockout') {
                generateKnockoutBracket($pdo, $tournamentId, $teamIds);
                $message = "フットボール方式のノックアウト（トーナメント表）を生成しました！";
            } else {
                $pdo->exec("DELETE FROM molkky_matches");
                $rounds = generateRoundRobin($teamIds);
                foreach ($rounds as $roundNo => $matches) {
                    foreach ($matches as [$t1, $t2]) {
                        $stmt = $pdo->prepare(
                            "INSERT INTO molkky_matches (tournament_id, team1_id, team2_id, status, round_no, match_label)
                             VALUES (?, ?, ?, 'pending', ?, ?)"
                        );
                        $stmt->execute([$tournamentId, $t1, $t2, $roundNo + 1, '第 ' . ($roundNo + 1) . ' 節']);
                    }
                }
                $message = "総当たり戦（ラウンドロビン）の対戦表を生成しました！";
            }

            $pdo->commit();
            header('Location: tournament.php?format=' . $newFormat);
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $message = "Error: " . $e->getMessage();
            $isError = true;
        }
    }
}

/* --- 試合を開始する（pending → ongoing にして match.php へ移動） --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_scheduled_match'])) {
    $matchId = filter_input(INPUT_POST, 'match_id', FILTER_VALIDATE_INT);
    if ($matchId) {
        $mInfo = $pdo->query("SELECT team1_id, team2_id FROM molkky_matches WHERE id = $matchId")->fetch(PDO::FETCH_ASSOC);
        if ($mInfo && $mInfo['team1_id'] > 0 && $mInfo['team2_id'] > 0) {
            $pdo->prepare("UPDATE molkky_matches SET status = 'ongoing' WHERE id = ? AND status = 'pending'")
                ->execute([$matchId]);
            header('Location: match.php?id=' . $matchId);
            exit;
        } else {
            $message = "対戦する2チームが決定するまで試合を開始できません。";
            $isError = true;
        }
    }
}

/* 全試合を取得 */
$bracketMatches = $pdo->query("SELECT * FROM molkky_matches ORDER BY round_no ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

/* ラウンドごとの試合に整理 */
$rounds = [];
$stats = ['total' => 0, 'finished' => 0, 'ongoing' => 0, 'pending' => 0];
$champion = null;

foreach ($bracketMatches as $m) {
    $rn = (int) ($m['round_no'] ?? 1);
    $rounds[$rn][] = $m;
    $stats['total']++;
    $stats[$m['status']]++;

    /* 優勝チームの検出 */
    if ($format === 'knockout' && str_contains($m['match_label'] ?? '', '決勝戦') && $m['status'] === 'finished' && $m['winner_team_id']) {
        $champion = $teamNames[(int)$m['winner_team_id']] ?? null;
    }
}

$progress = $stats['total'] > 0 ? round(($stats['finished'] / $stats['total']) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>大会 — モルック</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'nav.php'; ?>
    <div class="content_box content_box--wide">
        <div class="page-title">
            <h2>🏆 モルック大会 (トーナメント)</h2>
            <p class="subtitle">フットボール方式のノックアウト ＆ 総当たり対戦表</p>
        </div>

        <?php if ($message !== ""): ?>
            <p class="<?php echo $isError ? 'message' : 'banner'; ?>"><?php echo e($message); ?></p>
        <?php endif; ?>

        <?php if ($champion): ?>
            <div class="banner champion-banner" style="background: linear-gradient(135deg, #ffd700, #ffae00); color: #333; font-size: 1.3rem; padding: 18px; box-shadow: 0 8px 24px rgba(255, 215, 0, 0.4);">
                🎉 今大会の優勝: <strong><?php echo e($champion); ?></strong> 👑🏆
            </div>
        <?php endif; ?>

        <!-- 大会コントロール & 形式切り替え -->
        <div class="card">
            <div class="table-toolbar">
                <h3>⚙️ 大会設定・形式選択</h3>
                <div class="table-toolbar__actions">
                    <button type="button" class="btn-icon" onclick="window.print()" title="印刷">
                        🖨️ <span class="btn-icon__text">印刷</span>
                    </button>
                </div>
            </div>

            <form method="post" action="tournament.php?format=<?php echo e($format); ?>" class="format-select-form">
                <div class="format-options">
                    <label class="format-option <?php echo $format === 'knockout' ? 'is-selected' : ''; ?>">
                        <input type="radio" name="format" value="knockout" <?php echo $format === 'knockout' ? 'checked' : ''; ?>>
                        <div class="format-option__content">
                            <span class="format-option__title">🏆 ノックアウト (トーナメント表)</span>
                            <span class="format-option__desc">勝者が勝ち上がるサッカー・カップ戦方式。決勝＆3位決定戦つき。</span>
                        </div>
                    </label>

                    <label class="format-option <?php echo $format === 'round_robin' ? 'is-selected' : ''; ?>">
                        <input type="radio" name="format" value="round_robin" <?php echo $format === 'round_robin' ? 'checked' : ''; ?>>
                        <div class="format-option__content">
                            <span class="format-option__title">🔄 総当たり戦 (リーグ戦)</span>
                            <span class="format-option__desc">全チームが一度ずつ対戦し、勝敗数・得失点差で順位を競います。</span>
                        </div>
                    </label>
                </div>

                <div class="tournament-info" style="margin-top: 16px;">
                    <div class="tournament-info__item">
                        <span class="tournament-info__label">参加チーム</span>
                        <span class="tournament-info__value"><?php echo count($teams); ?></span>
                    </div>
                    <div class="tournament-info__item">
                        <span class="tournament-info__label">全試合数</span>
                        <span class="tournament-info__value"><?php echo $stats['total']; ?></span>
                    </div>
                    <div class="tournament-info__item">
                        <span class="tournament-info__label">消化試合</span>
                        <span class="tournament-info__value"><?php echo $stats['finished']; ?></span>
                    </div>
                    <div class="tournament-info__item">
                        <span class="tournament-info__label">進行度</span>
                        <span class="tournament-info__value"><?php echo $progress; ?>%</span>
                    </div>
                </div>

                <?php if ($stats['total'] > 0): ?>
                    <div class="progress-bar">
                        <div class="progress-bar__fill" style="width: <?php echo $progress; ?>%"></div>
                    </div>
                <?php endif; ?>

                <?php if (count($teams) >= 2): ?>
                    <button type="submit" name="generate_tournament" class="btn-primary" style="margin-top:14px;"
                            onsubmit="return confirm('新しい対戦表を生成します。進行中の試合はリセットされます。よろしいですか？');">
                        🔄 新しい対戦表を作成 (<?php echo $format === 'knockout' ? 'トーナメント' : '総当たり'; ?>)
                    </button>
                <?php else: ?>
                    <p class="empty" style="margin-top:12px;">
                        大会を開催するにはチームが2つ以上必要です。<br>
                        <a href="index.php">ダッシュボード</a> でチームを作成して下さい。
                    </p>
                <?php endif; ?>
            </form>
        </div>

        <!-- 対戦表（フットボールトーナメントツリー or ラウンド表示） -->
        <?php if (!empty($rounds)): ?>
            <?php if ($format === 'knockout'): ?>
                <!-- ノックアウト トーナメントツリー（フットボールスタイル） -->
                <div class="card">
                    <h3>⚽ トーナメント表 (Knockout Bracket)</h3>
                    <div class="knockout-tree">
                        <?php foreach ($rounds as $roundNo => $matches):
                            $rLabel = $matches[0]['match_label'] ?? ('Round ' . $roundNo);
                        ?>
                            <div class="tree-round">
                                <h4 class="tree-round__header"><?php echo e($rLabel); ?></h4>
                                <div class="tree-round__matches">
                                    <?php foreach ($matches as $m):
                                        $t1 = (int) $m['team1_id'];
                                        $t2 = (int) $m['team2_id'];
                                        $name1 = $t1 > 0 ? ($teamNames[$t1] ?? 'Team ' . $t1) : '（未定）';
                                        $name2 = $t2 > 0 ? ($teamNames[$t2] ?? 'Team ' . $t2) : '（未定）';
                                        $s1 = (int) $m['team1_score'];
                                        $s2 = (int) $m['team2_score'];
                                        $winnerId = (int) $m['winner_team_id'];
                                        $status = $m['status'];
                                        $isBye = ($t1 > 0 && $t2 === 0) || ($t1 === 0 && $t2 > 0);
                                    ?>
                                        <div class="tree-card tree-card--<?php echo $status; ?> <?php echo $isBye ? 'tree-card--bye' : ''; ?>">
                                            <div class="tree-card__label"><?php echo e($m['match_label'] ?? ''); ?></div>
                                            <div class="tree-team <?php echo $status === 'finished' && $winnerId === $t1 ? 'is-winner' : ''; ?>">
                                                <span class="tree-team__name"><?php echo e($name1); ?></span>
                                                <?php if ($status !== 'pending' && !$isBye): ?>
                                                    <span class="tree-team__score"><?php echo $s1; ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="tree-team <?php echo $status === 'finished' && $winnerId === $t2 ? 'is-winner' : ''; ?>">
                                                <span class="tree-team__name"><?php echo e($name2); ?></span>
                                                <?php if ($status !== 'pending' && !$isBye): ?>
                                                    <span class="tree-team__score"><?php echo $s2; ?></span>
                                                <?php endif; ?>
                                            </div>

                                            <div class="tree-card__footer">
                                                <?php if ($status === 'pending' && $t1 > 0 && $t2 > 0): ?>
                                                    <form method="post">
                                                        <input type="hidden" name="match_id" value="<?php echo (int) $m['id']; ?>">
                                                        <button type="submit" name="start_scheduled_match" class="btn-sm btn-sm--save">▶ 試合開局</button>
                                                    </form>
                                                <?php elseif ($status === 'ongoing'): ?>
                                                    <a href="match.php?id=<?php echo (int) $m['id']; ?>" class="btn-sm btn-sm--active">🔴 試合進行中</a>
                                                <?php elseif ($status === 'finished'): ?>
                                                    <?php if ($isBye): ?>
                                                        <span class="status-badge">シード (不戦勝)</span>
                                                    <?php else: ?>
                                                        <a href="match.php?id=<?php echo (int) $m['id']; ?>" class="btn-sm btn-sm--done">試合結果</a>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="status-badge">対戦待ち</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- ラウンドロビン 表示 -->
                <?php foreach ($rounds as $roundNo => $matches): ?>
                    <div class="card tournament-round">
                        <h3 class="round-title">
                            第 <?php echo $roundNo; ?> 節
                            <?php
                                $roundDone = count(array_filter($matches, fn($m) => $m['status'] === 'finished'));
                                $roundTotal = count($matches);
                            ?>
                            <span class="round-progress"><?php echo $roundDone; ?>/<?php echo $roundTotal; ?> 試合完了</span>
                            <?php if ($roundDone === $roundTotal): ?>
                                <span class="round-complete-badge">✓ 完了</span>
                            <?php endif; ?>
                        </h3>

                        <div class="bracket-matches">
                            <?php foreach ($matches as $m):
                                $t1 = (int) $m['team1_id'];
                                $t2 = (int) $m['team2_id'];
                                $name1 = $teamNames[$t1] ?? 'Team ' . $t1;
                                $name2 = $teamNames[$t2] ?? 'Team ' . $t2;
                                $s1 = (int) $m['team1_score'];
                                $s2 = (int) $m['team2_score'];
                                $winnerId = (int) $m['winner_team_id'];
                                $status = $m['status'];
                            ?>
                                <div class="bracket-match bracket-match--<?php echo $status; ?>">
                                    <div class="bracket-match__teams">
                                        <div class="bracket-team <?php echo $status === 'finished' && $winnerId === $t1 ? 'is-winner' : ''; ?>">
                                            <span class="bracket-team__name"><?php echo e($name1); ?></span>
                                            <?php if ($status !== 'pending'): ?>
                                                <span class="bracket-team__score"><?php echo $s1; ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <span class="bracket-match__vs">vs</span>
                                        <div class="bracket-team <?php echo $status === 'finished' && $winnerId === $t2 ? 'is-winner' : ''; ?>">
                                            <span class="bracket-team__name"><?php echo e($name2); ?></span>
                                            <?php if ($status !== 'pending'): ?>
                                                <span class="bracket-team__score"><?php echo $s2; ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="bracket-match__action">
                                        <?php if ($status === 'pending'): ?>
                                            <form method="post">
                                                <input type="hidden" name="match_id" value="<?php echo (int) $m['id']; ?>">
                                                <button type="submit" name="start_scheduled_match" class="btn-sm">▶ 開始</button>
                                            </form>
                                        <?php elseif ($status === 'ongoing'): ?>
                                            <a href="match.php?id=<?php echo (int) $m['id']; ?>" class="btn-sm btn-sm--active">🔴 進行中</a>
                                        <?php else: ?>
                                            <a href="match.php?id=<?php echo (int) $m['id']; ?>" class="btn-sm btn-sm--done">結果</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>

        <div class="links">
            <a href="standings.php">順位表 (リーダーボード)</a>
            <a href="index.php">ダッシュボードへ</a>
        </div>
    </div>
</body>
</html>
