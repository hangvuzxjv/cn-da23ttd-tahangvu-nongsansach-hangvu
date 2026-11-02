<?php
$currentPage = (int) $page;
$pageCount = (int) $pages;
$startPage = max(1, $currentPage - 2);
$endPage = min($pageCount, $currentPage + 2);
?>
<?php if ($pageCount > 1): ?>
    <nav class="mt-3" aria-label="Phân trang <?= e($paginationLabel) ?>">
        <ul class="pagination flex-wrap">
            <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                <?php if ($currentPage <= 1): ?><span class="page-link">Trước</span><?php else: ?>
                    <a class="page-link" href="<?= e(url($paginationUrl . '?' . http_build_query(array_merge($paginationQuery, ['page' => $currentPage - 1])))) ?>">Trước</a>
                <?php endif; ?>
            </li>
            <?php for ($number = $startPage; $number <= $endPage; $number++): ?>
                <li class="page-item <?= $number === $currentPage ? 'active' : '' ?>">
                    <?php if ($number === $currentPage): ?><span class="page-link" aria-current="page"><?= $number ?></span><?php else: ?>
                        <a class="page-link" href="<?= e(url($paginationUrl . '?' . http_build_query(array_merge($paginationQuery, ['page' => $number])))) ?>"><?= $number ?></a>
                    <?php endif; ?>
                </li>
            <?php endfor; ?>
            <li class="page-item <?= $currentPage >= $pageCount ? 'disabled' : '' ?>">
                <?php if ($currentPage >= $pageCount): ?><span class="page-link">Sau</span><?php else: ?>
                    <a class="page-link" href="<?= e(url($paginationUrl . '?' . http_build_query(array_merge($paginationQuery, ['page' => $currentPage + 1])))) ?>">Sau</a>
                <?php endif; ?>
            </li>
        </ul>
    </nav>
<?php endif; ?>
