<?php
/**
 * チーム編集 API。チーム名の変更とメンバーの入れ替えを処理する。
 * POST で受け取り、完了後にダッシュボードへリダイレクトする。
 */
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$message = '';

/* チーム名を変更する。 */
if (isset($_POST['rename_team'])) {
    $teamId = filter_input(INPUT_POST, 'team_id', FILTER_VALIDATE_INT);
    $newName = trim($_POST['new_name'] ?? '');

    if (!$teamId || $newName === '') {
        $message = 'チーム名を入力して下さい。';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE teams SET name = ? WHERE id = ?");
            $stmt->execute([$newName, $teamId]);
            $message = 'チーム名を変更しました。';
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
        }
    }
}

/* メンバーを入れ替える（2チーム間で1人ずつ交換する）。 */
if (isset($_POST['swap_members'])) {
    $studentA = filter_input(INPUT_POST, 'student_a', FILTER_VALIDATE_INT);
    $studentB = filter_input(INPUT_POST, 'student_b', FILTER_VALIDATE_INT);

    if (!$studentA || !$studentB || $studentA === $studentB) {
        $message = '異なる2人のメンバーを選んで下さい。';
    } else {
        /* 各生徒が所属するチームを特定する。 */
        $stmt = $pdo->prepare("SELECT team_id FROM team_members WHERE student_id = ?");
        $stmt->execute([$studentA]);
        $teamA = $stmt->fetchColumn();

        $stmt->execute([$studentB]);
        $teamB = $stmt->fetchColumn();

        if (!$teamA || !$teamB) {
            $message = 'メンバーがチームに所属していません。';
        } elseif ($teamA === $teamB) {
            $message = '同じチーム内のメンバーは入れ替えられません。';
        } else {
            $pdo->beginTransaction();
            try {
                /* 一旦削除してから入れ替え先のチームに追加する。 */
                $del = $pdo->prepare("DELETE FROM team_members WHERE team_id = ? AND student_id = ?");
                $ins = $pdo->prepare("INSERT INTO team_members (team_id, student_id) VALUES (?, ?)");

                $del->execute([$teamA, $studentA]);
                $del->execute([$teamB, $studentB]);
                $ins->execute([$teamB, $studentA]);
                $ins->execute([$teamA, $studentB]);

                $pdo->commit();
                $message = 'メンバーを入れ替えました。';
            } catch (PDOException $e) {
                $pdo->rollBack();
                $message = 'Error: ' . $e->getMessage();
            }
        }
    }
}

/* ダッシュボードへ戻る。 */
session_start();
$_SESSION['team_message'] = $message;
header('Location: index.php#teams');
exit;
