<?php
/**
 * Panel de gestión de repuestos, precios y familias.
 *
 * @var list<\GestioBio\Models\ItemCatalog> $items
 * @var list<\GestioBio\Models\EquipmentFamily> $families
 * @var array{items: int, active_items: int, families: int, tickets: int} $stats
 */
use GestioBio\Services\Formatting;

$familiesById = [];
foreach ($families as $family) {
    $familiesById[$family->id] = $family->name;
}
?>
<div class="page-heading">
  <div>
    <h2>Gestión de repuestos y precios</h2>
    <p>Catálogo dinámico con precios referenciales en USD y compatibilidad por familia.</p>
  </div>
  <div class="page-actions">
    <a class="btn btn-primary" href="<?= e(url('/gestion/articulos/nuevo')) ?>">Nuevo artículo</a>
    <a class="btn btn-ghost" href="<?= e(url('/gestion/familias/nueva')) ?>">Nueva familia</a>
    <a class="btn btn-ghost" href="<?= e(url('/historial')) ?>">Historial</a>
  </div>
</div>

<div class="stats-grid">
  <div class="stat-card"><span><?= (int) $stats['items'] ?></span><p>Artículos</p></div>
  <div class="stat-card"><span><?= (int) $stats['active_items'] ?></span><p>Habilitados</p></div>
  <div class="stat-card"><span><?= (int) $stats['families'] ?></span><p>Familias</p></div>
  <div class="stat-card"><span><?= (int) $stats['tickets'] ?></span><p>Diagnósticos</p></div>
</div>

<section class="card table-card">
  <h3>Catálogo de repuestos y servicios</h3>
  <table class="data-table">
    <thead>
      <tr>
        <th>Artículo</th>
        <th>Precio (USD)</th>
        <th>Partida base</th>
        <th>Estado</th>
        <th>Familias compatibles</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $item): ?>
        <tr class="<?= $item->isActive ? '' : 'row-muted' ?>">
          <td><?= e($item->name) ?></td>
          <td>
            <form
              method="post"
              action="<?= e(url('/gestion/articulos/' . $item->id . '/precio')) ?>"
              class="inline-form"
            >
              <?= \GestioBio\Support\Csrf::field() ?>
              <input
                type="number"
                step="0.01"
                min="0"
                name="price"
                value="<?= e(Formatting::decimal($item->priceCents)) ?>"
                class="form-input input-price"
                aria-label="Precio de <?= e($item->name) ?>"
              >
              <button type="submit" class="btn btn-ghost btn-sm">Guardar</button>
            </form>
          </td>
          <td>
            <?php if ($item->isMandatoryBase): ?>
              <span class="badge badge-info">Obligatoria</span>
            <?php else: ?>
              —
            <?php endif; ?>
          </td>
          <td>
            <form method="post" action="<?= e(url('/gestion/articulos/' . $item->id . '/alternar')) ?>">
              <?= \GestioBio\Support\Csrf::field() ?>
              <button
                type="submit"
                class="badge <?= $item->isActive ? 'badge-success' : 'badge-danger' ?>"
              ><?= $item->isActive ? 'Habilitado' : 'Deshabilitado' ?></button>
            </form>
          </td>
          <td class="cell-wrap">
            <?php $names = []; ?>
            <?php foreach ($item->familyIds as $familyId): ?>
              <?php if (isset($familiesById[$familyId])): ?>
                <?php $names[] = $familiesById[$familyId]; ?>
              <?php endif; ?>
            <?php endforeach; ?>
            <?php if ($names === []): ?>
              —
            <?php else: ?>
              <?php foreach ($names as $name): ?>
                <span class="chip"><?= e($name) ?></span>
              <?php endforeach; ?>
            <?php endif; ?>
          </td>
          <td>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('/gestion/articulos/' . $item->id)) ?>">
              Editar
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($items === []): ?>
        <tr><td colspan="6" class="empty-state">El catálogo está vacío.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</section>

<section class="card table-card">
  <h3>Familias de equipos</h3>
  <table class="data-table">
    <thead>
      <tr>
        <th>Familia</th>
        <th>Prefijo</th>
        <th>Cotización</th>
        <th>Canal administrativo</th>
        <th>Estado</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($families as $family): ?>
        <tr>
          <td><?= e($family->name) ?></td>
          <td><code><?= e($family->prefix) ?></code></td>
          <td><?= $family->allowsQuotes ? 'Sí' : 'No' ?></td>
          <td><?= e($family->supportChannel !== '' ? $family->supportChannel : '—') ?></td>
          <td>
            <?php if ($family->isActive): ?>
              <span class="badge badge-success">Activa</span>
            <?php else: ?>
              <span class="badge badge-danger">Inactiva</span>
            <?php endif; ?>
          </td>
          <td>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('/gestion/familias/' . $family->id)) ?>">
              Editar
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($families === []): ?>
        <tr><td colspan="6" class="empty-state">No hay familias registradas.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</section>
