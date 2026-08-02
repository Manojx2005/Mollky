<?php
/**
 * 全ページ共通のヘッダー（ロゴ + タブナビ）。各ページから include する。
 * トップナビはホーム / ルール / ダッシュボードの3つ。
 */
$currentPage = basename($_SERVER['PHP_SELF']);
$navTabs = [
    'home.php'  => 'ホーム',
    'rules.php' => 'ルール',
    'index.php' => 'ダッシュボード',
];
/* 参加者選択・名簿・試合はダッシュボードの子ページ扱い。
   これらを開いている間は「ダッシュボード」タブをハイライトする。 */
$dashboardChildren = ['search.php', 'add_student.php', 'match.php'];
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
    </div>
</nav>

<?php /* トップへ戻るボタン（全ページ共通・右下固定）。 */ ?>
<button type="button" class="to-top" id="toTop" aria-label="トップへ戻る" title="トップへ戻る">↑</button>
<script>
    // 一定量スクロールしたらボタンを表示し、クリックで先頭へスムーズに戻る。
    (function () {
        var btn = document.getElementById('toTop');
        if (!btn) { return; }
        function onScroll() { btn.classList.toggle('is-visible', window.scrollY > 300); }
        btn.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    })();
</script>
