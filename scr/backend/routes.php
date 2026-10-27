<?php

$router->get('/', [HomeController::class, 'index']);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login'], ['csrf']);
$router->post('/logout', [AuthController::class, 'logout'], ['auth', 'csrf']);
$router->get('/admin', [AdminController::class, 'index'], ['auth']);
$router->get('/products/{slug}', [HomeController::class, 'product']);
$router->get('/users/{id}', [HomeController::class, 'user']);
