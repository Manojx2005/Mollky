<?php
/**
 * チーム作成ページ。シャッフルボタンで shuffleTeams() を呼び出す。
 * 1チームの人数は参加者選択ページでセッションに保存された値を使う。
 */
require_once 'db.php';
require_once 'team_shuffle.php';
session_start();

$message = "";

/* 1チームの人数（セッション値。範囲外なら既定の5に丸める）。 */
const TEAM_SIZE_MIN = 2;
const TEAM_SIZE_MAX = 8;
$teamSize = (int) ($_SESSION['team_size'] ?? 5);
if ($teamSize < TEAM_SIZE_MIN || $teamSize > TEAM_SIZE_MAX) {
    $teamSize = 5;
}

/* シャッフル要求を処理する。 */
if (isset($_POST['shuffle'])) {
    try {
        $message = shuffleTeams($pdo, $teamSize);
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

/* HTML 出力用のエスケープ関数。 */
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
    <title>チームを作る</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'nav.php'; ?>
    <div class="content_box">
        <div class="page-title">
            <h2>チームを作る</h2>
            <p class="subtitle">参加者を1チーム<?php echo $teamSize; ?>人ずつに分けます</p>
        </div>

        <div class="card">
            <?php if ($message !== ""): ?>
                <p class="<?php echo str_starts_with($message, 'Error') ? 'message' : 'banner'; ?>">
                    <?php echo e($message); ?>
                </p>
            <?php endif; ?>

            <form method="post">
                <button type="submit" name="shuffle" class="btn-primary">
                    シャッフルしてチーム作成
                </button>
            </form>
        </div>

        <div class="links">
            <?php if ($message !== ""): ?>
                <a href="match.php">試合を始める</a>
            <?php endif; ?>
            <a href="index.php">To Dashboard</a>
        </div>
    </div>
</body>
</html>