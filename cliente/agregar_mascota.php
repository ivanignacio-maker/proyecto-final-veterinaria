<?php
require_once __DIR__ . '/../config/config.php';
require_client();
csrf_check();

$error = '';

// Procesar el formulario cuando el cliente envía los datos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = format_name(trim($_POST['name'] ?? ''));
    $type = format_name(trim($_POST['animal_type'] ?? ''));
    $breed = format_name(trim($_POST['breed'] ?? ''));
    
    // Convertir campos opcionales numéricos a null si están vacíos
    $weight = ($_POST['weight_kg'] ?? '') !== '' ? $_POST['weight_kg'] : null;
    $height = ($_POST['height_cm'] ?? '') !== '' ? $_POST['height_cm'] : null;
    
    if (!$name || !$type) {
        $error = 'El nombre y tipo de animal son obligatorios.';
    } else {
        $q = db()->prepare("
            INSERT INTO pets(client_id, name, animal_type, breed, weight_kg, height_cm, birth_date) 
            VALUES(?, ?, ?, ?, ?, ?, ?)
        ");
        
        $q->execute([
            $_SESSION['client_id'], 
            $name, 
            $type, 
            $breed ?: null, 
            $weight, 
            $height, 
            $_POST['birth_date'] ?: null
        ]);
        
        flash('Mascota agregada a tu perfil.');
        header('Location: ' . url('cliente/mascotas.php'));
        exit;
    }
}

$pageTitle = 'Agregar mascota';
require __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <h1>Registrar una mascota</h1>
    <p class="muted">La mascota quedará vinculada a tu cuenta. La veterinaria podrá completar su ficha médica y vacunas.</p>
    
    <?php if ($error): ?>
        <div class="notice error">
            <span class="notice-icon">⚠️</span>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>
    
    <form method="post">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        
        <div class="form-grid">
            <div>
                <label>Nombre de la mascota</label>
                <input name="name" placeholder="Ej. Luna" required>
            </div>
            
            <div>
                <label>Tipo de animal</label>
                <select name="animal_type" required>
                    <option value="">Seleccionar especie</option>
                    <option value="Perro">Perro</option>
                    <option value="Gato">Gato</option>
                    <option value="Ave">Ave</option>
                    <option value="Roedor">Roedor</option>
                    <option value="Reptil">Reptil</option>
                    <option value="Otro">Otro</option>
                </select>
            </div>
            
            <div>
                <label>Raza</label>
                <input name="breed" placeholder="Ej. Golden Retriever, Mestizo">
            </div>
            
            <div>
                <label>Peso aproximado (kg)</label>
                <input type="number" min="0" step=".01" name="weight_kg" placeholder="Ej. 12.5">
            </div>
            
            <div>
                <label>Altura aproximada (cm)</label>
                <input type="number" min="0" step=".1" name="height_cm" placeholder="Ej. 45">
            </div>
            
            <div>
                <label>Fecha de nacimiento estimada</label>
                <input type="date" name="birth_date">
            </div>
        </div>
        
        <div class="actions">
            <button class="btn primary" type="submit">🐾 Guardar mascota</button>
            <a class="btn secondary" href="<?= url('cliente/mascotas.php') ?>">Cancelar</a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>