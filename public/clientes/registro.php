<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/public-page-bootstrap.php';
if (isset($_SESSION['id_cliente'])) { header('Location: ' . appUrl('public/clientes/cuenta.php'), true, 302); exit; }
$errors=[];$values=['nombre'=>'','apellido'=>'','email'=>'','telefono'=>''];
if ($_SERVER['REQUEST_METHOD']==='POST') {
 foreach($values as $k=>$_)$values[$k]=is_string($_POST[$k]??null)?trim($_POST[$k]):'';
 $password=is_string($_POST['password']??null)?$_POST['password']:'';$confirm=is_string($_POST['password_confirm']??null)?$_POST['password_confirm']:'';
 if(!validateCsrfToken($_POST['csrf_token']??null))$errors[]='La sesión del formulario expiró.';
 if($values['nombre']===''||mb_strlen($values['nombre'])>100)$errors[]='Ingresa un nombre válido.';
 if($values['apellido']===''||mb_strlen($values['apellido'])>100)$errors[]='Ingresa un apellido válido.';
 if(!filter_var($values['email'],FILTER_VALIDATE_EMAIL)||mb_strlen($values['email'])>160)$errors[]='Ingresa un correo válido.';
 if(mb_strlen($values['telefono'])>40)$errors[]='El teléfono es demasiado largo.';
 if(strlen($password)<10||strlen($password)>200)$errors[]='La contraseña debe tener al menos 10 caracteres.';
 if($password!==$confirm)$errors[]='Las contraseñas no coinciden.';
 if(($_POST['acepta']??'')!=='1')$errors[]='Debes aceptar los términos y la política de privacidad.';
 if($errors===[]&&$pdo instanceof PDO){
  $check=$pdo->prepare('SELECT 1 FROM clientes WHERE LOWER(TRIM(email))=LOWER(TRIM(:email)) LIMIT 1');$check->execute(['email'=>$values['email']]);
  if($check->fetchColumn())$errors[]='Ya existe una cuenta asociada a ese correo.';
  else{$stmt=$pdo->prepare('INSERT INTO clientes (nombre,apellido,email,telefono,password_hash,activo,email_verificado,creado_en,actualizado_en) VALUES (:nombre,:apellido,:email,:telefono,:password_hash,TRUE,FALSE,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP) RETURNING id_cliente');$stmt->execute(['nombre'=>$values['nombre'],'apellido'=>$values['apellido'],'email'=>mb_strtolower($values['email']),'telefono'=>$values['telefono']?:null,'password_hash'=>password_hash($password,PASSWORD_DEFAULT)]);session_regenerate_id(true);$_SESSION['id_cliente']=(int)$stmt->fetchColumn();$_SESSION['cliente_nombre']=$values['nombre'];$_SESSION['cliente_email']=mb_strtolower($values['email']);header('Location: '.appUrl('public/clientes/cuenta.php'),true,303);exit;}
 }
 if(!($pdo instanceof PDO)&&$errors===[])$errors[]='El registro no está disponible temporalmente.';
}
renderPublicPageStart('Crear cuenta | Coratto Pet','Crea tu cuenta de cliente en Coratto Pet.','cuenta');
?><main id="contenido"><div class="public-shell"><section class="public-form-card"><span class="public-eyebrow">TU CUENTA CORATTO</span><h1>Crear cuenta</h1><?php if($errors):?><div class="form-errors" role="alert"><ul><?php foreach($errors as $error):?><li><?=e($error)?></li><?php endforeach;?></ul></div><?php endif;?><form class="public-form" method="post"><input type="hidden" name="csrf_token" value="<?=e(csrfToken())?>"><label>Nombre<input name="nombre" maxlength="100" required value="<?=e($values['nombre'])?>"></label><label>Apellido<input name="apellido" maxlength="100" required value="<?=e($values['apellido'])?>"></label><label>Correo electrónico<input type="email" name="email" maxlength="160" required value="<?=e($values['email'])?>"></label><label>Teléfono <span class="field-note">Opcional</span><input name="telefono" maxlength="40" value="<?=e($values['telefono'])?>"></label><label>Contraseña<input type="password" name="password" minlength="10" maxlength="200" required autocomplete="new-password"></label><label>Confirmar contraseña<input type="password" name="password_confirm" minlength="10" maxlength="200" required autocomplete="new-password"></label><label class="public-form__check"><input type="checkbox" name="acepta" value="1" required> Acepto los términos y la política de privacidad.</label><div class="full"><button class="button" type="submit">Crear mi cuenta</button></div></form><div class="auth-links"><a href="<?=e(appUrl('public/clientes/login.php'))?>">Ya tengo una cuenta</a></div></section></div></main><?php renderPublicPageEnd(); ?>
