<?php
/**
 * Checkboxes de fallas y repuestos compatibles con la familia detectada.
 *
 * @var \GestioBio\Services\DiagnosticForm $form
 */
$items = $form->itemOptions;
$selected = $form->selectedItemIds;
?>
<?php if ($items === []): ?>
  <p class="empty-state">No hay repuestos compatibles habilitados para esta familia.</p>
<?php else: ?>
  <?php foreach ($items as $index => $item): ?>
    <div>
      <label for="id_items_<?= (int) $index ?>">
        <input
          type="checkbox"
          name="items[]"
          value="<?= (int) $item->id ?>"
          id="id_items_<?= (int) $index ?>"
          <?= in_array($item->id, $selected, true) ? 'checked' : '' ?>
        >
        <?= e($item->name) ?> ($<?= e(\GestioBio\Services\Formatting::priceCell($item->priceCents)) ?>)
      </label>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
