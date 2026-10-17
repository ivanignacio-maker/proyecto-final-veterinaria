<?php
require_once __DIR__ . '/../config/config.php';
require_client();
csrf_check();

$error = '';

// Procesar el formulario cuando el cliente envía los datos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $type = trim($_POST['animal_type'] ?? '');
    $breed = trim($_POST['breed'] ?? '');
    
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
    <h1>Agregar una mascota</h1>
    <p class="muted">La mascota quedará vinculada a tu cuenta. La veterinaria podrá completar su ficha médica.</p>
    
    <?php if ($error): ?>
        <p><?= e($error) ?></p>
    <?php endif; ?>
    
    <form method="post">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        
        <div class="form-grid">
            <div>
                <label>Nombre</label>
                <input name="name" required>
            </div>
            
            <div>
                <label>Tipo de animal</label>
                <select name="animal_type" required>
                    <option value="">Seleccionar</option>
                    <option>Perro</option>
                    <option>Gato</option>
                    <option>Ave</option>
                    <option>Roedor</option>
                    <option>Reptil</option>
                    <option>Otro</option>
                </select>
            </div>
            
            <div>
                <label>Raza</label>
                <input name="breed">
            </div>
            
            <div>
                <label>Peso (kg)</label>
                <input type="number" min="0" step=".01" name="weight_kg">
            </div>
            
            <div>
                <label>Altura (cm)</label>
                <input type="number" min="0" step=".1" name="height_cm">
            </div>
            
            <div>
                <label>Fecha de nacimiento</label>
                <input type="date" name="birth_date">
            </div>
        </div>
        
        <div class="actions">
            <button>Guardar mascota</button>
            <a class="btn secondary" href="<?= url('cliente/mascotas.php') ?>">Cancelar</a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>