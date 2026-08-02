<?php
/**
 * ダッシュボード（トップ）。集計の表示、チームのシャッフル作成、
 * 大会終了（チーム・試合の全削除）、チーム一覧の表示・編集を行う。
 */
require_once 'db.php';
require_once 'team_shuffle.php';
session_start();

$message = "";

/* api_teams.php からのフラッシュメッセージ。 */
if (isset($_SESSION['team_message']) && $_SESSION['team_message'] !== '') {
    $message = $_SESSION['team_message'];
    unset($_SESSION['team_message']);
}

/* 1チームの人数（セッション値。範囲外は既定の5に丸める）。 */
$teamSize = (int) ($_SESSION['team_size'] ?? 5);
if ($teamSize < 2 || $teamSize > 8) {
    $teamSize = 5;
}

/* シャッフルしてチームを作成する。 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['shuffle'])) {
    try {
        $message = shuffleTeams($pdo, $teamSize);
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

/* 大会終了: チーム・メンバー・試合をすべて削除する。 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_teams'])) {
    $pdo->beginTransaction();
    try {
        /* 外部キーの依存順（子テーブル）に削除する。 */
        $pdo->exec("DELETE FROM team_members");
        /* molkky_matches は実行時生成なので、存在する場合のみ削除。 */
        if ($pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='molkky_matches'")->fetch()) {
            $pdo->exec("DELETE FROM `molkky_matches`");
        }
        if ($pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='molkky_throws'")->fetch()) {
            $pdo->exec("DELETE FROM `molkky_throws`");
        }
        $pdo->exec("DELETE FROM teams");
        /* 次の大会に備えて参加状態もリセットする。 */
        $pdo->exec("UPDATE students SET is_active = 0");
        $pdo->commit();
        $message = "全てのチームを削除しました。";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Error: " . $e->getMessage();
    }
}

