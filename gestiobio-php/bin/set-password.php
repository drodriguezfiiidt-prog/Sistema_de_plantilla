<?php

declare(strict_types=1);

/**
 * Cambia la contraseña de un usuario (equivalente a manage.py changepassword).
 *
 * Uso: php bin/set-password.php <usuario> [nueva-contraseña]
 */

require dirname(__DIR__) . '/bootstrap.php';

use GestioBio\Models\User;

$username = $argv[1] ?? '';
if ($username === '') {
    fwrite(STDERR, 'Uso: php bin/set-password.php <usuario> [nueva-contraseña]' . PHP_EOL);
    exit(1);
}

$user = User::findByUsername($username);
if ($user === null) {
    fwrite(STDERR, 'No existe el usuario «' . $username . '».' . PHP_EOL);
    exit(1);
}

$password = $argv[2] ?? '';
if ($password === '') {
    fwrite(STDOUT, 'Nueva contraseña: ');
    $password = trim((string) fgets(STDIN));
}

if (mb_strlen($password, 'UTF-8') < 8) {
    fwrite(STDERR, 'La contraseña debe tener al menos 8 caracteres.' . PHP_EOL);
    exit(1);
}

$user->updatePassword($password);
echo 'Contraseña actualizada para «' . $user->username . '».' . PHP_EOL;
