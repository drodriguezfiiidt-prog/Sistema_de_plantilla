<?php
/**
 * Documento institucional (bloques) compartido entre vista previa, detalle y Word.
 *
 * @var \GestioBio\Services\DiagnosticDocument $document
 */
$kinds = [
    'table' => \GestioBio\Services\Block::TABLE,
    'bullets' => \GestioBio\Services\Block::BULLETS,
    'heading' => \GestioBio\Services\Block::HEADING,
];
?>
<article class="document">
  <?php foreach ($document->blocks as $block): ?>
    <?php if ($block->kind === $kinds['table']): ?>
      <table class="quote-table">
        <thead>
          <tr>
            <?php foreach ($block->columns as $column): ?><th><?= e($column) ?></th><?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($block->rows as $row): ?>
            <tr>
              <?php foreach ($row as $cell): ?><td><?= e($cell) ?></td><?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php elseif ($block->kind === $kinds['bullets']): ?>
      <ul class="document-bullets">
        <?php foreach ($block->items as $item): ?><li><?= e($item) ?></li><?php endforeach; ?>
      </ul>
    <?php elseif ($block->kind === $kinds['heading']): ?>
      <h4 class="document-heading"><?= e($block->text) ?></h4>
    <?php else: ?>
      <p class="document-paragraph<?= $block->bold ? ' is-bold' : '' ?>"><?= nl2br(e($block->text)) ?></p>
    <?php endif; ?>
  <?php endforeach; ?>
</article>