/* チームと所属生徒をまとめて取得する。 */
$stmt = $pdo->query("SELECT t.id as team_id, t.name as team_name, s.id as student_id, s.name as student_name
                     FROM teams t
                     JOIN team_members tm ON t.id = tm.team_id
                     JOIN students s ON tm.student_id = s.id
                     ORDER BY t.id, s.id");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* チームごとに整形する。 */
$teams = [];
$teamIds = [];
$allMembers = []; /* メンバー入れ替え用の全メンバーリスト */
foreach ($results as $row) {
    $tid = $row['team_id'];
    $tname = $row['team_name'];
    if (!isset($teams[$tname])) {
        $teams[$tname] = [];
        $teamIds[$tname] = $tid;
    }
    $teams[$tname][] = ['id' => $row['student_id'], 'name' => $row['student_name']];
    $allMembers[] = ['id' => $row['student_id'], 'name' => $row['student_name'], 'team' => $tname];
}

/* ダッシュボード上部に表示する集計値。 */
$totalStudents = (int) $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$activeStudents = (int) $pdo->query("SELECT COUNT(*) FROM students WHERE is_active = 1")->fetchColumn();
$totalTeams = count($teams);
$totalMatches = 0;
$ongoingMatches = 0;
$finishedMatches = 0;
if ($pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='molkky_matches'")->fetch()) {
    $totalMatches = (int) $pdo->query("SELECT COUNT(*) FROM `molkky_matches`")->fetchColumn();
    $ongoingMatches = (int) $pdo
        ->query("SELECT COUNT(*) FROM `molkky_matches` WHERE status = 'ongoing'")
        ->fetchColumn();
    $finishedMatches = (int) $pdo
        ->query("SELECT COUNT(*) FROM `molkky_matches` WHERE status = 'finished'")
        ->fetchColumn();
}

/* 直近の完了試合（「最後の試合」カード用）。 */
$lastMatch = null;
$teamNameMap = [];
foreach ($pdo->query("SELECT id, name FROM teams")->fetchAll(PDO::FETCH_ASSOC) as $t) {
    $teamNameMap[(int) $t['id']] = $t['name'];
}
if ($pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='molkky_matches'")->fetch()) {
    $lastMatch = $pdo->query(
        "SELECT * FROM molkky_matches WHERE status = 'finished' ORDER BY id DESC LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>モルック 大会</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'nav.php'; ?>
    <img class="hero-banner" src="images/banner.png" alt="モルックを楽しむ様子">
    <div class="content_box">
        <div class="page-title" id="dashboard">
            <h2>ダッシュボード</h2>
        </div>

        <?php if ($message !== ""): ?>
            <p class="<?php echo str_starts_with($message, 'Error') ? 'message' : 'banner'; ?>">
                <?php echo e($message); ?>
            </p>
        <?php endif; ?>

        <div class="stat-grid">
            <div class="stat">
                <span class="stat__value"><?php echo $totalStudents; ?></span>
                <span class="stat__label">登録中</span>
            </div>
            <div class="stat">
                <span class="stat__value"><?php echo $activeStudents; ?></span>
                <span class="stat__label">参加中</span>
            </div>
            <div class="stat">
                <span class="stat__value"><?php echo $totalTeams; ?></span>
                <span class="stat__label">チーム</span>
            </div>
            <div class="stat">
                <span class="stat__value"><?php echo $ongoingMatches; ?><span class="stat__sub">/<?php echo $totalMatches; ?></span></span>
                <span class="stat__label">進行中の試合</span>
            </div>
        </div>

        <?php if ($lastMatch): ?>
            <div class="card last-match-card">
                <div class="last-match__header">
                    <h3>🏆 最後の試合結果</h3>
                    <a href="history.php" class="btn-sm">全履歴</a>
                </div>
                <?php
                    $lm1 = (int) $lastMatch['team1_id'];
                    $lm2 = (int) $lastMatch['team2_id'];
                    $lmName1 = $teamNameMap[$lm1] ?? 'Team ' . $lm1;
                    $lmName2 = $teamNameMap[$lm2] ?? 'Team ' . $lm2;
                    $lmWinner = (int) $lastMatch['winner_team_id'];
                ?>
                <div class="last-match__scoreboard">
                    <div class="last-match__team <?php echo $lmWinner === $lm1 ? 'is-winner' : ''; ?>">
                        <span class="last-match__name"><?php echo e($lmName1); ?></span>
                        <span class="last-match__score"><?php echo (int) $lastMatch['team1_score']; ?></span>
                    </div>
                    <span class="last-match__vs">VS</span>
                    <div class="last-match__team <?php echo $lmWinner === $lm2 ? 'is-winner' : ''; ?>">
                        <span class="last-match__name"><?php echo e($lmName2); ?></span>
                        <span class="last-match__score"><?php echo (int) $lastMatch['team2_score']; ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="card">
            <h3>Let's Play!</h3>

            <div class="team-creation-options">
                <form method="post" class="shuffle-form">
                    <button type="submit" name="shuffle" class="btn-primary">
                        🎲 自動シャッフルでチーム作成（<?php echo $teamSize; ?>人 / チーム）
                    </button>
                </form>
                <a href="create_team.php" class="btn-outline btn-create-manual">
                    ➕ 手動でチーム作成
                </a>
            </div>

            <ul class="play-hints">
                <li>人数変更はこちら <span class="arrow">→</span> <span class="tab-ref">参加者選択</span></li>
                <li>参加者を変更する時はこちら <span class="arrow">→</span> <span class="tab-ref">名簿</span></li>
            </ul>

            <div class="nav-actions">
                <a href="tournament.php">🏆 大会（対戦表）を見る</a>
                <a href="match.php">試合を始める / Let's Play</a>
                <a href="search.php" class="secondary">参加者選択</a>
                <a href="add_student.php" class="secondary">名簿</a>
            </div>

            <?php if (!empty($teams)): ?>
                <div class="delete-form">
                    <div class="dashboard-extra-actions">
                        <a href="tournament.php" class="btn-outline">🏆 大会表</a>
                        <a href="standings.php" class="btn-outline">📊 順位表</a>
                        <a href="history.php" class="btn-outline">📋 試合履歴</a>
                    </div>
                    <div class="dashboard-extra-actions" style="margin-top:10px;">
                        <button type="button" class="btn-outline" onclick="window.print()">🖨️ 印刷</button>
                        <a href="export.php?type=teams" class="btn-outline">📊 チームCSV</a>
                        <a href="export.php?type=students" class="btn-outline">📊 名簿CSV</a>
                    </div>
                    <form method="post" style="margin-top:14px;"
                          onsubmit="return confirm('全てのチームと試合を削除します。よろしいですか？');">
                        <button type="submit" name="delete_teams" class="btn-danger">
                            ゲーム終了 / チームを削除
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <div class="card" id="teams">
            <div class="table-toolbar">
                <h3>チーム一覧</h3>
                <div class="table-toolbar__actions">
                    <a href="create_team.php" class="btn-sm">➕ チーム追加</a>
                    <?php if (!empty($teams)): ?>
                        <button type="button" class="btn-icon" id="toggleEditMode" title="編集モード">
                            ✏️ <span class="btn-icon__text">一括編集</span>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (empty($teams)): ?>
                <p class="empty">
                    まだチームがありません。<br>
                    <a href="search.php">参加者を選んで自動シャッフル</a> するか、
                    <a href="create_team.php">手動でチームを作成</a> して下さい。
                </p>
            <?php else: ?>
                <div class="team-grid">
                    <?php foreach ($teams as $teamName => $members):
                        $tid = $teamIds[$teamName];
                    ?>
                        <div class="team-card" data-team-id="<?php echo $tid; ?>">
                            <div class="team-card__head">
                                <h3 class="team-card__title"><?php echo e($teamName); ?></h3>
                                <div class="team-card__actions">
                                    <a href="edit_team.php?id=<?php echo $tid; ?>" class="btn-sm btn-sm--edit" title="メンバーと名前を編集">✏️ 編集</a>
                                    <span class="team-card__badge"><?php echo count($members); ?>人</span>
                                </div>
                            </div>
                            <!-- 編集フォーム（チーム名変更） -->
                            <form method="post" action="api_teams.php" class="team-edit-form" style="display:none;">
                                <input type="hidden" name="team_id" value="<?php echo $tid; ?>">
                                <div class="team-edit-row">
                                    <input type="text" name="new_name" value="<?php echo e($teamName); ?>"
                                           class="team-edit-input" placeholder="新しいチーム名">
                                    <button type="submit" name="rename_team" class="btn-sm btn-sm--save">保存</button>
                                </div>
                            </form>
                            <ul class="member-list">
                                <?php foreach ($members as $m): ?>
                                    <li data-student-id="<?php echo $m['id']; ?>">
                                        <?php echo e($m['name']); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- メンバー入れ替えフォーム（編集モード時に表示） -->
                <div class="swap-form-container" id="swapForm" style="display:none;">
                    <h4 class="swap-form__title">🔄 メンバー入れ替え</h4>
                    <form method="post" action="api_teams.php" class="swap-form">
                        <div class="swap-form__row">
                            <div class="swap-form__select-group">
                                <label class="field-label" for="student_a">メンバー A</label>
                                <select name="student_a" id="student_a">
                                    <?php foreach ($allMembers as $m): ?>
                                        <option value="<?php echo $m['id']; ?>">
                                            <?php echo e($m['name']); ?> (<?php echo e($m['team']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <span class="swap-form__arrow">⇄</span>
                            <div class="swap-form__select-group">
                                <label class="field-label" for="student_b">メンバー B</label>
                                <select name="student_b" id="student_b">
                                    <?php foreach ($allMembers as $m): ?>
                                        <option value="<?php echo $m['id']; ?>">
                                            <?php echo e($m['name']); ?> (<?php echo e($m['team']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" name="swap_members" class="btn-primary">入れ替え実行</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // 参加者選択からの遷移(#dashboard)時は、バナー画像の読み込み完了後に
        // 本文（集計・「Let's Play!」）へスクロールして着地させる。
        if (location.hash === '#dashboard') {
            window.addEventListener('load', function () {
                var el = document.getElementById('dashboard');
                if (el) { el.scrollIntoView({ block: 'start' }); }
            });
        }
        if (location.hash === '#teams') {
            window.addEventListener('load', function () {
                var el = document.getElementById('teams');
                if (el) { el.scrollIntoView({ block: 'start', behavior: 'smooth' }); }
            });
        }

        // 編集モードの切り替え。
        (function () {
            var btn = document.getElementById('toggleEditMode');
            if (!btn) return;
            var editForms = document.querySelectorAll('.team-edit-form');
            var swapForm = document.getElementById('swapForm');
            var isEditing = false;

            btn.addEventListener('click', function () {
                isEditing = !isEditing;
                editForms.forEach(function (f) {
                    f.style.display = isEditing ? 'block' : 'none';
                });
                if (swapForm) {
                    swapForm.style.display = isEditing ? 'block' : 'none';
                }
                btn.innerHTML = isEditing
                    ? '✖ <span class="btn-icon__text">閉じる</span>'
                    : '✏️ <span class="btn-icon__text">編集</span>';
            });
        })();
    </script>
</body>
</html>
