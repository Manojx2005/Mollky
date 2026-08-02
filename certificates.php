<?php
/**
 * 賞状（賞状ジェネレーター・表彰状）ページ。
 * 優勝・準優勝・3位チーム、および MVP 選手へ印刷可能な日本語伝統の賞状を発行。
 */
require_once 'db.php';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/* 大会基本情報 */
$tournament = $pdo->query("SELECT * FROM tournaments LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$tournamentName = $tournament['name'] ?? 'モルック 大会';

/* 集計により 1位、2位、3位 チームを取得 */
$teams = $pdo->query("SELECT id, name FROM teams ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$standings = [];
foreach ($teams as $t) {
    $tid = (int)$t['id'];
    $stmtM = $pdo->prepare(
        "SELECT s.name FROM team_members tm
         JOIN students s ON s.id = tm.student_id
         WHERE tm.team_id = ? ORDER BY s.id"
    );
    $stmtM->execute([$tid]);
    $members = $stmtM->fetchAll(PDO::FETCH_COLUMN);

    $standings[$tid] = [
        'id' => $tid,
        'name' => $t['name'],
        'members' => implode('・', $members),
        'wins' => 0,
        'pf' => 0,
        'pa' => 0,
    ];
}

$hasMatches = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='molkky_matches'")->fetch();
if ($hasMatches) {
    $matches = $pdo->query("SELECT * FROM molkky_matches WHERE status = 'finished'")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($matches as $m) {
        $t1 = (int) $m['team1_id'];
        $t2 = (int) $m['team2_id'];
        $w = (int) $m['winner_team_id'];
        if (isset($standings[$t1])) {
            $standings[$t1]['pf'] += (int)$m['team1_score'];
            $standings[$t1]['pa'] += (int)$m['team2_score'];
            if ($w === $t1) $standings[$t1]['wins']++;
        }
        if (isset($standings[$t2])) {
            $standings[$t2]['pf'] += (int)$m['team2_score'];
            $standings[$t2]['pa'] += (int)$m['team1_score'];
            if ($w === $t2) $standings[$t2]['wins']++;
        }
    }
}

usort($standings, function ($a, $b) {
    $c = $b['wins'] <=> $a['wins'];
    return $c !== 0 ? $c : ($b['pf'] - $b['pa']) <=> ($a['pf'] - $a['pa']);
});

$top3 = array_slice($standings, 0, 3);
$awards = [
    0 => ['title' => '賞状 — 優勝', 'rank' => '優勝', 'badge' => '🥇 優勝', 'desc' => '貴チームは当大会において頭書のとおり極めて優秀な成績を収められました。その栄誉を称えここに表彰いたします。'],
    1 => ['title' => '賞状 — 準優勝', 'rank' => '準優勝', 'badge' => '🥈 準優勝', 'desc' => '貴チームは当大会において頭書のとおり優れたチームワークを発揮し見事優秀な成績を収められました。ここに表彰いたします。'],
    2 => ['title' => '賞状 — 第3位', 'rank' => '第3位', 'badge' => '🥉 第3位', 'desc' => '貴チームは当大会において頭書のとおり健闘し優秀な成績を収められました。ここに表彰いたします。'],
];

/* 印刷対象の切り替え (?award=0|1|2) */
$selectedAward = (int)($_GET['award'] ?? 0);
if ($selectedAward < 0 || $selectedAward > 2) $selectedAward = 0;
$awardData = $awards[$selectedAward] ?? $awards[0];
$targetTeam = $top3[$selectedAward] ?? ($standings[0] ?? ['name' => '未定チーム', 'members' => '—']);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>表彰状・賞状発行 — モルック</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .cert-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            justify-content: center;
        }
        .cert-frame {
            border: 12px double #d4af37;
            background: #fffdf7;
            padding: 40px 30px;
            text-align: center;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            position: relative;
            margin-bottom: 24px;
        }
        .cert-header {
            font-size: 2.2rem;
            font-weight: 800;
            letter-spacing: 0.3em;
            color: #8b6b15;
            margin-bottom: 20px;
            border-bottom: 2px solid #d4af37;
            display: inline-block;
            padding-bottom: 8px;
        }
        .cert-rank {
            font-size: 1.6rem;
            font-weight: 800;
            color: #205720;
            margin-bottom: 16px;
        }
        .cert-team {
            font-size: 2rem;
            font-weight: 800;
            color: #333;
            margin-bottom: 8px;
        }
        .cert-members {
            font-size: 1rem;
            color: #666;
            margin-bottom: 24px;
        }
        .cert-body {
            font-size: 1.1rem;
            line-height: 1.8;
            color: #444;
            max-width: 600px;
            margin: 0 auto 30px;
            text-align: left;
        }
        .cert-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 40px;
            padding: 0 20px;
        }
        .cert-date { font-size: 1rem; color: #555; }
        .cert-seal {
            width: 70px;
            height: 70px;
            border: 3px solid #c00;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #c00;
            font-weight: 800;
            font-size: 0.9rem;
            transform: rotate(-12deg);
        }
        @media print {
            .tabnav, .to-top, .cert-selector, .links, .page-title, .table-toolbar { display: none !important; }
            .cert-frame { border-width: 16px !important; margin: 0 !important; }
        }
    </style>
</head>
<body>
    <?php include 'nav.php'; ?>
    <div class="content_box content_box--wide">
        <div class="page-title">
            <h2>🏆 表彰状・賞状発行</h2>
            <p class="subtitle">大会結果に基づく公式表彰状の生成と印刷</p>
        </div>

        <div class="cert-selector">
            <a href="certificates.php?award=0" class="btn-outline <?php echo $selectedAward === 0 ? 'btn-primary' : ''; ?>" style="<?php echo $selectedAward === 0 ? 'color:#fff;' : ''; ?>">
                🥇 優勝
            </a>
            <a href="certificates.php?award=1" class="btn-outline <?php echo $selectedAward === 1 ? 'btn-primary' : ''; ?>" style="<?php echo $selectedAward === 1 ? 'color:#fff;' : ''; ?>">
                🥈 準優勝
            </a>
            <a href="certificates.php?award=2" class="btn-outline <?php echo $selectedAward === 2 ? 'btn-primary' : ''; ?>" style="<?php echo $selectedAward === 2 ? 'color:#fff;' : ''; ?>">
                🥉 第3位
            </a>
            <button type="button" class="btn-icon" onclick="window.print()" style="margin-left: 10px;">
                🖨️ 賞状を印刷する
            </button>
        </div>

        <div class="cert-frame">
            <div class="cert-header">賞 状</div>
            <div class="cert-rank"><?php echo e($awardData['badge']); ?></div>
            <div class="cert-team"><?php echo e($targetTeam['name']); ?> 殿</div>
            <div class="cert-members">選手: <?php echo e($targetTeam['members']); ?></div>
            <div class="cert-body">
                <?php echo e($awardData['desc']); ?>
            </div>
            <div class="cert-footer">
                <div class="cert-date"><?php echo date('Y年 n月 j日'); ?><br><?php echo e($tournamentName); ?> 実行委員会</div>
                <div class="cert-seal">モルック<br>印</div>
            </div>
        </div>

        <div class="links">
            <a href="stats.php">詳細統計</a>
            <a href="standings.php">順位表</a>
            <a href="index.php">ダッシュボードへ</a>
        </div>
    </div>
</body>
</html>
