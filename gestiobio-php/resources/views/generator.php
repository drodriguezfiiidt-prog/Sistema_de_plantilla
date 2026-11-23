<?php
/**
 * Asistente de diagnóstico técnico.
 *
 * @var \GestioBio\Services\DiagnosticForm $form
 * @var \GestioBio\Services\GenerationResult|null $result
 * @var \GestioBio\Models\DiagnosticTicket|null $ticket
 * @var \GestioBio\Models\EquipmentFamily|null $family
 */
?>
<div class="page-heading">
  <div>
    <h2>Asistente de diagnóstico técnico</h2>
    <p>Parametrice el ticket, el equipo y las fallas para generar el correo institucional y su Word.</p>
  </div>
  <a class="btn btn-ghost" href="<?= e(url('/historial')) ?>">Ver historial</a>
</div>

<div class="layout-two-columns">
  <section class="card form-card">
    <h3>Datos del ticket</h3>
    <form
      method="post"
      id="diagnostic-form"
      data-preview-url="<?= e(url('/vista-previa')) ?>"
      data-context-url="<?= e(url('/api/contexto')) ?>"
    >
      <?= \GestioBio\Support\Csrf::field() ?>
      <?php if ($form->nonFieldErrors() !== []): ?>
        <div class="form-alert">
          <?= render_partial('partials/errors', ['messages' => $form->nonFieldErrors()]) ?>
        </div>
      <?php endif; ?>

      <div class="field">
        <label for="id_ticket_number">Número de ticket</label>
        <input
          type="text"
          name="ticket_number"
          id="id_ticket_number"
          class="form-input"
          maxlength="60"
          placeholder="35212348"
          autocomplete="off"
          data-ticket-input="true"
          value="<?= e($form->value('ticket_number')) ?>"
        >
        <?= render_partial('partials/errors', ['messages' => $form->errorsFor('ticket_number')]) ?>
      </div>

      <div class="field">
        <label for="id_serial_number">Serial del equipo</label>
        <input
          type="text"
          name="serial_number"
          id="id_serial_number"
          class="form-input"
          maxlength="80"
          placeholder="V10-000000"
          autocomplete="off"
          data-serial-input="true"
          value="<?= e($form->value('serial_number')) ?>"
        >
        <small class="help">El prefijo del serial autodetecta la familia del equipo.</small>
        <?= render_partial('partials/errors', ['messages' => $form->errorsFor('serial_number')]) ?>
      </div>

      <div class="field">
        <label for="id_family">Familia detectada</label>
        <select name="family" id="id_family" class="form-input">
          <option value="">Autodetectar por serial</option>
          <?php foreach ($form->familyOptions as $option): ?>
            <option
              value="<?= (int) $option->id ?>"
              <?= $form->value('family') === (string) $option->id ? 'selected' : '' ?>
            ><?= e($option->name) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="family-chip" id="family-chip">
          <?php if ($family !== null): ?>
            <strong><?= e($family->name) ?></strong> · prefijo <?= e($family->prefix) ?>
            <?php if (!$family->allowsQuotes): ?>· sin tabla de cotización<?php endif; ?>
          <?php else: ?>
            Sin familia detectada
          <?php endif; ?>
        </div>
        <?= render_partial('partials/errors', ['messages' => $form->errorsFor('family')]) ?>
      </div>

      <fieldset class="field">
        <legend>Fallas y repuestos diagnosticados</legend>
        <div id="id_items" class="checkbox-grid">
          <?= render_partial('partials/items', ['form' => $form]) ?>
        </div>
        <small class="help">
          Solo se muestran repuestos activos y compatibles con la familia detectada.
        </small>
        <?= render_partial('partials/errors', ['messages' => $form->errorsFor('items')]) ?>
      </fieldset>

      <div class="field">
        <label for="id_warranty_status">Estado de garantía</label>
        <select name="warranty_status" id="id_warranty_status" class="form-input">
          <?php foreach (\GestioBio\Services\DiagnosticForm::WARRANTY_CHOICES as $choice): ?>
            <option value="<?= e($choice) ?>" <?= $form->value('warranty_status') === $choice ? 'selected' : '' ?>>
              <?= e($choice) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?= render_partial('partials/errors', ['messages' => $form->errorsFor('warranty_status')]) ?>
      </div>

      <div class="field">
        <label for="id_technical_report">Informe técnico</label>
        <textarea
          name="technical_report"
          id="id_technical_report"
          class="form-input"
          rows="5"
          placeholder="Detalle libre del diagnóstico: pruebas realizadas, componentes afectados y observaciones."
        ><?= e($form->value('technical_report')) ?></textarea>
        <small class="help">Se publica con tratamiento de usted y glosario institucional.</small>
        <?= render_partial('partials/errors', ['messages' => $form->errorsFor('technical_report')]) ?>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Generar diagnóstico</button>
        <button type="reset" class="btn btn-ghost">Limpiar</button>
      </div>
    </form>
  </section>

  <section class="card preview-card">
    <div class="preview-header">
      <h3>Vista previa del documento</h3>
      <span class="preview-live" id="preview-status">Actualización en vivo</span>
    </div>
    <?php if ($ticket !== null): ?>
      <p class="saved-note">
        Evaluación guardada.
        <a href="<?= e(url('/historial/' . $ticket->id)) ?>">Ver registro #<?= e($ticket->ticketNumber) ?></a>
      </p>
    <?php endif; ?>
    <div id="preview-panel" class="preview-body" aria-live="polite">
      <?= render_partial('partials/preview', ['result' => $result, 'form' => $form]) ?>
    </div>
  </section>
</div>
