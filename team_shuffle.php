<?php
/**
 * 参加中(is_active=1)の生徒をシャッフルし、$teamSize 人ずつのチームに分ける。
 * 成功時は完了メッセージを返す。人数が条件を満たさない場合は例外を投げる。
 */
function shuffleTeams(PDO $pdo, int $teamSize): string
{
    /* 対象の大会を1件取得する。 */
    $tournament = $pdo->query("SELECT id FROM tournaments LIMIT 1")
        ->fetch(PDO::FETCH_ASSOC);
    if (!$tournament) {
        throw new RuntimeException("大会が見つかりません。");
    }
    $tournamentId = $tournament['id'];

    /* 参加中の生徒を取得する。 */
    $students = $pdo->query("SELECT id FROM students WHERE is_active = 1")
        ->fetchAll(PDO::FETCH_COLUMN);

    /* 各チームちょうど $teamSize 人にするため、人数はその倍数である必要がある。 */
    $count = count($students);
    if ($count < $teamSize) {
        $need = $teamSize - $count;
        throw new RuntimeException(
            "参加者が{$count}人です。1チーム{$teamSize}人にするには、あと{$need}人選んで下さい。"
        );
    }
    if ($count % $teamSize !== 0) {
        $remainder = $count % $teamSize;
        $addMore = $teamSize - $remainder;
        throw new RuntimeException(
            "参加者が{$count}人です。{$teamSize}の倍数にして下さい。"
            . "あと{$addMore}人選ぶか、{$remainder}人減らして下さい。"
        );
    }

    /* シャッフルして $teamSize 人ずつに分割する。 */
    shuffle($students);
    $chunks = array_chunk($students, $teamSize);

    $pdo->beginTransaction();
    try {
        /* 作り直しのため、既存のチームを削除してから登録する。 */
        $pdo->exec("DELETE FROM team_members");
        $pdo->exec("DELETE FROM teams");

        foreach ($chunks as $index => $members) {
            $stmt = $pdo->prepare(
                "INSERT INTO teams (tournament_id, name) VALUES (?, ?)"
            );
            $stmt->execute([$tournamentId, "Team " . ($index + 1)]);
            $teamId = $pdo->lastInsertId();

            foreach ($members as $sId) {
                $stmt = $pdo->prepare(
                    "INSERT INTO team_members (team_id, student_id) VALUES (?, ?)"
                );
                $stmt->execute([$teamId, $sId]);
            }
        }
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return "チームを作成しました。";
}
