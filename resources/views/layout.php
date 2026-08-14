<?php

/**
 * Página única da aplicação (era `app.blade.php`).
 *
 * Todo valor dinâmico sai por `e()`, que escapa igual ao `{{ }}` do Blade.
 */
use App\Core\Config;
use App\Support\Assets;

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(Config::get('app.name')) ?></title>

    <?php // Ordem obrigatória do design system (dev/ESTILO.md) ?>
    <link rel="stylesheet" href="<?= e(Assets::url('css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(Assets::url('css/layout.css')) ?>">
    <link rel="stylesheet" href="<?= e(Assets::url('css/components.css')) ?>">
</head>
<body>

<?php include __DIR__.'/partials/topbar.php'; ?>
<?php include __DIR__.'/partials/tabs.php'; ?>

<main>
    <?php include __DIR__.'/screens/links.php'; ?>
    <?php include __DIR__.'/screens/invoices.php'; ?>
    <?php include __DIR__.'/screens/database.php'; ?>
</main>

<?php include __DIR__.'/partials/modal.php'; ?>

<div id="toast"></div>

<script type="module" src="<?= e(Assets::url('js/app.js')) ?>"></script>
</body>
</html>
