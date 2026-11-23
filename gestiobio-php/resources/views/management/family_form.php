<?php
/**
 * Alta y edición de familias de equipos.
 *
 * @var \GestioBio\Models\EquipmentFamily|null $family
 * @var array<string, mixed> $values
 * @var array<string, list<string>> $errors
 */
?>
<div class="page-heading">
  <div>
    <h2>
      <?php if ($family !== null): ?>
        Editar «<?= e($family->name) ?>»
      <?php else: ?>
        Nueva familia de equipos
      <?php endif; ?>
    </h2>
    <p>El prefijo permite autodetectar la familia a partir del serial del equipo.</p>
  </div>
  <a class="btn btn-ghost" href="<?= e(url('/gestion')) ?>">Volver al panel</a>
</div>

<form method="post" class="card form-card" action="">
  <?= \GestioBio\Support\Csrf::field() ?>

  <div class="field">
    <label for="id_name">Nombre</label>
    <input
      type="text"
      name="name"
      id="id_name"
      class="form-input"
      maxlength="80"
      value="<?= e((string) $values['name']) ?>"
      required
    >
    <?= render_partial('partials/errors', ['messages' => $errors['name'] ?? []]) ?>
  </div>

  <div class="field">
    <label for="id_prefix">Prefijo de serial</label>
    <input
      type="text"
      name="prefix"
      id="id_prefix"
      class="form-input"
      maxlength="20"
      value="<?= e((string) $values['prefix']) ?>"
      required
    >
    <small class="help">Prefijo que identifica la familia. Ejemplo: V10, FP-, G62.</small>
    <?= render_partial('partials/errors', ['messages' => $errors['prefix'] ?? []]) ?>
  </div>

  <div class="field">
    <label class="form-check-label" for="id_allows_quotes">
      <input
        type="checkbox"
        name="allows_quotes"
        id="id_allows_quotes"
        value="1"
        class="form-check"
        <?= $values['allows_quotes'] ? 'checked' : '' ?>
      >
      Permite tabla de cotización
    </label>
    <small class="help">
      Si se desactiva, el documento no incluye tabla tarifaria y remite al canal
      administrativo de la familia.
    </small>
  </div>

  <div class="field">
    <label for="id_support_channel">Canal de atención</label>
    <input
      type="text"
      name="support_channel"
      id="id_support_channel"
      class="form-input"
      maxlength="160"
      value="<?= e((string) $values['support_channel']) ?>"
    >
    <small class="help">Canal exigido cuando la familia no permite cotización.</small>
    <?= render_partial('partials/errors', ['messages' => $errors['support_channel'] ?? []]) ?>
  </div>

  <div class="field">
    <label class="form-check-label" for="id_is_active">
      <input
        type="checkbox"
        name="is_active"
        id="id_is_active"
        value="1"
        class="form-check"
        <?= $values['is_active'] ? 'checked' : '' ?>
      >
      Familia activa
    </label>
  </div>

  <div class="form-actions">
    <button class="btn btn-primary" type="submit">Guardar</button>
    <a class="btn btn-ghost" href="<?= e(url('/gestion')) ?>">Cancelar</a>
  </div>
</form>
