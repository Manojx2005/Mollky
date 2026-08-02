<?php
/**
 * ダッシュボード（トップ）。集計の表示、チームのシャッフル作成、
 * 大会終了（チーム・試合の全削除）、チーム一覧の表示を行う。
 */
require_once 'db.php';
require_once 'team_shuffle.php';
session_start();

$message = "";

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
$stmt = $pdo->query("SELECT t.name as team_name, s.name as student_name
                     FROM teams t
                     JOIN team_members tm ON t.id = tm.team_id
                     JOIN students s ON tm.student_id = s.id
                     ORDER BY t.id");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* チーム名ごとにメンバー配列へ整形する。 */
$teams = [];
foreach ($results as $row) {
    $teams[$row['team_name']][] = $row['student_name'];
}

/* ダッシュボード上部に表示する集計値。 */
$totalStudents = (int) $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$activeStudents = (int) $pdo->query("SELECT COUNT(*) FROM students WHERE is_active = 1")->fetchColumn();
$totalTeams = count($teams);
$totalMatches = 0;
$ongoingMatches = 0;
if ($pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='molkky_matches'")->fetch()) {
    $totalMatches = (int) $pdo->query("SELECT COUNT(*) FROM `molkky_matches`")->fetchColumn();
    $ongoingMatches = (int) $pdo
        ->query("SELECT COUNT(*) FROM `molkky_matches` WHERE status = 'ongoing'")
        ->fetchColumn();
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

        <div class="card">
            <h3>Let's Play!</h3>

            <form method="post" class="shuffle-form">
                <button type="submit" name="shuffle" class="btn-primary">
                    シャッフルしてチーム作成（<?php echo $teamSize; ?>人 / チーム）
                </button>
            </form>

            <ul class="play-hints">
                <li>人数変更はこちら <span class="arrow">→</span> <span class="tab-ref">参加者選択</span></li>
                <li>参加者を変更する時はこちら <span class="arrow">→</span> <span class="tab-ref">名簿</span></li>
            </ul>

            <div class="nav-actions">
                <a href="match.php">試合を始める / Let's Play</a>
                <a href="search.php" class="secondary">参加者選択</a>
                <a href="add_student.php" class="secondary">名簿</a>
            </div>

            <?php if (!empty($teams)): ?>
                <form method="post" class="delete-form"
                      onsubmit="return confirm('全てのチームと試合を削除します。よろしいですか？');">
                    <button type="submit" name="delete_teams" class="btn-danger">
                        ゲーム終了 / チームを削除
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>チーム一覧</h3>
            <?php if (empty($teams)): ?>
                <p class="empty">まだチームがありません。参加者を選んでチームを作って下さい。</p>
            <?php else: ?>
                <div class="team-grid">
                    <?php foreach ($teams as $teamName => $members): ?>
                        <div class="team-card">
                            <div class="team-card__head">
                                <h3><?php echo e($teamName); ?></h3>
                                <span class="team-card__badge"><?php echo count($members); ?>人</span>
                            </div>
                            <ul class="member-list">
                                <?php foreach ($members as $name): ?>
                                    <li><?php echo e($name); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
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
    </script>
</body>
</html>
