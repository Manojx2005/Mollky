<?php
/**
 * チーム編集ページ。チーム名の変更、メンバーの追加・削除・移動を行う。
 * ?id=<team_id> で対象チームを指定する。
 */
require_once 'db.php';
session_start();

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$message = "";
$isError = false;

$teamId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT)
    ?: filter_input(INPUT_POST, 'team_id', FILTER_VALIDATE_INT);

if (!$teamId) {
    header('Location: index.php');
    exit;
}

/* チーム情報を取得する。 */
$stmt = $pdo->prepare("SELECT * FROM teams WHERE id = ?");
$stmt->execute([$teamId]);
$team = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$team) {
    header('Location: index.php');
    exit;
}

/* --- チーム名を変更する --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rename_team'])) {
    $newName = trim($_POST['new_name'] ?? '');
    if ($newName === '') {
        $message = "チーム名を入力して下さい。";
        $isError = true;
    } else {
        $stmt = $pdo->prepare("UPDATE teams SET name = ? WHERE id = ?");
        $stmt->execute([$newName, $teamId]);
        $team['name'] = $newName;
        $message = "チーム名を「{$newName}」に変更しました。";
    }
}

/* --- 既存生徒をメンバーに追加する --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_member'])) {
    $studentId = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
    if ($studentId) {
        /* 既に別のチームに所属していないか確認する。 */
        $check = $pdo->prepare("SELECT team_id FROM team_members WHERE student_id = ?");
        $check->execute([$studentId]);
        $existingTeam = $check->fetchColumn();

        if ($existingTeam) {
            $message = "この生徒は既に別のチームに所属しています。";
            $isError = true;
        } else {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO team_members (team_id, student_id) VALUES (?, ?)");
                $stmt->execute([$teamId, $studentId]);
                /* 試合に参加できるよう is_active = 1 に設定する */
                $pdo->prepare("UPDATE students SET is_active = 1 WHERE id = ?")->execute([$studentId]);
                $pdo->commit();
                $message = "メンバーを追加しました。";
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $message = "Error: " . $e->getMessage();
                $isError = true;
            }
        }
    }
}

/* --- 新しい生徒をその場で作成してチームに追加する --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_and_add_member'])) {
    $newMemberName = trim($_POST['new_member_name'] ?? '');
    $newMemberIdRaw = trim($_POST['new_member_id'] ?? '');
    $newMemberId = $newMemberIdRaw === '' ? null : filter_var($newMemberIdRaw, FILTER_VALIDATE_INT);

    if ($newMemberName === '') {
        $message = "名前を入力して下さい。";
        $isError = true;
    } elseif ($newMemberIdRaw !== '' && ($newMemberId === false || $newMemberId <= 0)) {
        $message = "学籍番号は正の数字で入力して下さい。";
        $isError = true;
    } else {
        $pdo->beginTransaction();
        try {
            if ($newMemberId === null) {
                $ins = $pdo->prepare("INSERT INTO students (name, is_active) VALUES (?, 1)");
                $ins->execute([$newMemberName]);
                $newMemberId = $pdo->lastInsertId();
            } else {
                $ins = $pdo->prepare("INSERT INTO students (id, name, is_active) VALUES (?, ?, 1)");
                $ins->execute([$newMemberId, $newMemberName]);
            }
            $insTm = $pdo->prepare("INSERT INTO team_members (team_id, student_id) VALUES (?, ?)");
            $insTm->execute([$teamId, $newMemberId]);
            $pdo->commit();
            $message = "「{$newMemberName}」を新規登録してチームに追加しました！";
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($e->getCode() === '23000') {
                $message = "その学籍番号は既に登録されています。";
            } else {
                $message = "Error: " . $e->getMessage();
            }
            $isError = true;
        }
    }
}

/* --- メンバーを外す --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_member'])) {
    $studentId = filter_input(INPUT_POST, 'remove_student_id', FILTER_VALIDATE_INT);
    if ($studentId) {
        $stmt = $pdo->prepare("DELETE FROM team_members WHERE team_id = ? AND student_id = ?");
        $stmt->execute([$teamId, $studentId]);
        $message = "メンバーを外しました。";
    }
}

/* --- メンバーを別のチームへ移動する --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['move_member'])) {
    $studentId = filter_input(INPUT_POST, 'move_student_id', FILTER_VALIDATE_INT);
    $toTeamId = filter_input(INPUT_POST, 'to_team_id', FILTER_VALIDATE_INT);
    if ($studentId && $toTeamId && $toTeamId !== $teamId) {
        $pdo->beginTransaction();
        try {
            $pdo->prepare("DELETE FROM team_members WHERE team_id = ? AND student_id = ?")->execute([$teamId, $studentId]);
            $pdo->prepare("INSERT INTO team_members (team_id, student_id) VALUES (?, ?)")->execute([$toTeamId, $studentId]);
            $pdo->commit();
            $message = "メンバーを移動しました。";
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $message = "Error: " . $e->getMessage();
            $isError = true;
        }
    }
}

/* 現在のメンバーを取得する。 */
$members = $pdo->prepare(
    "SELECT s.id, s.name FROM team_members tm
     JOIN students s ON s.id = tm.student_id
     WHERE tm.team_id = ? ORDER BY s.id"
);
$members->execute([$teamId]);
$members = $members->fetchAll(PDO::FETCH_ASSOC);

