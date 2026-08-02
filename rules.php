<?php
/**
 * ルール（公開）ページ。モルックの道具・得点計算・勝利条件とペナルティを解説する。
 * 共有ヘッダー(style.css)と公開ページ用スタイル(site.css)を併用する。
 */
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>モルック ルール＆公式詳細解説</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="site.css">
</head>
<body>
    <?php include 'nav.php'; ?>

    <div class="site">
        <img class="site-banner" src="images/banner.png" alt="モルックを楽しむ人々">
        <h1 class="page-title">【 ルール＆公式詳細解説 】</h1>

        <!-- 01. 道具・配置・投擲フォーム -->
        <section class="section">
            <div class="sec-head">01. 使う道具、配置、そして投擲フォーム</div>
            <div class="grid">
                <div>
                    <p class="lead">モルックは非常にシンプルなスポーツですが、公式大会では厳密な用具サイズと投擲ラインが定められています。</p>

                    <div class="equip">
                        <div class="card">
                            <span class="tag">投げる棒</span>
                            <div class="name">モルック</div>
                            <div class="desc">長さ約 22.5cm、直径 5.9cm の木製の棒。</div>
                        </div>
                        <div class="card">
                            <span class="tag">的（ピン）</span>
                            <div class="name">スキットル</div>
                            <div class="desc">1〜12の数字が記載された12本のピン。高さ約15cm。</div>
                        </div>
                        <div class="card">
                            <span class="tag">投擲エリア</span>
                            <div class="name">3.5m</div>
                            <div class="desc">モルッカーリ（投擲線）からスキットルまで <b>3.5m (±0.1m)</b> 離します。</div>
                        </div>
                    </div>

                    <div class="sub-head">投擲の基本と応用フォーム</div>
                    <p class="lead" style="margin-bottom:16px;">投げる際の持ち方に決まりはありませんが、<b class="accent">下手投げ（下から投げる）</b>が基本です。</p>
                    <ul class="forms">
                        <li><b>基本フォーム：</b>軌道は緩やかな放物線状。確実な得点を狙う際に最適。</li>
                        <li><b>ラハティ投げ：</b>重心を落とし、スキットル手前から転がすイメージ。ピンを遠くに飛ばしたい時に有効。</li>
                        <li><b>裏投げ / 縦投げ：</b>逆手でバックスピンをかけたり、縦に持って1本だけを狙い撃ちする高度な技術。</li>
                    </ul>
                </div>

                <div>
                    <div class="media">
                        <div class="video"><div class="play"></div></div>
                    </div>
                    <div class="media">
                        <img src="images/image-2.png" alt="道具と配置">
                        <div class="cap">※高得点の11番、12番が中央に守られるように密集して配置します。</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 02. 得点計算と立て直し -->
        <section class="section">
            <div class="sec-head">02. 得点計算と「立て直し」のルール</div>
            <div class="grid">
                <div>
                    <p class="lead">このゲームの戦略性は、<b class="accent">1本倒せば「数字」</b>、<b class="accent">複数本倒せば「本数」</b>という独自の採点システムにあります。</p>

                    <div class="score-rule">
                        <p>・<b>1本だけ倒した場合：</b>倒れたスキットルに書かれている数字がそのまま得点。</p>
                        <p>・<b>複数本倒した場合：</b>書かれている数字に関係なく、倒れた「本数」が得点。</p>
                    </div>

                    <div class="sub-head">エッジケース：「倒れた」の厳密な定義</div>

                    <div class="edge">
                        <div class="box no">
                            <div class="t"><img src="images/no-count.png" alt="">ノーカウント (0点)</div>
                            <p>他のスキットルやモルックの上に重なって浮いている（完全に地面に接地していない）場合は、倒れたとみなされません。</p>
                        </div>
                        <div class="box ok">
                            <div class="t"><img src="images/mark.png" alt="">得点としてカウント</div>
                            <p>地面に完全に平たく倒れ伏しているスキットルのみを、得点計算の対象とします。</p>
                        </div>
                    </div>

                    <div class="tip">
                        <div class="t"><img src="images/mark1.png" alt="">スキットルの正しい立て直し方</div>
                        <p>倒れたスキットルは、底部が地面に接しているその場所（元の位置ではない）で立て直します。立てる際は、数字の面を投擲エリア（モルッカーリ）の方向に向けます。先端がプレイヤー側に倒れた場合は、接地点を起点にして回転させながら立てます。</p>
                    </div>
                </div>

                <div>
                    <div class="media">
                        <div class="video"><div class="play"></div><span>[ 動画：単発狙い vs 複数本倒しのスコア判定 ]</span></div>
                    </div>
                    <div class="media">
                        <img src="images/image-3.png" alt="立て直しの様子">
                        <div class="cap">※寄りかかったピン（ノーカウント）と、正しいその場での「立て直し」の動作を確認してください。徐々にピンが広がる様子に注目してください。</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 03. 勝利条件とペナルティ -->
        <section class="section">
            <div class="sec-head">03. 勝利条件と３大ペナルティ</div>
            <div class="grid">
                <div>
                    <p class="lead">勝利への条件はただ一つ、チームの合計得点が<b class="accent">「ぴったり50点」</b>になることです。しかし、焦りは以下の厳しいペナルティを招きます。</p>

                    <div class="penalty">
                        <div class="t"><img src="images/mark1.png" alt="">1. バースト（50点オーバー）</div>
                        <p>得点を加算した結果、50点を超えてしまった場合、ペナルティとして一気に <b>25点</b> に減点され、そこからゲームを継続しなければなりません。</p>
                    </div>

                    <div class="penalty">
                        <div class="t"><img src="images/mark1.png" alt="">2. ３回連続ミス（失格）</div>
                        <p>スキットルを1本も倒せない（0点）状態が 3回連続で続いたチームは、そのセットにおいて<b>ただちに失格（得点0点）</b>となります。</p>
                    </div>

                    <div class="penalty">
                        <div class="t"><img src="images/mark1.png" alt="">3. 投擲時のファウル（ラインクロス等）</div>
                        <p>投擲エリアに入ってから退場するまでの間に、モルッカーリ（投擲線）を動かしたり、足で踏み越えたり、サイドラインに触れたりすると<b>ファウル（0点）</b>となります。また、自分の順番ではない時に投げた場合も 0点となり、次の投擲資格を失います。</p>
                    </div>
                </div>

                <div>
                    <div class="media">
                        <div class="video"><div class="play"></div><span>[ 動画：51点バーストの絶望＆連続ミス失格 ]</span></div>
                    </div>
                    <div class="media">
                        <img src="images/image-1.png" alt="ペナルティの概念図">
                        <div class="cap">※ファウルを避けるため、投擲後はそのまま後ろに一歩下がってエリアから退場することが推奨されています。</div>
                    </div>
                </div>
            </div>
        </section>

        <footer class="site-footer">
            <div class="footer-container">
                <p>© 2026 MÖLKKY. Copyright.</p>
            </div>
        </footer>
    </div>
</body>
</html>
