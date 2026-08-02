<?php
/**
 * 名簿ページ。生徒(参加者)を1人ずつデータベースに登録する。
 * 追加した生徒は既定で is_active=0（未参加）とし、参加者選択ページで選ばせる。
 */
require_once 'db.php';

$message = "";
$isError = false;

/* HTML 出力用のエスケープ関数。 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/* 追加フォームの送信を処理する。 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    $name = trim($_POST['name'] ?? '');
    /* 学籍番号は任意。空欄なら AUTOINCREMENT に任せる。 */
    $idRaw = trim($_POST['student_id'] ?? '');
    $studentId = $idRaw === ''
        ? null
        : filter_var($idRaw, FILTER_VALIDATE_INT);

    if ($name === '') {
        $message = "名前を入力して下さい。";
        $isError = true;
    } elseif ($idRaw !== '' && ($studentId === false || $studentId <= 0)) {
        $message = "学籍番号は正しい数字で入力して下さい。";
        $isError = true;
    } else {
        try {
            if ($studentId === null) {
                $stmt = $pdo->prepare(
                    "INSERT INTO students (name, is_active) VALUES (?, 0)"
                );
                $stmt->execute([$name]);
                $studentId = $pdo->lastInsertId();
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO students (id, name, is_active) VALUES (?, ?, 0)"
                );
                $stmt->execute([$studentId, $name]);
            }
            $message = "追加しました: {$name}（学籍番号 {$studentId}）";
        } catch (PDOException $e) {
            $isError = true;
            /* 学籍番号(id)の重複。 */
            if ($e->getCode() === '23000') {
                $message = "その学籍番号は既に登録されています。";
            } else {
                $message = "Error: " . $e->getMessage();
            }
        }
    }
}

/* ヘッダー表示用の登録済み生徒数。 */
$total = (int) $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>学生を追加</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'nav.php'; ?>
    <div class="content_box">
        <div class="page-title">
            <h2>学生を追加</h2>
            <p class="subtitle">データベースに参加者を登録します（現在 <?php echo $total; ?>人）</p>
        </div>

        <div class="card">
            <?php if ($message !== ""): ?>
                <p class="<?php echo $isError ? 'message' : 'banner'; ?>">
                    <?php echo e($message); ?>
                </p>
            <?php endif; ?>

            <form method="post">
                <label class="field-label" for="name">名前</label>
                <input type="text" id="name" name="name" required
                       placeholder="例: 山田 太郎" autocomplete="off">

                <label class="field-label" for="student_id">学籍番号（任意）</label>
                <input type="number" id="student_id" name="student_id" min="1"
                       placeholder="空欄なら自動で採番" autocomplete="off">

                <div class="actions">
                    <button type="submit" name="add_student" class="btn-primary">追加する</button>
                </div>
            </form>
        </div>

        <div class="links">
            <a href="search.php">参加者を選択</a>
            <a href="index.php">To Dashboard</a>
        </div>
    </div>
</body>
</html>
