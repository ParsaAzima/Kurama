<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->escape($title ?? 'Kurama') ?></title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 40rem; margin: 4rem auto; padding: 0 1rem; line-height: 1.6; }
        code { background: #f2f2f2; padding: .1rem .4rem; border-radius: 4px; }
    </style>
</head>
<body>
<h1><?= $this->escape($title ?? 'Kurama') ?></h1>
<?= $this->render('partials.greeting', ['name' => $name ?? 'world']) ?>
<p>Try <code>/hello/kurama</code>, <code>/api/ping</code> or <code>/view/parsa</code>.</p>
</body>
</html>