/* どのチームにも所属していない生徒全員（追加候補）。 */
$available = $pdo->query(
    "SELECT s.id, s.name FROM students s
     WHERE s.id NOT IN (SELECT student_id FROM team_members)
     ORDER BY s.id"
)->fetchAll(PDO::FETCH_ASSOC);

/* 他のチーム一覧（移動先用）。 */
$otherTeams = $pdo->prepare("SELECT id, name FROM teams WHERE id != ? ORDER BY id");
$otherTeams->execute([$teamId]);
$otherTeams = $otherTeams->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>チーム編集 — <?php echo e($team['name']); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'nav.php'; ?>
    <div class="content_box">
        <div class="page-title">
            <h2>チーム編集</h2>
            <p class="subtitle"><?php echo e($team['name']); ?> のメンバーと設定を変更</p>
        </div>

        <?php if ($message !== ""): ?>
            <p class="<?php echo $isError ? 'message' : 'banner'; ?>"><?php echo e($message); ?></p>
        <?php endif; ?>

        <!-- チーム名変更 -->
        <div class="card">
            <h3>📝 チーム名</h3>
            <form method="post">
                <input type="hidden" name="team_id" value="<?php echo $teamId; ?>">
                <div class="team-edit-row">
                    <input type="text" name="new_name" value="<?php echo e($team['name']); ?>"
                           class="team-edit-input" placeholder="新しいチーム名" required>
                    <button type="submit" name="rename_team" class="btn-sm btn-sm--save">変更保存</button>
                </div>
            </form>
        </div>

        <!-- 現在のメンバー -->
        <div class="card">
            <h3>👥 メンバー（<?php echo count($members); ?>人）</h3>
            <?php if (empty($members)): ?>
                <p class="empty">メンバーがいません。下の追加フォームから追加して下さい。</p>
            <?php else: ?>
                <ul class="edit-member-list">
                    <?php foreach ($members as $m): ?>
                        <li class="edit-member-item">
                            <div class="edit-member-info">
                                <span class="edit-member-id"><?php echo $m['id']; ?></span>
                                <span class="edit-member-name"><?php echo e($m['name']); ?></span>
                            </div>
                            <div class="edit-member-actions">
                                <?php if (!empty($otherTeams)): ?>
                                    <form method="post" class="edit-member-move-form">
                                        <input type="hidden" name="team_id" value="<?php echo $teamId; ?>">
                                        <input type="hidden" name="move_student_id" value="<?php echo $m['id']; ?>">
                                        <select name="to_team_id" class="edit-member-select">
                                            <?php foreach ($otherTeams as $ot): ?>
                                                <option value="<?php echo $ot['id']; ?>"><?php echo e($ot['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="move_member" class="btn-sm" title="移動">→ 移動</button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" class="edit-member-remove-form"
                                      onsubmit="return confirm('<?php echo e($m['name']); ?> をこのチームから外しますか？');">
                                    <input type="hidden" name="team_id" value="<?php echo $teamId; ?>">
                                    <input type="hidden" name="remove_student_id" value="<?php echo $m['id']; ?>">
                                    <button type="submit" name="remove_member" class="btn-sm btn-sm--danger" title="外す">✕ 外す</button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <!-- 既存の登録生徒からメンバー追加 -->
        <div class="card">
            <h3>➕ 登録済みの生徒から追加</h3>
            <?php if (empty($available)): ?>
                <p class="empty">チームに未所属の登録生徒はいません。</p>
            <?php else: ?>
                <p class="subtitle" style="margin-bottom:12px;">チームに未所属の生徒を選択して追加できます（全 <?php echo count($available); ?> 人）</p>
                <form method="post">
                    <input type="hidden" name="team_id" value="<?php echo $teamId; ?>">
                    <select name="student_id" class="edit-add-select" style="width:100% !important; margin-bottom:10px;">
                        <?php foreach ($available as $s): ?>
                            <option value="<?php echo $s['id']; ?>"><?php echo e($s['name']); ?>（ID: <?php echo $s['id']; ?>）</option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" name="add_member" class="btn-primary">この生徒を追加</button>
                </form>
            <?php endif; ?>
        </div>

        <!-- 新しい生徒をその場で作成して追加 -->
        <div class="card">
            <h3>✨ 新しい生徒を作成して追加</h3>
            <p class="subtitle" style="margin-bottom:12px;">名簿にない新しいメンバーを直接追加できます</p>
            <form method="post">
                <input type="hidden" name="team_id" value="<?php echo $teamId; ?>">
                <label class="field-label" for="new_member_name">名前</label>
                <input type="text" id="new_member_name" name="new_member_name" required
                       placeholder="例: 新規 太郎" autocomplete="off" style="margin-bottom:10px;">

                <label class="field-label" for="new_member_id">学籍番号（任意）</label>
                <input type="number" id="new_member_id" name="new_member_id" min="1"
                       placeholder="空欄なら自動で採番" autocomplete="off" style="margin-bottom:14px;">

                <button type="submit" name="create_and_add_member" class="btn-primary">新規作成して追加</button>
            </form>
        </div>

        <div class="links">
            <a href="index.php#teams">チーム一覧へ戻る</a>
            <a href="index.php">ダッシュボードへ</a>
        </div>
    </div>
</body>
</html>
