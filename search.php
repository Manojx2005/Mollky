<?php
/**
 * 参加者選択ページ。試合に出る生徒をチェックで選び、is_active を更新する。
 * 1チームの人数もここで選び、セッションに保存してチーム作成へ引き継ぐ。
 */
require_once 'db.php';
session_start();

$message = "";

/* 1チームの人数の選択範囲と既定値。 */
const TEAM_SIZE_MIN = 2;
const TEAM_SIZE_MAX = 8;
const TEAM_SIZE_DEFAULT = 5;

/* 現在のチーム人数を「送信値 → セッション → 既定値」の優先順で決める。 */
$teamSize = filter_input(INPUT_POST, 'team_size', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => TEAM_SIZE_MIN, 'max_range' => TEAM_SIZE_MAX],
]);
if ($teamSize === false || $teamSize === null) {
    $teamSize = (int) ($_SESSION['team_size'] ?? TEAM_SIZE_DEFAULT);
}
/* 次ページ（チーム作成）でも使えるように保存する。 */
$_SESSION['team_size'] = $teamSize;

/* 送信時: チェックされた生徒だけを is_active = 1（参加）にする。 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start'])) {
    /* 送信値(students.id)を整数のみに絞り、重複を除く。 */
    $selected = isset($_POST['students']) && is_array($_POST['students'])
        ? array_values(array_unique(array_filter(array_map(
            static fn ($id) => filter_var($id, FILTER_VALIDATE_INT),
            $_POST['students']
        ), static fn ($id) => $id !== false)))
        : [];

    $count = count($selected);
    if ($count < $teamSize) {
        $message = "1チーム" . $teamSize . "人です。あと"
            . ($teamSize - $count) . "人選んで下さい。";
    } elseif ($count % $teamSize !== 0) {
        $remainder = $count % $teamSize;
        $message = $count . "人選択中です。" . $teamSize . "の倍数にして下さい。"
            . "あと" . ($teamSize - $remainder) . "人選ぶか、"
            . $remainder . "人減らして下さい。";
    } else {
        $pdo->beginTransaction();
        try {
            /* 一旦全員を非参加にし、選ばれた生徒だけ参加に戻す。 */
            $pdo->exec("UPDATE students SET is_active = 0");

            $placeholders = implode(',', array_fill(0, count($selected), '?'));
            $stmt = $pdo->prepare(
                "UPDATE students SET is_active = 1 WHERE id IN ($placeholders)"
            );
            $stmt->execute($selected);

            $pdo->commit();

            /* ダッシュボードの本文（#dashboard）へ戻る。バナー先頭ではなく
               集計・「Let's Play!」の位置に着地させ、そのままチーム作成できる。 */
            header('Location: index.php#dashboard');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Error: " . $e->getMessage();
        }
    }
}

/* 生徒一覧を取得する（キーワード絞り込みはブラウザ側の JS で行う）。 */
$students = $pdo
    ->query("SELECT id, name, is_active FROM students ORDER BY id")
    ->fetchAll(PDO::FETCH_ASSOC);

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
    <title>参加者を選択</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'nav.php'; ?>
    <div class="content_box">
        <div class="page-title">
            <h2>参加者を選択</h2>
            <p class="subtitle">試合に参加する学生を選んで下さい</p>
        </div>
        <div class="card">

            <?php if ($message !== ""): ?>
                <p class="message"><?php echo e($message); ?></p>
            <?php endif; ?>

            <div class="search">
                <input type="text" id="filter"
                       placeholder="学籍番号 または 名前で検索" autocomplete="off">
            </div>

            <form method="post" action="">
                <div class="team-size-row">
                    <label class="field-label" for="team_size">1チームあたりの人数</label>
                    <select name="team_size" id="team_size">
                        <?php for ($n = TEAM_SIZE_MIN; $n <= TEAM_SIZE_MAX; $n++): ?>
                            <option value="<?php echo $n; ?>" <?php echo $n === $teamSize ? 'selected' : ''; ?>>
                                <?php echo $n; ?>人
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="toolbar">
                    <span class="count" id="count">選択: <strong>0</strong>人</span>
                    <button type="button" class="link-btn" id="toggle_all">すべて選択</button>
                </div>
                <p class="hint" id="hint" data-team-size="<?php echo $teamSize; ?>"></p>

                <ul class="students" id="student_list">
                    <?php foreach ($students as $s): ?>
                        <li data-search="<?php echo e($s['id'] . ' ' . $s['name']); ?>">
                            <label>
                                <input type="checkbox" name="students[]"
                                       value="<?php echo e((string) $s['id']); ?>">
                                <span class="sid"><?php echo e((string) $s['id']); ?></span>
                                <span class="name"><?php echo e($s['name']); ?></span>
                            </label>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <?php if (empty($students)): ?>
                    <p class="empty">学生が登録されていません。</p>
                <?php endif; ?>

                <div class="actions">
                    <button type="submit" name="start">送信して開始</button>
                </div>
            </form>

            <p class="dashboard-link">
                <a href="add_student.php">学生を追加</a>
                <a href="index.php">To Dashboard</a>
            </p>
        </div>
    </div>

    <script>
        // 選択人数のカウント・チーム分けの可否ヒント・キーワード絞り込みを担う。
        const filter = document.getElementById('filter');
        const items = Array.from(document.querySelectorAll('#student_list li'));
        const countEl = document.getElementById('count');
        const hintEl = document.getElementById('hint');
        const teamSizeSelect = document.getElementById('team_size');
        let teamSize = parseInt(hintEl.dataset.teamSize, 10) || 5;
        const toggleBtn = document.getElementById('toggle_all');
        const checkboxes = () =>
            Array.from(document.querySelectorAll('input[name="students[]"]'));

        function updateCount() {
            const boxes = checkboxes();
            const n = boxes.filter((cb) => cb.checked).length;
            countEl.innerHTML = '選択: <strong>' + n + '</strong>人';
            if (toggleBtn) {
                const allChecked = boxes.length > 0 && n === boxes.length;
                toggleBtn.textContent = allChecked ? 'すべて解除' : 'すべて選択';
            }
            updateHint(n);
        }

        function updateHint(n) {
            if (!hintEl) return;
            const remainder = n % teamSize;
            if (n === 0) {
                hintEl.textContent = '1チーム' + teamSize + '人です。' + teamSize + 'の倍数を選んで下さい。';
                hintEl.className = 'hint';
            } else if (remainder === 0) {
                hintEl.textContent = '✓ ' + (n / teamSize) + 'チーム作れます（各' + teamSize + '人）。';
                hintEl.className = 'hint hint--ok';
            } else {
                const addMore = teamSize - remainder;
                hintEl.textContent = 'あと' + addMore + '人選ぶか、' + remainder + '人減らして下さい（' + teamSize + 'の倍数に）。';
                hintEl.className = 'hint hint--warn';
            }
        }

        filter.addEventListener('input', () => {
            const q = filter.value.trim().toLowerCase();
            items.forEach((li) => {
                const hay = (li.dataset.search || '').toLowerCase();
                li.style.display = hay.includes(q) ? '' : 'none';
            });
        });

        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                const boxes = checkboxes();
                const target = !(boxes.length > 0 && boxes.every((cb) => cb.checked));
                boxes.forEach((cb) => { cb.checked = target; });
                updateCount();
            });
        }

        document.getElementById('student_list')
            .addEventListener('change', updateCount);

        if (teamSizeSelect) {
            teamSizeSelect.addEventListener('change', () => {
                teamSize = parseInt(teamSizeSelect.value, 10) || teamSize;
                updateCount();
            });
        }

        updateCount();
    </script>
</body>
</html>
