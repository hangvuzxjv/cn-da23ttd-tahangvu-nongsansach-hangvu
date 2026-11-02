<article class="mx-auto static-page">
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= e(url('/')) ?>">Trang chủ</a></li><li class="breadcrumb-item active" aria-current="page"><?= e($page['title']) ?></li></ol></nav>
    <h1 class="h2 mb-4"><?= e($page['title']) ?></h1>
    <div class="static-page-content"><?= $page['content'] ?></div>
</article>
