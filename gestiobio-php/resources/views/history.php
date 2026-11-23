<?php
/**
 * Historial de evaluaciones guardadas.
 *
 * @var list<\GestioBio\Models\DiagnosticTicket> $tickets
 * @var int $total_count
 * @var string $query
 * @var string $warranty
 * @var string $family_id
 * @var list<\GestioBio\Models\EquipmentFamily> $families
 * @var array<string, string> $warranty_choices
 */
use GestioBio\Services\Formatting;
use GestioBio\Support\Clock;

?>
<div class="page-heading">
  <div>
    <h2>Historial de evaluaciones</h2>
    <p><?= (int) $total_count ?> registro(s) guardado(s) en <?= e($brand_name) ?>.</p>
  </div>
  <a class="btn btn-primary" href="<?= e(url('/')) ?>">Nuevo diagnóstico</a>
</div>

<form class="card filter-bar" method="get" action="<?= e(url('/historial')) ?>">
  <input
    type="search"
    name="q"
    value="<?= e($query) ?>"
    placeholder="Ticket o serial"
    class="form-input"
  >
  <select name="garantia" class="form-input">
    <option value="">Todas las garantías</option>
    <?php foreach ($warranty_choices as $value => $label): ?>
      <option value="<?= e($value) ?>" <?= $value === $warranty ? 'selected' : '' ?>><?= e($label) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="familia" class="form-input">
    <option value="">Todas las familias</option>
    <?php foreach ($families as $item): ?>
      <option value="<?= (int) $item->id ?>" <?= (string) $item->id === $family_id ? 'selected' : '' ?>>
        <?= e($item->name) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-primary" type="submit">Filtrar</button>
</form>

<div class="card table-card">
  <?php if ($tickets !== []): ?>
    <table class="data-table">
      <thead>
        <tr>
          <th>Ticket</th>
          <th>Serial</th>
          <th>Familia</th>
          <th>Garantía</th>
          <th>Fallas</th>
          <th>Total</th>
          <th>Creado</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($tickets as $ticket): ?>
          <?php
          $badge = match ($ticket->warrantyStatus) {
              'Vigente' => 'badge-success',
              'Invalidada' => 'badge-danger',
              default => 'badge-warning',
          };
          $summary = $ticket->itemsSummary();
          ?>
          <tr>
            <td><a href="<?= e(url('/historial/' . $ticket->id)) ?>">#<?= e($ticket->ticketNumber) ?></a></td>
            <td><?= e($ticket->serialNumber) ?></td>
            <td><?= e($ticket->family?->name ?? '—') ?></td>
            <td><span class="badge <?= e($badge) ?>"><?= e($ticket->warrantyLabel()) ?></span></td>
            <td class="cell-wrap"><?= e($summary !== '' ? $summary : '—') ?></td>
            <td>$<?= e(Formatting::decimal($ticket->totalCents)) ?></td>
            <td><?= e(Clock::local($ticket->createdAt)) ?></td>
            <td>
              <a class="btn btn-ghost btn-sm" href="<?= e(url('/historial/' . $ticket->id . '/docx')) ?>">
                Word
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <p class="empty-state">No hay evaluaciones registradas todavía.</p>
  <?php endif; ?>
</div>
