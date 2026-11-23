<?php
/**
 * Página de error genérica.
 *
 * @var int $status
 * @var string $title
 * @var string $message
 */
?>
<div class="page-heading">
  <div>
    <h2><?= e($title) ?></h2>
    <p>Código <?= (int) $status ?> · <?= e($brand_name) ?></p>
  </div>
  <a class="btn btn-primary" href="<?= e(url('/')) ?>">Volver al generador</a>
</div>

<section class="card">
  <p><?= e($message) ?></p>
</section>
