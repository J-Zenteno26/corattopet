<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/public-page-bootstrap.php';
if(isset($_SESSION['id_cliente'])){header('Location: '.appUrl('public/clientes/cuenta.php'),true,302);exit;}
$error='';$email='';
if($_SERVER['REQUEST_METHOD']==='POST'){$email=is_string($_POST['email']??null)?trim($_POST['email']):'';$password=is_string($_POST['password']??null)?$_POST['password']:'';
 if(!validateCsrfToken($_POST['csrf_token']??null))$error='La sesión del formulario expiró.';
 elseif(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)>200)$error='El correo o la contraseña no son correctos.';
 elseif($pdo instanceof PDO){$stmt=$pdo->prepare('SELECT id_cliente,nombre,email,password_hash,activo FROM clientes WHERE LOWER(TRIM(email))=LOWER(TRIM(:email)) LIMIT 1');$stmt->execute(['email'=>$email]);$client=$stmt->fetch();if(!is_array($client)||!valorBooleanoPublico($client['activo'])||!is_string($client['password_hash'])||!password_verify($password,$client['password_hash']))$error='El correo o la contraseña no son correctos.';else{session_regenerate_id(true);$_SESSION['id_cliente']=(int)$client['id_cliente'];$_SESSION['cliente_nombre']=(string)$client['nombre'];$_SESSION['cliente_email']=(string)$client['email'];header('Location: '.appUrl('public/clientes/cuenta.php'),true,303);exit;}}
 else $error='El acceso no está disponible temporalmente.';}
renderPublicPageStart('Iniciar sesión | Coratto Pet','Accede a tu cuenta de cliente Coratto Pet.','cuenta');
?><main id="contenido"><div class="public-shell"><section class="public-form-card"><span class="public-eyebrow">BIENVENIDO DE NUEVO</span><h1>Iniciar sesión</h1><?php if($error):?><p class="form-errors" role="alert"><?=e($error)?></p><?php endif;?><form class="public-form" method="post"><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>"><label class="full">Correo electrónico<input type="email" name="email" maxlength="160" required value="<?=e($email)?>" autocomplete="email"></label><label class="full">Contraseña<input type="password" name="password" maxlength="200" required autocomplete="current-password"></label><div class="full"><button class="button" type="submit">Entrar</button></div></form><div class="auth-links"><a href="<?=e(appUrl('public/clientes/registro.php'))?>">Crear una cuenta</a></div></section></div></main><?php renderPublicPageEnd(); ?>
