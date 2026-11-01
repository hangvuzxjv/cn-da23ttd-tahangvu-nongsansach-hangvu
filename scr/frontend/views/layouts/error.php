<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Trang thông báo lỗi của Nông Sản Sạch.">
    <meta name="theme-color" content="#176b3a">
    <title><?= (int) http_response_code() ?> - Nông Sản Sạch</title>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('/favicon.svg')) ?>">
    <link href="<?= e(asset('/css/vendor/bootstrap.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('/css/app.css')) ?>" rel="stylesheet">
</head>
<body class="error-page">
    <main class="container error-page__content">
        <?= $content ?? '' ?>
    </main>
    <footer class="error-page__footer">Nông Sản Sạch · Nông sản theo mùa, minh bạch nguồn gốc</footer>
</body>
</html>
