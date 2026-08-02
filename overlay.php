<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Molkky Scoreboard Overlay</title>
<!--
    OBS 用スコアボードオーバーレイ。背景を透明にし、映像に重ねて合成する。
    scoreboard_data.php を定期取得して得点・投げる人を表示する。
-->
<style>
    /* 背景を透明にし、OBS が映像の上に重ねられるようにする。 */
    :root{
        --accent: #6d8bff;
        --accent-2: #a06bff;
        --win: #ffcf3f;
        --danger: #ff5d5d;
        --panel: rgba(17, 22, 38, 0.86);
        --panel-line: rgba(255, 255, 255, 0.10);
        --text: #ffffff;
        --muted: rgba(255, 255, 255, 0.62);
    }
    *{ box-sizing: border-box; margin: 0; padding: 0; }
    html, body{ width: 100%; height: 100%; background: transparent; overflow: hidden; }
    body{
        font-family: "Segoe UI", system-ui, -apple-system, sans-serif;
        color: var(--text);
        display: flex; justify-content: center; align-items: flex-start;
        padding: 40px 16px 0;
        background-color: #00b140;
    }

    /* 表示する試合が無いときはボード全体をフェードアウトさせる。 */
    .board{
        display: flex; flex-direction: column; align-items: center;
        max-width: 100%;
        opacity: 0; transform: translateY(-10px);
        transition: opacity 300ms ease, transform 300ms ease;
    }
    .board.is-visible{ opacity: 1; transform: translateY(0); }

    /* 内容に合わせた幅にし、余白や見切れが出ないようにする。 */
    .scoreboard{
        display: inline-flex; align-items: stretch;
        max-width: 100%;
        background: var(--panel);
        border: 1px solid var(--panel-line);
        border-radius: 18px;
        overflow: hidden;
    }

    .team{
        display: flex; align-items: center; gap: 18px;
        padding: 16px 24px; min-width: 0;
        transition: background 250ms ease;
    }
    .team--left{ justify-content: flex-end; }
    .team--right{ justify-content: flex-start; }

    .team__info{ display: flex; flex-direction: column; gap: 6px; min-width: 0; }
    .team--left .team__info{ align-items: flex-end; text-align: right; }
    .team--right .team__info{ align-items: flex-start; text-align: left; }

    .team__name{
        font-size: clamp(1.05rem, 2.3vw, 1.5rem); font-weight: 800;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        max-width: min(34vw, 300px);
    }
    .team__score{
        font-size: clamp(2.6rem, 6.5vw, 4.4rem); font-weight: 800; line-height: 0.9;
        font-variant-numeric: tabular-nums; letter-spacing: -0.02em;
    }

    .misses{ display: flex; gap: 7px; }
    .team--left .misses{ flex-direction: row-reverse; }
    .miss-dot{
        width: 12px; height: 12px; border-radius: 50%;
        background: rgba(255, 255, 255, 0.18);
        transition: background 200ms ease;
    }
    .miss-dot.is-on{
        background: var(--danger);
    }

    /* 投球中のチーム: 背景の色付け + チーム名を強調色にする。 */
    .team.is-active{
        background: linear-gradient(180deg,
            rgba(109, 139, 255, 0.18), rgba(109, 139, 255, 0.03));
    }
    .team.is-active .team__name{ color: var(--accent); }
    .team.is-winner .team__name{ color: var(--win); }
    .team.is-winner .team__score{
        color: var(--win); text-shadow: 0 0 18px rgba(255, 207, 63, 0.5);
    }

    .center{
        display: flex; flex-direction: column; align-items: center;
        justify-content: center; gap: 4px;
        padding: 0 20px; flex: none;
        background: linear-gradient(180deg, var(--accent), var(--accent-2));
    }
    .center__vs{ font-size: 1.3rem; font-weight: 800; letter-spacing: 0.14em; }
    .center__target{ font-size: 0.65rem; letter-spacing: 0.16em; opacity: 0.85; }

    /* 「投げる人」を表示する帯。 */
    .now{
        margin-top: -1px; max-width: 100%;
        display: flex; align-items: center; gap: 12px;
        padding: 9px 24px;
        background: linear-gradient(180deg, var(--accent), var(--accent-2));
        border-radius: 0 0 16px 16px;
    }
    .now.is-hidden{ display: none; }
    .now__label{
        font-size: 0.7rem; font-weight: 800; letter-spacing: 0.18em;
        text-transform: uppercase; opacity: 0.9; white-space: nowrap;
        border-right: 1px solid rgba(255,255,255,0.4); padding-right: 12px;
    }
    .now__name{
        font-size: 1.3rem; font-weight: 800;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        max-width: min(60vw, 520px);
    }

    .winner-tag{
        font-size: 0.68rem; font-weight: 800; letter-spacing: 0.14em;
        color: var(--win); text-transform: uppercase;
        opacity: 0; height: 0; overflow: hidden; transition: opacity 200ms ease;
    }
    .team.is-winner .winner-tag{ opacity: 1; height: auto; }
