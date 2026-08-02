<?php
/**
 * 全ページ共通のヘッダー（ロゴ + タブナビ）。各ページから include する。
 * トップナビはホーム / ルール / 大会 / 順位表 / 統計 / ダッシュボード。
 */
$currentPage = basename($_SERVER['PHP_SELF']);
$navTabs = [
    'home.php'       => 'ホーム',
    'rules.php'      => 'ルール',
    'tournament.php' => '大会',
    'standings.php'  => '順位表',
    'stats.php'      => '詳細統計',
    'index.php'      => 'ダッシュボード',
];
/* 子ページ */
$dashboardChildren = [
    'search.php',
    'add_student.php',
    'match.php',
    'history.php',
    'create_team.php',
    'edit_team.php',
    'certificates.php'
];
?>
<nav class="tabnav" aria-label="メインナビゲーション">
    <div class="tabnav__inner">
        <a class="tabnav__brand" href="home.php" aria-label="MÖLKKY ホーム">
            <img src="images/logo.png" alt="MÖLKKY">
        </a>
        <?php foreach ($navTabs as $file => $label):
            $isActive = $currentPage === $file
                || ($file === 'index.php' && in_array($currentPage, $dashboardChildren, true));
            $classes = 'tabnav__link' . ($isActive ? ' is-active' : '');
        ?>
            <a href="<?php echo $file; ?>" class="<?php echo $classes; ?>"
               <?php echo $isActive ? 'aria-current="page"' : ''; ?>>
                <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
            </a>
        <?php endforeach; ?>

        <button type="button" class="tabnav__theme-btn" id="themeToggleBtn" title="テーマ切り替え">
            🎨 テーマ
        </button>
    </div>
</nav>

<?php /* トップへ戻るボタン（全ページ共通・右下固定）。 */ ?>
<button type="button" class="to-top" id="toTop" aria-label="トップへ戻る" title="トップへ戻る">↑</button>

<script>
    // テーマ切り替え機能 (Lawn Green, Dark, Wood)
    (function () {
        var themes = ['lawn', 'dark', 'wood'];
        var currentTheme = localStorage.getItem('molkky_theme') || 'lawn';

        function applyTheme(t) {
            document.documentElement.setAttribute('data-theme', t);
            localStorage.setItem('molkky_theme', t);
        }
        applyTheme(currentTheme);

        var btn = document.getElementById('themeToggleBtn');
        if (btn) {
            btn.addEventListener('click', function () {
                var idx = (themes.indexOf(currentTheme) + 1) % themes.length;
                currentTheme = themes[idx];
                applyTheme(currentTheme);
            });
        }

        // トップへ戻るボタン
        var toTop = document.getElementById('toTop');
        if (toTop) {
            function onScroll() { toTop.classList.toggle('is-visible', window.scrollY > 300); }
            toTop.addEventListener('click', function () {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
            window.addEventListener('scroll', onScroll, { passive: true });
            onScroll();
        }
    })();
</script>
