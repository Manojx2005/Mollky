<?php
/**
 * ホーム（公開ランディング）ページ。モルックの紹介・ニュース・制作チームを表示する。
 * 共有ヘッダー(style.css)と公開ページ用スタイル(site.css)を併用する。
 */
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mölkky — ホーム</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="site.css">
</head>
<body>
    <?php include 'nav.php'; ?>

    <div class="site">
        <img class="site-banner" src="images/ll.png" alt="日本中で大人気！ フィンランド発の木製スポーツ「モルック」">
        <section class="section features">
            <h1 class="page-title">モルックの3大魅力</h1>

            <div class="card-container">
                <div class="card green">
                    <div class="card-title">
                        <img src="images/green.png" alt="">
                        <h2>カンタン</h2>
                    </div>
                    <p>下から棒を投げるだけ！<br>年齢問わず、誰でもすぐに楽しめます。</p>
                </div>

                <div class="card yellow">
                    <div class="card-title">
                        <img src="images/yello2.png" alt="">
                        <h2>おもしろい</h2>
                    </div>
                    <p>1本ならピンの数字が点数に、<br>複数本なら倒れた本数が点数に。</p>
                </div>

                <div class="card pink">
                    <div class="card-title">
                        <img src="images/pink.png" alt="">
                        <h2>スリリング</h2>
                    </div>
                    <p>目指すはぴったり50点。<br>1点でもオーバーすると<br>25点へ強制逆戻り!</p>
                </div>
            </div>
        </section>

        <section class="section news">
            <h2 class="sec-head">月間モルックニュース＆アップデート</h2>

            <div class="news-grid">
                <div class="news-card">
                    <span class="news-date">2026.07</span>
                    <h3>サマーモルックカップ、参加チーム募集開始！</h3>
                    <p>毎年恒例の夏季大会の開催が決定。初心者チームの参加も大歓迎です。エントリーは今月末まで受付中。</p>
                </div>
                <div class="news-card">
                    <span class="news-date">2026.06</span>
                    <h3>公式ルールの一部改定について</h3>
                    <p>3回連続ミスに関する判定基準が明確化されました。詳しくは「ルール」ページをご確認ください。</p>
                </div>
                <div class="news-card">
                    <span class="news-date">2026.06</span>
                    <h3>初心者向け体験会を開催しました</h3>
                    <p>親子連れを中心に大盛況となった体験会のレポートを公開。次回開催は8月を予定しています。</p>
                </div>
                <div class="news-card">
                    <span class="news-date">2026.05</span>
                    <h3>新色スキットルセットが登場</h3>
                    <p>屋外でも見やすいカラーリングの新モデルが仲間入り。練習用セットとしてもおすすめです。</p>
                </div>
            </div>
        </section>

        <section class="section team">
            <h2 class="sec-head">プロジェクトチーム</h2>

            <?php
            /* 制作チームの一覧。[イニシャル, 氏名, 役割, CSSクラス] の順。 */
            $teamGroups = [
                '運営' => [
                    ['マ', 'マノジュさん', 'リーダー', 'leadership'],
                    ['ダ', 'ダグワさん', 'サブリーダー', 'leadership'],
                    ['星', '星さん', 'ファシリテーター', 'leadership'],
                ],
                'デザイン' => [
                    ['肖', '肖平さん', 'デザイン', 'design'],
                    ['ピ', 'ピューさん', 'デザイン', 'design'],
                    ['ス', 'スーへマンさん', 'デザイン', 'design'],
                ],
                'プログラム' => [
                    ['へ', 'へインさん', 'プログラマ', 'program'],
                    ['ピ', 'ピョーリンテさん', 'プログラマ', 'program'],
                    ['ム', 'ムンフトルさん', 'プログラマ', 'program'],
                    ['マ', 'マノジュさん', 'プログラマ', 'program'],
                    ['ク', 'クレバロさん', 'プログラマ', 'program'],
                    ['深', '深見さん', 'プログラマ', 'program'],
                ],
                '映像制作' => [
                    ['テ', 'テッモンニャンさん', '動画', 'video'],
                    ['星', '星さん', '動画', 'video'],
                    ['李', '李さん', '動画', 'video'],
                    ['バ', 'バヤルサイハンさん', '動画', 'video'],
                    ['ダ', 'ダグワさん', '動画', 'video'],
                ],
            ];
            /* HTML 出力用のエスケープ関数。 */
            function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
            foreach ($teamGroups as $groupName => $members): ?>
                <div class="team-group">
                    <h3 class="team-group-title"><?php echo h($groupName); ?></h3>
                    <div class="team-grid">
                        <?php foreach ($members as [$initial, $name, $role, $cls]): ?>
                            <div class="team-card <?php echo $cls; ?>">
                                <div class="avatar"><?php echo h($initial); ?></div>
                                <p class="member-name"><?php echo h($name); ?></p>
                                <span class="member-role"><?php echo h($role); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>

        <footer class="site-footer">
            <div class="footer-container">
                <p>© 2026 MÖLKKY. Copyright.</p>
            </div>
        </footer>
    </div>
</body>
</html>
