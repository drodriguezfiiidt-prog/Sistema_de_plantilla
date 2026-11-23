<?php
/**
 * Detalle de un ticket con su documento institucional.
 *
 * @var \GestioBio\Models\DiagnosticTicket $ticket
 * @var \GestioBio\Services\DiagnosticDocument $document
 */
use GestioBio\Services\Formatting;
use GestioBio\Support\Clock;

$summary = $ticket->itemsSummary();
?>
<div class="page-heading">
  <div>
    <h2>Ticket #<?= e($ticket->ticketNumber) ?></h2>
    <p>
      Serial <?= e($ticket->serialNumber) ?>
      · <?= e($ticket->family?->name ?? 'Sin familia') ?>
      · Garantía <?= e(mb_strtolower($ticket->warrantyLabel())) ?>
    </p>
  </div>
  <div class="page-actions">
    <a class="btn btn-primary" href="<?= e(url('/historial/' . $ticket->id . '/docx')) ?>">
      Descargar Word
    </a>
    <a class="btn btn-ghost" href="<?= e(url('/historial')) ?>">Volver al historial</a>
  </div>
</div>

<section class="card">
  <h3>Datos de la evaluación</h3>
  <dl class="detail-list">
    <dt>Registrado</dt><dd><?= e(Clock::local($ticket->createdAt)) ?></dd>
    <dt>Familia</dt><dd><?= e($ticket->family?->name ?? 'No determinada') ?></dd>
    <dt>Garantía</dt><dd><?= e($ticket->warrantyLabel()) ?></dd>
    <dt>Fallas y repuestos</dt><dd><?= e($summary !== '' ? $summary : '—') ?></dd>
    <dt>Total referencial</dt><dd>$<?= e(Formatting::decimal($ticket->totalCents)) ?></dd>
    <dt>Registrado por</dt><dd><?= e($ticket->createdBy ?? '—') ?></dd>
    <dt>Informe técnico</dt>
    <dd><?= nl2br(e($ticket->technicalReport !== '' ? $ticket->technicalReport : 'Sin observaciones.')) ?></dd>
  </dl>
</section>

<section class="card">
  <h3>Documento institucional</h3>
  <p class="saved-note"><strong>Asunto:</strong> <?= e($document->subject) ?></p>
  <?= render_partial('partials/document', ['document' => $document]) ?>
</section>
