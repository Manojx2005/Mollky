<?php
/**
 * 試合ページ。2チームを選んで試合を開始し、倒れたスキットルから得点を記録する。
 * モルックのルール（ちょうど50点で勝ち・超過で25点・3連続ミスで負け）を実装し、
 * 直前の1投を取り消す「一つ戻す」にも対応する。
 */
require_once 'db.php';

/* 試合状態を保存する molkky_matches テーブル（無ければ作成）。 */
$pdo->exec("CREATE TABLE IF NOT EXISTS `molkky_matches` (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tournament_id INTEGER NOT NULL,
    team1_id INTEGER NOT NULL,
    team2_id INTEGER NOT NULL,
    team1_score INTEGER NOT NULL DEFAULT 0,
    team2_score INTEGER NOT NULL DEFAULT 0,
    winner_team_id INTEGER NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ongoing',
    current_turn VARCHAR(10) NOT NULL DEFAULT 'team1',
    team1_misses INTEGER NOT NULL DEFAULT 0,
    team2_misses INTEGER NOT NULL DEFAULT 0,
    team1_player_idx INTEGER NOT NULL DEFAULT 0,
    team2_player_idx INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

/* 既存テーブルに不足している列を後方互換のため個別に追加する。 */
foreach ([
    "ADD COLUMN `current_turn` VARCHAR(10) NOT NULL DEFAULT 'team1'",
    "ADD COLUMN `team1_misses` INT NOT NULL DEFAULT 0",
    "ADD COLUMN `team2_misses` INT NOT NULL DEFAULT 0",
    "ADD COLUMN `team1_player_idx` INTEGER NOT NULL DEFAULT 0",
    "ADD COLUMN `team2_player_idx` INTEGER NOT NULL DEFAULT 0",
] as $alter) {
    try {
        $pdo->exec("ALTER TABLE `molkky_matches` $alter");
    } catch (PDOException $e) {
        /* 既に存在する列は無視する。 */
    }
}

/* 「一つ戻す」用の履歴。1投ごとに直前の状態を保存し、誤入力時に復元する。 */
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

/* モルックのルール定数。 */
const TARGET_SCORE = 50;       // ちょうどこの点で勝ち
const OVER_RESET_SCORE = 25;   // 超えたらこの点に戻る
const MAX_THROW = 12;          // スキットルは1〜12
const MAX_MISSES = 3;          // 連続ミスがこの回数で負け

$message = "";

/* HTML 出力用のエスケープ関数。 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/* チームの選手名を id 順（＝投げる順番）で取得する。 */
function teamPlayers(PDO $pdo, int $teamId): array
{
    $stmt = $pdo->prepare(
        "SELECT s.name
         FROM team_members tm
         JOIN students s ON s.id = tm.student_id
         WHERE tm.team_id = ?
         ORDER BY s.id"
    );
    $stmt->execute([$teamId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/* 新しい試合を開始する。 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_match'])) {
    $team1 = filter_input(INPUT_POST, 'team1', FILTER_VALIDATE_INT);
    $team2 = filter_input(INPUT_POST, 'team2', FILTER_VALIDATE_INT);

    if (!$team1 || !$team2 || $team1 === $team2) {
        $message = "異なる2チームを選んで下さい。";
    } else {
        try {
            $tournament = $pdo->query("SELECT id FROM tournaments LIMIT 1")
                ->fetch(PDO::FETCH_ASSOC);
            if (!$tournament) {
                $message = "Error: 大会が見つかりません。";
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO `molkky_matches` (tournament_id, team1_id, team2_id)
                     VALUES (?, ?, ?)"
                );
                $stmt->execute([$tournament['id'], $team1, $team2]);
                header('Location: match.php?id=' . $pdo->lastInsertId());
                exit;
            }
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
        }
    }
}

/* 一つ戻す: 直前の1投を取り消し、履歴から状態を復元する。 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['undo'])) {
    $matchId = filter_input(INPUT_POST, 'match_id', FILTER_VALIDATE_INT);
    if ($matchId) {
        $stmt = $pdo->prepare(
            "SELECT * FROM `molkky_throws` WHERE match_id = ? ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$matchId]);
        $prev = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($prev) {
            $restore = $pdo->prepare(
                "UPDATE `molkky_matches`
                 SET team1_score = ?, team2_score = ?, team1_misses = ?, team2_misses = ?,
                     team1_player_idx = ?, team2_player_idx = ?, current_turn = ?,
                     status = ?, winner_team_id = ?
                 WHERE id = ?"
            );
            $restore->execute([
                $prev['team1_score'], $prev['team2_score'],
                $prev['team1_misses'], $prev['team2_misses'],
                $prev['team1_player_idx'], $prev['team2_player_idx'],
                $prev['current_turn'], $prev['status'],
                $prev['winner_team_id'] !== null ? (int) $prev['winner_team_id'] : null,
                $matchId,
            ]);
            $pdo->prepare("DELETE FROM `molkky_throws` WHERE id = ?")->execute([$prev['id']]);
        }
        header('Location: match.php?id=' . $matchId);
        exit;
    }
}

/* 倒れたスキットルから得点を計算・記録し、次のチームへ交代する。 */
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && (isset($_POST['record_throw']) || isset($_POST['miss']))) {
    $matchId = filter_input(INPUT_POST, 'match_id', FILTER_VALIDATE_INT);
    $isMiss = isset($_POST['miss']);

    /* ミスボタンは選択に関わらず0点。通常記録は倒れたピンから計算する。 */
    if ($isMiss) {
        $points = 0;
    } else {
        /* 送信された「倒れたピン」を 1〜12 の重複なし整数に整える。 */
        $rawPins = $_POST['pins'] ?? [];
        if (!is_array($rawPins)) {
            $rawPins = [];
        }
        $fallen = [];
        foreach ($rawPins as $pin) {
            $n = filter_var($pin, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1, 'max_range' => MAX_THROW],
            ]);
            if ($n !== false) {
                $fallen[$n] = true; // キーで重複を排除
            }
        }
        $count = count($fallen);

        /* 得点ルール: 0本=0点 / 1本=そのピンの番号 / 2本以上=倒した本数。 */
        if ($count === 0) {
            $points = 0;
        } elseif ($count === 1) {
            $points = (int) array_key_first($fallen);
        } else {
            $points = $count;
        }
    }

    if ($matchId) {
        $stmt = $pdo->prepare("SELECT * FROM `molkky_matches` WHERE id = ?");
        $stmt->execute([$matchId]);
        $match = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($match && $match['status'] === 'ongoing') {
            /* 番はサーバー側の状態を正とする（クライアント任せにしない）。 */
            $turn = ($match['current_turn'] ?? 'team1') === 'team2' ? 'team2' : 'team1';
            $scoreCol = $turn === 'team1' ? 'team1_score' : 'team2_score';
            $missCol = $turn === 'team1' ? 'team1_misses' : 'team2_misses';
            $teamId = $match[$turn === 'team1' ? 'team1_id' : 'team2_id'];
            $opponentId = $match[$turn === 'team1' ? 'team2_id' : 'team1_id'];

            $newScore = (int) $match[$scoreCol] + $points;
            /* 得点すれば連続ミスをリセット、0点なら連続ミスを+1する。 */
            $newMisses = $points > 0 ? 0 : ((int) ($match[$missCol] ?? 0) + 1);

            $winnerId = null;
            $status = 'ongoing';

            if ($newMisses >= MAX_MISSES) {
                /* 3連続ミスで負け → 相手チームの勝ち。 */
                $winnerId = $opponentId;
                $status = 'finished';
            } elseif ($newScore === TARGET_SCORE) {
                $winnerId = $teamId;
                $status = 'finished';
            } elseif ($newScore > TARGET_SCORE) {
                /* 50を超えたら25点に戻る。 */
                $newScore = OVER_RESET_SCORE;
            }

            /* 相手チームに交代する（試合終了時も列を更新して問題ない）。 */
            $nextTurn = $turn === 'team1' ? 'team2' : 'team1';

            /* 投げ終えたので、このチームの投げる人を次の選手へ進める。 */
            $idxCol = $turn === 'team1' ? 'team1_player_idx' : 'team2_player_idx';
            $newIdx = (int) ($match[$idxCol] ?? 0) + 1;

            /* 投擲者を特定する */
            $activePlayersCurrent = teamPlayers($pdo, $teamId);
            $activeIdxVal = (int) ($match[$turn === 'team1' ? 'team1_player_idx' : 'team2_player_idx'] ?? 0);
            $throwerName = $activePlayersCurrent ? $activePlayersCurrent[$activeIdxVal % count($activePlayersCurrent)] : null;
            $pinsHitStr = !empty($fallen) ? implode(',', array_keys($fallen)) : '';

            /* 更新前の状態＋投球結果を履歴に保存する（「一つ戻す」および実況ログ用）。 */
            $snap = $pdo->prepare(
                "INSERT INTO `molkky_throws`
                 (match_id, team1_score, team2_score, team1_misses, team2_misses,
                  team1_player_idx, team2_player_idx, current_turn, status, winner_team_id,
                  player_name, points_scored, pins_hit)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $snap->execute([
                $matchId,
                (int) $match['team1_score'], (int) $match['team2_score'],
                (int) ($match['team1_misses'] ?? 0), (int) ($match['team2_misses'] ?? 0),
                (int) ($match['team1_player_idx'] ?? 0), (int) ($match['team2_player_idx'] ?? 0),
                $match['current_turn'] ?? 'team1', $match['status'],
                $match['winner_team_id'] !== null ? (int) $match['winner_team_id'] : null,
                $throwerName,
                $points,
                $pinsHitStr
            ]);

            $stmt = $pdo->prepare(
                "UPDATE `molkky_matches`
                 SET $scoreCol = ?, $missCol = ?, winner_team_id = ?, status = ?,
                     current_turn = ?, $idxCol = ?
                 WHERE id = ?"
            );
            $stmt->execute([$newScore, $newMisses, $winnerId, $status, $nextTurn, $newIdx, $matchId]);

            /* ノックアウト大会の勝者自動進出＆3位決定戦敗者進出処理 */
            if ($status === 'finished' && $winnerId) {
                $loserId = ($winnerId === $teamId) ? $opponentId : $teamId;

                /* 次のラウンドの試合へ進出 */
                if (!empty($match['next_match_id'])) {
                    $nextId = (int) $match['next_match_id'];
                    $slot = (int) ($match['next_match_slot'] ?? 1);
                    $slotCol = ($slot === 2) ? 'team2_id' : 'team1_id';
                    $pdo->prepare("UPDATE `molkky_matches` SET {$slotCol} = ? WHERE id = ?")->execute([$winnerId, $nextId]);
                }

                /* 3位決定戦へ進出 */
                if (!empty($match['third_place_match_id']) && $loserId > 0) {
                    $tpId = (int) $match['third_place_match_id'];
                    $tpMatch = $pdo->query("SELECT team1_id, team2_id FROM `molkky_matches` WHERE id = $tpId")->fetch(PDO::FETCH_ASSOC);
                    if ($tpMatch) {
                        if ((int)$tpMatch['team1_id'] === 0) {
                            $pdo->prepare("UPDATE `molkky_matches` SET team1_id = ? WHERE id = ?")->execute([$loserId, $tpId]);
                        } elseif ((int)$tpMatch['team2_id'] === 0) {
                            $pdo->prepare("UPDATE `molkky_matches` SET team2_id = ? WHERE id = ?")->execute([$loserId, $tpId]);
                        }
                    }
                }
            }
        }
        header('Location: match.php?id=' . $matchId);
        exit;
    }
}

/* チーム選択プルダウン用の一覧。 */
$teams = $pdo
    ->query("SELECT id, name FROM teams ORDER BY id")
    ->fetchAll(PDO::FETCH_ASSOC);
$teamNames = [];
foreach ($teams as $t) {
    $teamNames[(int) $t['id']] = $t['name'];
}

/* 表示対象の試合（?id=...）を読み込む。 */
$currentMatch = null;
$hasHistory = false;
$matchId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($matchId) {
    $stmt = $pdo->prepare("SELECT * FROM `molkky_matches` WHERE id = ?");
    $stmt->execute([$matchId]);
    $currentMatch = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    /* 「一つ戻す」ボタンの表示判定に、取り消せる履歴の有無を調べる。 */
    if ($currentMatch) {
        $h = $pdo->prepare("SELECT 1 FROM `molkky_throws` WHERE match_id = ? LIMIT 1");
        $h->execute([(int) $currentMatch['id']]);
        $hasHistory = (bool) $h->fetchColumn();
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>試合とスコア</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'nav.php'; ?>
    <div class="content_box">
        <div class="page-title">
            <h2>試合とスコア</h2>
            <p class="subtitle">チームを選んで試合を始め、得点を記録します</p>
        </div>

        <?php if ($message !== ""): ?>
            <p class="message"><?php echo e($message); ?></p>
        <?php endif; ?>

        <?php if ($currentMatch):
            $t1 = (int) $currentMatch['team1_id'];
            $t2 = (int) $currentMatch['team2_id'];
            $name1 = $teamNames[$t1] ?? ('Team ' . $t1);
            $name2 = $teamNames[$t2] ?? ('Team ' . $t2);
            $finished = $currentMatch['status'] === 'finished';
            $winnerId = (int) $currentMatch['winner_team_id'];
            $miss1 = (int) ($currentMatch['team1_misses'] ?? 0);
            $miss2 = (int) ($currentMatch['team2_misses'] ?? 0);

            /* 連続ミスを最大回数ぶんの点で表示する小さなヘルパー。 */
            $missDots = static function (int $misses): string {
                $out = '';
                for ($i = 1; $i <= MAX_MISSES; $i++) {
                    $filled = $i <= $misses ? ' is-miss' : '';
                    $out .= '<span class="miss-dot' . $filled . '"></span>';
                }
                return $out;
            };
        ?>
            <div class="card">
                <?php if ($finished):
                    $winnerName = $winnerId === $t1 ? $name1 : $name2;
                    $lostByMisses = ($winnerId === $t1 && $miss2 >= MAX_MISSES)
                        || ($winnerId === $t2 && $miss1 >= MAX_MISSES);
                ?>
                    <div class="banner">
                        勝者: <?php echo e($winnerName); ?> 🎉
                        <?php if ($lostByMisses): ?>
                            <span class="banner__reason">（相手が<?php echo MAX_MISSES; ?>連続ミス）</span>
                        <?php endif; ?>
                    </div>
                    <div class="match-finished-actions">
                        <button type="button" class="btn-icon" onclick="window.print()" title="印刷">
                            🖨️ <span class="btn-icon__text">結果を印刷</span>
                        </button>
                    </div>
                <?php endif; ?>

                <div class="scoreboard">
                    <div>
                        <div class="team-name <?php echo $finished && $winnerId === $t1 ? 'win' : ''; ?>">
                            <?php echo e($name1); ?>
                        </div>
                        <div class="score"><?php echo (int) $currentMatch['team1_score']; ?></div>
                        <div class="miss-track" title="連続ミス <?php echo $miss1; ?>/<?php echo MAX_MISSES; ?>">
                            <?php echo $missDots($miss1); ?>
                        </div>
                    </div>
                    <div class="vs">VS</div>
                    <div>
                        <div class="team-name <?php echo $finished && $winnerId === $t2 ? 'win' : ''; ?>">
                            <?php echo e($name2); ?>
                        </div>
                        <div class="score"><?php echo (int) $currentMatch['team2_score']; ?></div>
                        <div class="miss-track" title="連続ミス <?php echo $miss2; ?>/<?php echo MAX_MISSES; ?>">
                            <?php echo $missDots($miss2); ?>
                        </div>
                    </div>
                </div>

                <?php if ($hasHistory): ?>
                    <form method="post" class="undo-form">
                        <input type="hidden" name="match_id" value="<?php echo (int) $currentMatch['id']; ?>">
                        <button type="submit" name="undo" value="1" class="btn-undo">
                            ↩ 一つ戻す（入力ミスを修正）
                        </button>
                    </form>
                <?php endif; ?>

                <?php if (!$finished):
                    /* スキットルの配置（手前が 1・2）。行ごとに描画する。 */
                    $pinRows = [[7, 9, 8], [5, 11, 12, 6], [3, 10, 4], [1, 2]];
                    $turn = ($currentMatch['current_turn'] ?? 'team1') === 'team2' ? 'team2' : 'team1';
                    $activeName = $turn === 'team1' ? $name1 : $name2;
                    $activeMisses = $turn === 'team1' ? $miss1 : $miss2;

                    /* 今このチームで投げる選手を特定する。 */
                    $activeTeamId = $turn === 'team1' ? $t1 : $t2;
                    $activeIdx = (int) ($currentMatch[$turn === 'team1' ? 'team1_player_idx' : 'team2_player_idx'] ?? 0);
                    $activePlayers = teamPlayers($pdo, $activeTeamId);
                    $currentPlayer = $activePlayers
                        ? $activePlayers[$activeIdx % count($activePlayers)]
                        : null;
                ?>
                    <?php if ($currentPlayer !== null): ?>
                        <div class="now-throwing">
                            <span class="now-throwing__label">投げる人</span>
                            <span class="now-throwing__name"><?php echo e($currentPlayer); ?></span>
                            <span class="now-throwing__team"><?php echo e($activeName); ?></span>
                        </div>
                    <?php endif; ?>

                    <p class="turn-indicator">
                        今の番: <strong><?php echo e($activeName); ?></strong>
                        <span class="turn-misses">連続ミス <?php echo $activeMisses; ?>/<?php echo MAX_MISSES; ?></span>
                    </p>

                    <form method="post" class="throw-panel" id="throwForm">
                        <input type="hidden" name="match_id" value="<?php echo (int) $currentMatch['id']; ?>">

                        <p class="throw-label">倒れたスキットルをすべてタップ（複数選択できます）</p>

                        <div class="pin-board">
                            <?php foreach ($pinRows as $row): ?>
                                <div class="pin-row">
                                    <?php foreach ($row as $n): ?>
                                        <label class="pin" aria-label="スキットル <?php echo $n; ?>">
                                            <input type="checkbox" name="pins[]" value="<?php echo $n; ?>">
                                            <img class="pin__img" src="images/skittles/skittle<?php echo $n; ?>.png" alt="<?php echo $n; ?>">
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <p class="score-preview" id="scorePreview">獲得点: 0（ミス）</p>

                        <div class="throw-actions">
                            <button type="reset" class="miss-btn">選択をクリア</button>
                            <button type="submit" name="miss" value="1" class="miss-record-btn">
                                ミス（0点）— <?php echo MAX_MISSES; ?>連続で負け
                            </button>
                        </div>
                        <button type="submit" name="record_throw" value="1" class="btn-primary">
                            記録して次のチームへ
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <!-- 音声＆効果音・タイマー・QRツールバー -->
            <div class="card match-tools-card">
                <div class="match-tools-row">
                    <div class="match-timer-badge">
                        ⏱️ 経過時間: <span id="matchTimer">00:00</span>
                    </div>
                    <div class="match-audio-controls">
                        <button type="button" class="btn-sm" id="toggleSoundBtn">🔊 効果音: ON</button>
                        <button type="button" class="btn-sm" id="toggleSpeechBtn">🗣️ 音声実況: ON</button>
                        <a href="overlay.php" target="_blank" class="btn-sm">📺 OBS表示</a>
                    </div>
                </div>
            </div>

            <!-- 実況プレイバイプレイ タイムライン -->
            <?php
                $stmtP = $pdo->prepare(
                    "SELECT player_name, points_scored, pins_hit, team1_score, team2_score, created_at
                     FROM molkky_throws WHERE match_id = ? AND player_name IS NOT NULL ORDER BY id DESC LIMIT 10"
                );
                $stmtP->execute([(int)$currentMatch['id']]);
                $playLogs = $stmtP->fetchAll(PDO::FETCH_ASSOC);
            ?>
            <?php if (!empty($playLogs)): ?>
                <div class="card">
                    <h3>📝 投球ログ・実況タイムライン (Play-by-Play)</h3>
                    <div class="play-by-play-feed">
                        <?php foreach ($playLogs as $log):
                            $pts = (int)$log['points_scored'];
                            $pName = $log['player_name'] ?: '選手';
                            $pins = $log['pins_hit'];
                            $detail = $pts === 0 ? 'ミス（0点）' : ($pts > 0 && str_contains($pins, ',') ? "{$pts}本倒し (+{$pts}点)" : "{$pins}番ピン (+{$pts}点)");
                        ?>
                            <div class="play-log-item <?php echo $pts === 0 ? 'play-log-item--miss' : ''; ?>">
                                <span class="play-log-time"><?php echo date('H:i:s', strtotime($log['created_at'])); ?></span>
                                <span class="play-log-player"><strong><?php echo e($pName); ?></strong></span>
                                <span class="play-log-detail"><?php echo e($detail); ?></span>
                                <span class="play-log-score">(スコア: <?php echo $log['team1_score']; ?> - <?php echo $log['team2_score']; ?>)</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="links">
                <a href="match.php">別の試合を始める</a>
                <a href="history.php">試合履歴</a>
                <a href="standings.php">順位表</a>
                <a href="index.php">ダッシュボードへ</a>
            </div>

        <?php else: ?>
            <div class="card">
                <h3>試合を始める</h3>
                <?php if (count($teams) < 2): ?>
                    <p class="message">チームが2つ以上必要です。先にチームを作って下さい。</p>
                    <div class="links"><a href="index.php">ダッシュボードでチームを作る</a></div>
                <?php else: ?>
                    <form method="post">
                        <label class="field-label" for="team1">チーム 1</label>
                        <select name="team1" id="team1" required>
                            <?php foreach ($teams as $t): ?>
                                <option value="<?php echo (int) $t['id']; ?>"><?php echo e($t['name']); ?></option>
                            <?php endforeach; ?>
                        </select>

                        <label class="field-label" for="team2">チーム 2</label>
                        <select name="team2" id="team2" required>
                            <?php foreach ($teams as $t): ?>
                                <option value="<?php echo (int) $t['id']; ?>"><?php echo e($t['name']); ?></option>
                            <?php endforeach; ?>
                        </select>

                        <button type="submit" name="start_match" class="btn-primary">試合開始</button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="links">
                <a href="index.php">ダッシュボードへ</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
    // 選択したピンからモルックの得点を即時プレビューする
    (function () {
        var form = document.getElementById('throwForm');
        var preview = document.getElementById('scorePreview');

        if (form && preview) {
            function update() {
                var checked = form.querySelectorAll('input[name="pins[]"]:checked');
                var n = checked.length;
                if (n === 0) {
                    preview.textContent = '獲得点: 0（ミス）';
                } else if (n === 1) {
                    var v = parseInt(checked[0].value, 10);
                    preview.textContent = '獲得点: ' + v + '（' + v + '番を1本）';
                } else {
                    preview.textContent = '獲得点: ' + n + '（' + n + '本倒し）';
                }
            }

            form.addEventListener('change', update);
            form.addEventListener('reset', function () { setTimeout(update, 0); });
            update();
        }

        // --- 試合タイマー（ストップウォッチ） ---
        var timerEl = document.getElementById('matchTimer');
        if (timerEl) {
            var startTime = localStorage.getItem('molkky_timer_start_' + (location.search || 'default'));
            if (!startTime) {
                startTime = Date.now();
                localStorage.setItem('molkky_timer_start_' + (location.search || 'default'), startTime);
            }
            setInterval(function () {
                var elapsedSec = Math.floor((Date.now() - parseInt(startTime, 10)) / 1000);
                var mins = String(Math.floor(elapsedSec / 60)).padStart(2, '0');
                var secs = String(elapsedSec % 60).padStart(2, '0');
                timerEl.textContent = mins + ':' + secs;
            }, 1000);
        }

        // --- 音声効果音 ＆ 音声実況 (Web Audio & Speech API) ---
        var soundEnabled = localStorage.getItem('molkky_sound') !== 'false';
        var speechEnabled = localStorage.getItem('molkky_speech') !== 'false';

        var soundBtn = document.getElementById('toggleSoundBtn');
        var speechBtn = document.getElementById('toggleSpeechBtn');

        function updateBtnStates() {
            if (soundBtn) soundBtn.textContent = '🔊 効果音: ' + (soundEnabled ? 'ON' : 'OFF');
            if (speechBtn) speechBtn.textContent = '🗣️ 音声実況: ' + (speechEnabled ? 'ON' : 'OFF');
        }
        updateBtnStates();

        if (soundBtn) {
            soundBtn.addEventListener('click', function () {
                soundEnabled = !soundEnabled;
                localStorage.setItem('molkky_sound', soundEnabled);
                updateBtnStates();
            });
        }
        if (speechBtn) {
            speechBtn.addEventListener('click', function () {
                speechEnabled = !speechEnabled;
                localStorage.setItem('molkky_speech', speechEnabled);
                updateBtnStates();
            });
        }

        // 音声アナウンス（投げる人）
        var throwingNameEl = document.querySelector('.now-throwing__name');
        if (throwingNameEl && speechEnabled && 'speechSynthesis' in window) {
            var pName = throwingNameEl.textContent.trim();
            if (pName) {
                var msg = new SpeechSynthesisUtterance('次は ' + pName + ' さんの番です');
                msg.lang = 'ja-JP';
                msg.rate = 1.0;
                window.speechSynthesis.cancel();
                window.speechSynthesis.speak(msg);
            }
        }
    })();
    </script>
</body>
</html>
