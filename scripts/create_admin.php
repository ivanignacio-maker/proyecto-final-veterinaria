<?php
require_once __DIR__.'/../config/config.php';
if(PHP_SAPI!=='cli'){http_response_code(403);exit("Solo CLI\n");}
if($argc<3){fwrite(STDERR,"Uso: php scripts/create_admin.php correo contraseña\n");exit(1);}
$email=$argv[1];$password=$argv[2];
if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<12){fwrite(STDERR,"Correo inválido o contraseña de menos de 12 caracteres.\n");exit(1);}
$q=db()->prepare("INSERT INTO admins(email,password_hash) VALUES(?,?)");
$q->execute([$email,password_hash($password,PASSWORD_DEFAULT)]);
echo "Administrador creado. Guardá la contraseña de forma segura.\n";
