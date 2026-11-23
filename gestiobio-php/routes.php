<?php

declare(strict_types=1);

use GestioBio\Controllers\ApiController;
use GestioBio\Controllers\AuthController;
use GestioBio\Controllers\DocxController;
use GestioBio\Controllers\GeneratorController;
use GestioBio\Controllers\HistoryController;
use GestioBio\Controllers\ManagementController;
use GestioBio\Controllers\PreviewController;
use GestioBio\Router;

/**
 * Tabla de rutas (equivalentes a email_generator/urls.py del proyecto Django).
 */
$router = new Router();

$router->get('/', [GeneratorController::class, 'index']);
$router->post('/', [GeneratorController::class, 'index']);

$router->get('/vista-previa/', [PreviewController::class, 'index']);
$router->post('/descargar/', [DocxController::class, 'download']);
$router->get('/api/contexto/', [ApiController::class, 'context']);

$router->get('/historial/', [HistoryController::class, 'index']);
$router->get('/historial/{id}/', [HistoryController::class, 'show']);
$router->get('/historial/{id}/docx/', [DocxController::class, 'ticket']);

$router->get('/gestion/', [ManagementController::class, 'index']);
$router->get('/gestion/articulos/nuevo/', [ManagementController::class, 'itemCreate']);
$router->post('/gestion/articulos/nuevo/', [ManagementController::class, 'itemCreate']);
$router->get('/gestion/articulos/{id}/', [ManagementController::class, 'itemEdit']);
$router->post('/gestion/articulos/{id}/', [ManagementController::class, 'itemEdit']);
$router->post('/gestion/articulos/{id}/precio/', [ManagementController::class, 'priceUpdate']);
$router->post('/gestion/articulos/{id}/alternar/', [ManagementController::class, 'toggle']);
$router->get('/gestion/familias/nueva/', [ManagementController::class, 'familyCreate']);
$router->post('/gestion/familias/nueva/', [ManagementController::class, 'familyCreate']);
$router->get('/gestion/familias/{id}/', [ManagementController::class, 'familyEdit']);
$router->post('/gestion/familias/{id}/', [ManagementController::class, 'familyEdit']);

$router->get('/login/', [AuthController::class, 'login']);
$router->post('/login/', [AuthController::class, 'login']);
$router->post('/logout/', [AuthController::class, 'logout']);

return $router;
