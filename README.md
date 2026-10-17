# VetCompartido — versión 2 (XAMPP / Windows)

Sistema inicial de gestión veterinaria multi-clínica en PHP 8.1+, MySQL/MariaDB, HTML, CSS y JavaScript.

## Incluye en esta versión
- Panel admin para dar de alta/suspender clínicas y crear el primer usuario.
- Login de veterinaria por nombre de clínica, correo y contraseña; opción Recordarme con token persistente rotatorio.
- Portal de cliente por DNI + correo (para producción se recomienda agregar código de verificación/OTP).
- Clientes y mascotas compartidos por DNI, mascotas con ID, peso, altura, raza, tipo y foto opcional.
- Historial clínico común con consultas, vacunas, estados, medicamentos y edición de consulta.
- Registro de auditoría de modificaciones clínicas.
- Stock de medicamentos por veterinaria.
- Turnos: veterinaria asigna y actualiza estado; cliente solicita turnos en clínicas que ya atendieron a alguna mascota suya.
- Portal del cliente para agregar mascotas, ver historiales y adjuntar documentos.
- Descarga autenticada de archivos adjuntos.
- CSS adaptable para una interfaz moderna, profesional y sencilla.

## Instalación con XAMPP
1. Instalar XAMPP desde https://www.apachefriends.org/.
2. Descomprimir esta carpeta en `C:\\xampp\\htdocs\\vetcompartido`.
3. Abrir el panel XAMPP e iniciar Apache y MySQL.
4. Entrar en `http://localhost/phpmyadmin`, crear/importar la base ejecutando `database/schema.sql`.
5. Revisar `config/config.php` y configurar usuario/contraseña de MySQL.
6. Crear el primer administrador desde CMD o PowerShell, situado en la carpeta del proyecto:
   `php scripts/create_admin.php admin@ejemplo.com "UnaClaveLarga123!"`
7. Abrir `http://localhost/vetcompartido/admin/login.php`, ingresar con el administrador y crear veterinarias.
8. Entrar en `http://localhost/vetcompartido/login.php` para el panel veterinario.
9. Portal del cliente: `http://localhost/vetcompartido/cliente/login.php`.

## Importante
- Importar el esquema completo en una base nueva. Si ya tenés la versión anterior con datos, no vuelvas a importar el archivo entero; aplicá una migración controlada.
- Si la ruta del proyecto cambia, configurar `BASE_URL` en `config/config.php`.
- Asegurar que `uploads/` sea escribible por PHP. Los archivos se sirven mediante PHP con validación de sesión; no crear enlaces públicos directos a la carpeta.
- La opción Recordarme usa tabla `remember_tokens`; esa tabla debe existir.
- Esta versión es un MVP. Antes de usar datos reales, revisar la seguridad con un profesional, aplicar HTTPS en hosting, copias de seguridad, política de privacidad/consentimiento y controles de acceso.
- El acceso del cliente DNI + correo no prueba por sí solo que la persona controle ese correo. Para producción, agregar verificación por correo, OTP o contraseña.
- La auditoría registra los cambios posteriores; no es un sistema legalmente inalterable ni reemplaza un procedimiento de corrección clínica documentado.
- Se recomienda validar también tamaño máximo en PHP (`upload_max_filesize`, `post_max_size`) y mantener las dependencias/servidor actualizados.
