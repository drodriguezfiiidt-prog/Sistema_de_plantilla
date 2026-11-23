<?php
/**
 * Lista de errores de un campo (usar con $messages).
 *
 * @var list<string> $messages
 */
?>
<?php if (!empty($messages)): ?>
  <ul class="errorlist">
    <?php foreach ($messages as $message): ?>
      <li><?= e($message) ?></li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
