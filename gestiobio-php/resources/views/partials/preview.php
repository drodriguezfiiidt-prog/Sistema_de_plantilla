<?php
/**
 * Vista previa en vivo del diagnóstico.
 *
 * @var \GestioBio\Services\GenerationResult|null $result
 */
use GestioBio\Services\Formatting;

?>
<?php if ($result !== null): ?>
  <div class="preview-toolbar">
    <span class="badge badge-<?= e($result->report->tone()) ?>"><?= e($result->report->label()) ?></span>
    <div class="preview-actions">
      <button type="button" class="btn btn-ghost" data-copy-preview>
        Copiar al portapapeles
      </button>
      <button
        type="button"
        class="btn btn-primary"
        data-download-docx
        data-url="<?= e(url('/descargar')) ?>"
      >
        Descargar Word (.docx)
      </button>
    </div>
  </div>

  <div class="preview-meta">
    <p><strong>Asunto:</strong> <?= e($result->document->subject) ?></p>
    <?php if ($result->document->familyName !== ''): ?>
      <p>
        <strong>Modelo:</strong> <?= e($result->document->familyName) ?>
        · <strong>Serial:</strong> <?= e($result->document->serialNumber) ?>
        · <strong>Garantía:</strong> <?= e($result->document->warrantyStatus) ?>
      </p>
    <?php endif; ?>
  </div>

  <div id="preview-document">
    <?= render_partial('partials/document', ['document' => $result->document]) ?>
  </div>

  <?php if ($result->quote->hasLines()): ?>
    <p class="preview-total">
      Total referencial: <strong>$<?= e(Formatting::decimal($result->quote->totalCents)) ?></strong>
    </p>
  <?php endif; ?>

  <?php if ($result->report->errors !== [] || $result->report->warnings !== [] || $result->report->infos !== []): ?>
    <details class="preview-report">
      <summary>Validaciones del documento (<?= count($result->report->errors) ?> errores)</summary>
      <?php if ($result->report->errors !== []): ?>
        <ul class="report-list report-errors">
          <?php foreach ($result->report->errors as $item): ?><li><?= e($item) ?></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <?php if ($result->report->warnings !== []): ?>
        <ul class="report-list report-warnings">
          <?php foreach ($result->report->warnings as $item): ?><li><?= e($item) ?></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <?php if ($result->report->infos !== []): ?>
        <ul class="report-list report-infos">
          <?php foreach ($result->report->infos as $item): ?><li><?= e($item) ?></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </details>
  <?php endif; ?>

  <textarea id="preview-text" class="visually-hidden" readonly><?= e($result->document->plainText()) ?></textarea>
<?php else: ?>
  <div class="preview-empty">
    <h3>Vista previa del correo</h3>
    <p>
      Complete el número de ticket, el serial y las fallas detectadas para ver
      el diagnóstico institucional en tiempo real.
    </p>
    <p class="preview-hint">
      El serial autodetecta la familia del equipo (por ejemplo, un serial que
      inicia con <code>V10</code> corresponde a Bio500/V10).
    </p>
  </div>
<?php endif; ?>
