<?php
/**
 * Inicio de sesión del panel de gestión.
 *
 * @var string|null $error
 * @var string $username
 * @var string $next
 */
?>
<div class="page-heading">
  <div>
    <h2>Iniciar sesión</h2>
    <p>Acceso restringido al personal autorizado de <?= e($company) ?>.</p>
  </div>
  <a class="btn btn-ghost" href="<?= e(url('/')) ?>">Volver al generador</a>
</div>

<section class="card form-card login-card">
  <?php if (!empty($error)): ?>
    <div class="form-alert"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post" action="<?= e(url('/login')) ?>">
    <?= \GestioBio\Support\Csrf::field() ?>
    <input type="hidden" name="next" value="<?= e($next) ?>">

    <div class="field">
      <label for="id_username">Usuario</label>
      <input
        type="text"
        name="username"
        id="id_username"
        class="form-input"
        value="<?= e($username) ?>"
        autocomplete="username"
        required
        autofocus
      >
    </div>

    <div class="field">
      <label for="id_password">Contraseña</label>
      <input
        type="password"
        name="password"
        id="id_password"
        class="form-input"
        autocomplete="current-password"
        required
      >
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Entrar</button>
      <a class="btn btn-ghost" href="<?= e(url('/')) ?>">Cancelar</a>
    </div>
  </form>

  <p class="preview-hint">
    Usuarios de demostración: <code>admin</code> / <code>GestioBio2026*</code>
    (staff) y <code>soporte</code> / <code>Soporte2026*</code> (sin acceso a gestión).
  </p>
</section>
