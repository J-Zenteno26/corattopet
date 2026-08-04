<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/config/app.php';
require_once dirname(__DIR__,2).'/shared/seguridad.php';
if($_SERVER['REQUEST_METHOD']!=='POST'||!validateCsrfToken($_POST['csrf_token']??null)){http_response_code(405);exit;}
unset($_SESSION['id_cliente'],$_SESSION['cliente_nombre'],$_SESSION['cliente_email']);
session_regenerate_id(true);
header('Location: '.appUrl('public/index.php'),true,303);
exit;