</style>
</head>
<body>
    <div class="board" id="board">
        <div class="scoreboard">
            <div class="team team--left" id="team1">
                <div class="team__info">
                    <div class="winner-tag">🏆 Winner</div>
                    <div class="team__name" id="name1">Team 1</div>
                    <div class="misses" id="miss1"></div>
                </div>
                <div class="team__score" id="score1">0</div>
            </div>

            <div class="center">
                <div class="center__vs">VS</div>
                <div class="center__target" id="target">TO 50</div>
            </div>

            <div class="team team--right" id="team2">
                <div class="team__score" id="score2">0</div>
                <div class="team__info">
                    <div class="winner-tag">🏆 Winner</div>
                    <div class="team__name" id="name2">Team 2</div>
                    <div class="misses" id="miss2"></div>
                </div>
            </div>
        </div>

        <div class="now is-hidden" id="now">
            <span class="now__label">投げる人</span>
            <span class="now__name" id="player">—</span>
        </div>
    </div>

<script>
// scoreboard_data.php を1秒ごとに取得し、変化があった時だけ描画を更新する。
(function () {
    var POLL_MS = 1000;
    var board = document.getElementById('board');
    var els = {
        target: document.getElementById('target'),
        now: document.getElementById('now'),
        player: document.getElementById('player'),
        t1: document.getElementById('team1'), t2: document.getElementById('team2'),
        n1: document.getElementById('name1'), n2: document.getElementById('name2'),
        s1: document.getElementById('score1'), s2: document.getElementById('score2'),
        m1: document.getElementById('miss1'), m2: document.getElementById('miss2')
    };
    var last = ''; // 直前の応答を保持し、無駄な再描画（ちらつき）を防ぐ

    function setText(el, val) { if (el.textContent !== String(val)) el.textContent = val; }

    function renderMisses(container, misses, maxMisses) {
        if (container.childElementCount !== maxMisses) {
            container.innerHTML = '';
            for (var i = 0; i < maxMisses; i++) {
                container.appendChild(document.createElement('span')).className = 'miss-dot';
            }
        }
        var dots = container.children;
        for (var j = 0; j < dots.length; j++) {
            dots[j].classList.toggle('is-on', j < misses);
        }
    }

    function renderTeam(root, nameEl, scoreEl, missEl, data, maxMisses) {
        setText(nameEl, data.name);
        setText(scoreEl, data.score);
        renderMisses(missEl, data.misses, maxMisses);
        root.classList.toggle('is-active', !!data.active);
        root.classList.toggle('is-winner', !!data.winner);
    }

    function render(d) {
        // 試合が無いときはボード全体を隠す。
        if (d.state === 'idle' || !d.team1) {
            board.classList.remove('is-visible');
            return;
        }
        board.classList.add('is-visible');
        setText(els.target, 'TO ' + d.target);
        renderTeam(els.t1, els.n1, els.s1, els.m1, d.team1, d.maxMisses);
        renderTeam(els.t2, els.n2, els.s2, els.m2, d.team2, d.maxMisses);

        if (d.state === 'live' && d.currentPlayer) {
            setText(els.player, d.currentPlayer);
            els.now.classList.remove('is-hidden');
        } else {
            els.now.classList.add('is-hidden');
        }
    }

    function poll() {
        fetch('scoreboard_data.php', { cache: 'no-store' })
            .then(function (r) { return r.text(); })
            .then(function (txt) {
                if (txt === last) return;      // 変化なしなら何もしない
                last = txt;
                render(JSON.parse(txt));
            })
            .catch(function () { /* 一時的なエラー時は直前の表示を維持する */ });
    }

    // ?demo=1 でサンプルデータを表示する（OBS での配置調整用）。
    if (/[?&]demo=1/.test(location.search)) {
        render({
            state: 'live', target: 50, maxMisses: 3,
            currentPlayer: 'アウン　ジン　ミョー',
            team1: { name: 'Team 1', score: 34, misses: 1, active: true,  winner: false },
            team2: { name: 'Team 2', score: 28, misses: 0, active: false, winner: false }
        });
        return;
    }

    poll();
    setInterval(poll, POLL_MS);
})();
</script>
</body>
</html>
