<?php
/**
 * データベース接続とスキーマ初期化（SQLite）。
 * MySQL/XAMPP を使わず、1つのファイル(molkky.sqlite)で完結する。
 * テーブルが無ければ作成し、初回のみ seed.sql を読み込む。
 */
$dbFile = __DIR__ . '/molkky.sqlite';

$pdo = new PDO("sqlite:" . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("PRAGMA foreign_keys = ON");

/* クラス */
$pdo->exec("CREATE TABLE IF NOT EXISTS classes (
    id   INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL
)");

/* 生徒。is_active = 1 が「試合に参加する」。class_id の既定値により
   class_id を省略する add_student.php でも登録できる。 */
$pdo->exec("CREATE TABLE IF NOT EXISTS students (
    id        INTEGER PRIMARY KEY AUTOINCREMENT,
    class_id  INTEGER NOT NULL DEFAULT 1,
    name      TEXT NOT NULL,
    is_active INTEGER NOT NULL DEFAULT 1,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
)");

/* 大会。SQLite に ENUM が無いため TEXT + CHECK で列挙値を表現する。 */
$pdo->exec("CREATE TABLE IF NOT EXISTS tournaments (
    id        INTEGER PRIMARY KEY AUTOINCREMENT,
    name      TEXT NOT NULL,
    format    TEXT NOT NULL DEFAULT 'round_robin'
                   CHECK (format IN ('round_robin','knockout')),
    team_size INTEGER NOT NULL DEFAULT 3,
    status    TEXT NOT NULL DEFAULT 'setup'
                   CHECK (status IN ('setup','running','finished'))
)");

/* チーム */
$pdo->exec("CREATE TABLE IF NOT EXISTS teams (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    tournament_id INTEGER NOT NULL,
    name          TEXT NOT NULL,
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE
)");

/* チームメンバー（チームと生徒の対応） */
$pdo->exec("CREATE TABLE IF NOT EXISTS team_members (
    team_id    INTEGER NOT NULL,
    student_id INTEGER NOT NULL,
    PRIMARY KEY (team_id, student_id),
    FOREIGN KEY (team_id)    REFERENCES teams(id)    ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
)");

/* 試合（スキーマ準拠用）。実際の得点処理は match.php が molkky_matches を
   別途作成して行う。 */
$pdo->exec("CREATE TABLE IF NOT EXISTS matches (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    tournament_id  INTEGER NOT NULL,
    round_no       INTEGER NOT NULL DEFAULT 1,
    team_a_id      INTEGER NULL,
    team_b_id      INTEGER NULL,
    score_a        INTEGER NOT NULL DEFAULT 0,
    score_b        INTEGER NOT NULL DEFAULT 0,
    winner_team_id INTEGER NULL,
    status         TEXT NOT NULL DEFAULT 'pending'
                        CHECK (status IN ('pending','live','done')),
    FOREIGN KEY (tournament_id)  REFERENCES tournaments(id) ON DELETE CASCADE,
    FOREIGN KEY (team_a_id)      REFERENCES teams(id) ON DELETE SET NULL,
    FOREIGN KEY (team_b_id)      REFERENCES teams(id) ON DELETE SET NULL,
    FOREIGN KEY (winner_team_id) REFERENCES teams(id) ON DELETE SET NULL
)");

/* 初回のみ生徒データを投入する。 */
$studentCount = (int) $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
if ($studentCount === 0) {
    $seedFile = __DIR__ . '/seed.sql';
    if (is_file($seedFile)) {
        $pdo->exec(file_get_contents($seedFile));
    }
}

/* シャッフルや試合には大会が最低1件必要なので用意しておく。 */
if (!$pdo->query("SELECT id FROM tournaments LIMIT 1")->fetch()) {
    $pdo->exec("INSERT INTO tournaments (name) VALUES ('モルック大会')");
}
