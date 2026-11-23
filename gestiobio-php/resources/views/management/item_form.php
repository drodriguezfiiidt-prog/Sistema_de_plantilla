<?php
/**
 * Alta y edición de artículos del catálogo.
 *
 * @var \GestioBio\Models\ItemCatalog|null $item
 * @var list<\GestioBio\Models\EquipmentFamily> $families
 * @var array<string, mixed> $values
 * @var array<string, list<string>> $errors
 */
$familyIds = array_map('intval', (array) $values['family_ids']);
?>
<div class="page-heading">
  <div>
    <h2>
      <?php if ($item !== null): ?>
        Editar «<?= e($item->name) ?>»
      <?php else: ?>
        Nuevo artículo del catálogo
      <?php endif; ?>
    </h2>
    <p>Defina el precio referencial y las familias compatibles con el repuesto o servicio.</p>
  </div>
  <a class="btn btn-ghost" href="<?= e(url('/gestion')) ?>">Volver al panel</a>
</div>

<form method="post" class="card form-card" action="">
  <?= \GestioBio\Support\Csrf::field() ?>

  <div class="field">
    <label for="id_name">Nombre del artículo</label>
    <input
      type="text"
      name="name"
      id="id_name"
      class="form-input"
      maxlength="160"
      value="<?= e((string) $values['name']) ?>"
      required
    >
    <?= render_partial('partials/errors', ['messages' => $errors['name'] ?? []]) ?>
  </div>

  <div class="field">
    <label for="id_price">Precio referencial en USD</label>
    <input
      type="text"
      name="price"
      id="id_price"
      class="form-input"
      inputmode="decimal"
      placeholder="80 o 12,50"
      value="<?= e((string) $values['price']) ?>"
      required
    >
    <?= render_partial('partials/errors', ['messages' => $errors['price'] ?? []]) ?>
  </div>

  <div class="field">
    <label class="form-check-label" for="id_is_mandatory_base">
      <input
        type="checkbox"
        name="is_mandatory_base"
        id="id_is_mandatory_base"
        value="1"
        class="form-check"
        <?= $values['is_mandatory_base'] ? 'checked' : '' ?>
      >
      Partida base obligatoria
    </label>
    <small class="help">
      Las partidas base se agregan automáticamente a la tabla tarifaria y no se
      muestran como opción en el formulario.
    </small>
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
      Habilitado en el formulario
    </label>
  </div>

  <fieldset class="field">
    <legend>Familias compatibles</legend>
    <div class="checkbox-grid">
      <?php foreach ($families as $index => $family): ?>
        <div>
          <label for="id_family_<?= (int) $index ?>">
            <input
              type="checkbox"
              name="family_ids[]"
              value="<?= (int) $family->id ?>"
              id="id_family_<?= (int) $index ?>"
              <?= in_array($family->id, $familyIds, true) ? 'checked' : '' ?>
            >
            <?= e($family->name) ?>
          </label>
        </div>
      <?php endforeach; ?>
      <?php if ($families === []): ?>
        <p class="empty-state">No hay familias registradas todavía.</p>
      <?php endif; ?>
    </div>
    <small class="help">
      Familias para las cuales está disponible este artículo. Sin familias
      asignadas no aparece en el formulario.
    </small>
  </fieldset>

  <div class="form-actions">
    <button class="btn btn-primary" type="submit">Guardar</button>
    <a class="btn btn-ghost" href="<?= e(url('/gestion')) ?>">Cancelar</a>
  </div>
</form>
