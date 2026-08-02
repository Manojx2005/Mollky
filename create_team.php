<?php
/**
 * 手動チーム作成ページ。チーム名を指定し、生徒名簿から
 * メンバーを選んで自由な人数でチームを作成する。
 */
require_once 'db.php';
session_start();

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$message = "";
$isError = false;

/* チーム作成処理。 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_team'])) {
    $teamName = trim($_POST['team_name'] ?? '');
    $selected = isset($_POST['members']) && is_array($_POST['members'])
        ? array_values(array_unique(array_filter(array_map(
            static fn ($id) => filter_var($id, FILTER_VALIDATE_INT),
            $_POST['members']
        ), static fn ($id) => $id !== false)))
        : [];

    if ($teamName === '') {
        $message = "チーム名を入力して下さい。";
        $isError = true;
    } elseif (empty($selected)) {
        $message = "少なくとも1人のメンバーを選んで下さい。";
        $isError = true;
    } else {
        $pdo->beginTransaction();
        try {
            $tournament = $pdo->query("SELECT id FROM tournaments LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if (!$tournament) {
                throw new RuntimeException("大会が見つかりません。");
            }

            $stmt = $pdo->prepare("INSERT INTO teams (tournament_id, name) VALUES (?, ?)");
            $stmt->execute([$tournament['id'], $teamName]);
            $newTeamId = $pdo->lastInsertId();

            $insTm = $pdo->prepare("INSERT INTO team_members (team_id, student_id) VALUES (?, ?)");
            $actSt = $pdo->prepare("UPDATE students SET is_active = 1 WHERE id = ?");

            foreach ($selected as $sId) {
                /* 既に他のチームに所属している場合はスキップする。 */
                $check = $pdo->prepare("SELECT 1 FROM team_members WHERE student_id = ?");
                $check->execute([$sId]);
                if ($check->fetchColumn()) continue;

                $insTm->execute([$newTeamId, $sId]);
                $actSt->execute([$sId]);
            }
            $pdo->commit();
            $message = "「{$teamName}」を作成しました！";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $message = "Error: " . $e->getMessage();
            $isError = true;
        }
    }
}

/* チーム未所属の生徒全員（登録中すべて）。 */
$available = $pdo->query(
    "SELECT s.id, s.name FROM students s
     WHERE s.id NOT IN (SELECT student_id FROM team_members)
     ORDER BY s.id"
)->fetchAll(PDO::FETCH_ASSOC);

/* 既存チーム一覧（参考表示用）。 */
$existingTeams = $pdo->query(
    "SELECT t.name as team_name, COUNT(tm.student_id) as cnt
     FROM teams t
     LEFT JOIN team_members tm ON t.id = tm.team_id
     GROUP BY t.id
     ORDER BY t.id"
)->fetchAll(PDO::FETCH_ASSOC);

/* 次のチーム番号候補。 */
$teamCount = (int) $pdo->query("SELECT COUNT(*) FROM teams")->fetchColumn();
$suggestedName = "Team " . ($teamCount + 1);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>チームを手動作成</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'nav.php'; ?>
    <div class="content_box">
        <div class="page-title">
            <h2>チームを手動作成</h2>
            <p class="subtitle">チーム名を決めて、メンバーを自由に選んで作成します</p>
        </div>

        <?php if ($message !== ""): ?>
            <p class="<?php echo $isError ? 'message' : 'banner'; ?>"><?php echo e($message); ?></p>
        <?php endif; ?>

        <?php if (!empty($existingTeams)): ?>
            <div class="card">
                <h3>📋 既存チーム</h3>
                <div class="existing-teams-list">
                    <?php foreach ($existingTeams as $et): ?>
                        <span class="existing-team-chip">
                            <?php echo e($et['team_name']); ?>
                            <span class="existing-team-chip__count"><?php echo $et['cnt']; ?>人</span>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="card">
            <h3>➕ 新しいチーム</h3>

            <?php if (empty($available)): ?>
                <p class="empty">チーム未所属の登録生徒がいません。<br>
                    <a href="add_student.php">新規生徒を追加</a> するか、
                    既存チームの<a href="index.php#teams">メンバーを編集</a>して下さい。
                </p>
            <?php else: ?>
                <form method="post" id="createTeamForm">
                    <label class="field-label" for="team_name">チーム名</label>
                    <input type="text" id="team_name" name="team_name"
                           value="<?php echo e($suggestedName); ?>" required
                           placeholder="例: Team A" autocomplete="off">

                    <div class="search" style="margin-top:14px;">
                        <input type="text" id="memberFilter"
                               placeholder="名前 または 学籍番号で検索" autocomplete="off">
                    </div>

                    <div class="toolbar">
                        <span class="count" id="memberCount">選択: <strong>0</strong>人</span>
                        <button type="button" class="link-btn" id="toggleAll">すべて選択</button>
                    </div>

                    <ul class="students" id="memberList">
                        <?php foreach ($available as $s): ?>
                            <li data-search="<?php echo e($s['id'] . ' ' . $s['name']); ?>">
                                <label>
                                    <input type="checkbox" name="members[]"
                                           value="<?php echo e((string) $s['id']); ?>">
                                    <span class="sid"><?php echo e((string) $s['id']); ?></span>
                                    <span class="name"><?php echo e($s['name']); ?></span>
                                </label>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="actions">
                        <button type="submit" name="create_team" class="btn-primary">チームを作成</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <div class="links">
            <a href="add_student.php">新規生徒の追加</a>
            <a href="index.php">ダッシュボードへ</a>
        </div>
    </div>

    <script>
        (function () {
            var filter = document.getElementById('memberFilter');
            var items = Array.from(document.querySelectorAll('#memberList li'));
            var countEl = document.getElementById('memberCount');
            var toggleBtn = document.getElementById('toggleAll');
            if (!filter || !items.length) return;

            var checkboxes = function () {
                return Array.from(document.querySelectorAll('input[name="members[]"]'));
            };

            function updateCount() {
                var boxes = checkboxes();
                var n = boxes.filter(function (cb) { return cb.checked; }).length;
                countEl.innerHTML = '選択: <strong>' + n + '</strong>人';
                if (toggleBtn) {
                    var allChecked = boxes.length > 0 && n === boxes.length;
                    toggleBtn.textContent = allChecked ? 'すべて解除' : 'すべて選択';
                }
            }

            filter.addEventListener('input', function () {
                var q = filter.value.trim().toLowerCase();
                items.forEach(function (li) {
                    var hay = (li.dataset.search || '').toLowerCase();
                    li.style.display = hay.includes(q) ? '' : 'none';
                });
            });

            if (toggleBtn) {
                toggleBtn.addEventListener('click', function () {
                    var boxes = checkboxes();
                    var target = !(boxes.length > 0 && boxes.every(function (cb) { return cb.checked; }));
                    boxes.forEach(function (cb) { cb.checked = target; });
                    updateCount();
                });
            }

            document.getElementById('memberList').addEventListener('change', updateCount);
            updateCount();
        })();
    </script>
</body>
</html>
