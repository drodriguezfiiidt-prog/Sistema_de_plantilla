<?php
/**
 * Layout compartido de GestioBioV3 (equivalente a base.html).
 *
 * @var string $content
 * @var array<int, array{type: string, message: string}> $flashes
 * @var \GestioBio\Models\User|null $current_user
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($page_title ?? ($brand_name . ' · ' . $brand_subtitle)) ?></title>
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
  <header class="app-header">
    <div class="brand">
      <span class="brand-mark">GB</span>
      <div class="brand-text">
        <h1><?= e($brand_name) ?></h1>
        <p><?= e($brand_subtitle) ?> · <?= e($company) ?></p>
      </div>
    </div>
    <nav class="app-nav">
      <a href="<?= e(url('/')) ?>">Generador</a>
      <a href="<?= e(url('/historial')) ?>">Historial</a>
      <a href="<?= e(url('/gestion')) ?>">Gestión</a>
      <?php if (!empty($current_user)): ?>
        <form method="post" action="<?= e(url('/logout')) ?>" class="nav-form">
          <?= \GestioBio\Support\Csrf::field() ?>
          <button type="submit" class="nav-link">Salir (<?= e($current_user->username) ?>)</button>
        </form>
      <?php else: ?>
        <a href="<?= e(url('/login')) ?>">Iniciar sesión</a>
      <?php endif; ?>
    </nav>
  </header>

  <?php if (!empty($flashes)): ?>
    <div class="flash-stack">
      <?php foreach ($flashes as $flash): ?>
        <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <main class="app-main">
    <?= $content ?>
  </main>

  <footer class="app-footer">
    © 2026 <?= e($brand_name) ?> by Rafael Escalona
  </footer>

  <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
