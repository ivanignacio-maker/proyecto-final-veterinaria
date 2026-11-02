<?php
/**
 * includes/pets_delete.php
 *
 * Borrado completo de una mascota. Lo usan:
 *   - cliente/api_eliminar_mascota.php  (el tutor da de baja su mascota)
 *   - admin/editar_cliente.php          (el administrador elimina una mascota)
 *
 * Tener UNA sola función evita que dos páginas borren cosas distintas.
 */

/** ¿Existe la tabla y TODAS las columnas indicadas en la base actual? */
function db_has_columns(PDO $pdo, string $table, array $columns): bool {
    $q = $pdo->prepare("
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
    ");
    foreach ($columns as $col) {
        $q->execute([$table, $col]);
        if ((int)$q->fetchColumn() === 0) {
            return false;
        }
    }
    return true;
}

/**
 * Borra la mascota y todo lo que depende de ella (co-tutores, códigos, turnos,
 * recordatorios, consultas, medicación y adjuntos), todo dentro de una transacción:
 * o se borra todo o no se borra nada.
 *
 * Cada paso tiene un nombre. Si alguno falla, la excepción lo menciona
 * ("Paso «turnos»: ...") y así sabemos exactamente dónde está el problema.
 * Si una tabla o columna no existe en tu base, ese paso se saltea.
 *
 * Devuelve los archivos del disco que hay que borrar DESPUÉS de confirmar:
 *   ['stored' => [nombres de adjuntos], 'photo' => ruta de la foto o null]
 *
 * @throws RuntimeException si la mascota no existe o falla algún paso.
 */
function delete_pet_cascade(PDO $pdo, int $petId): array {
    $q = $pdo->prepare('SELECT id, photo_path FROM pets WHERE id = ?');
    $q->execute([$petId]);
    $pet = $q->fetch();
    if (!$pet) {
        throw new RuntimeException('La mascota no existe o ya fue eliminada.');
    }

    // [nombre del paso, SQL, tabla/columnas que tienen que existir para ejecutarlo]
    $steps = [
        ['co-tutores',    'DELETE FROM pet_co_owners WHERE pet_id = ?',
            ['pet_co_owners', ['pet_id']]],
        ['códigos de acceso', 'DELETE FROM pet_access_codes WHERE pet_id = ?',
            ['pet_access_codes', ['pet_id']]],
        ['recordatorios de vacunas',
            'DELETE vr FROM vaccine_reminders vr JOIN consultations co ON co.id = vr.consultation_id WHERE co.pet_id = ?',
            ['vaccine_reminders', ['consultation_id']]],
        ['turnos',        'DELETE FROM appointments WHERE pet_id = ?',
            ['appointments', ['pet_id']]],
        ['medicación de consultas',
            'DELETE cm FROM consultation_medications cm JOIN consultations co ON co.id = cm.consultation_id WHERE co.pet_id = ?',
            ['consultation_medications', ['consultation_id']]],
        ['archivos de consultas',
            'DELETE cf FROM consultation_files cf JOIN consultations co ON co.id = cf.consultation_id WHERE co.pet_id = ?',
            ['consultation_files', ['consultation_id']]],
        ['consultas',     'DELETE FROM consultations WHERE pet_id = ?',
            ['consultations', ['pet_id']]],
    ];

    $storedFiles = [];
    $currentStep = 'inicio';

    try {
        $pdo->beginTransaction();

        // Antes de borrar las filas, anotamos qué adjuntos hay que quitar del disco.
        $currentStep = 'lectura de adjuntos';
        if (db_has_columns($pdo, 'consultation_files', ['consultation_id', 'stored_name'])) {
            $files = $pdo->prepare('
                SELECT cf.stored_name
                FROM consultation_files cf
                JOIN consultations co ON co.id = cf.consultation_id
                WHERE co.pet_id = ?
            ');
            $files->execute([$petId]);
            $storedFiles = $files->fetchAll(PDO::FETCH_COLUMN);
        }

        foreach ($steps as [$label, $sql, [$table, $columns]]) {
            $currentStep = $label;
            if (!db_has_columns($pdo, $table, $columns)) {
                continue; // esa tabla/columna no existe en tu base: no hay nada que borrar
            }
            $pdo->prepare($sql)->execute([$petId]);
        }

        $currentStep = 'mascota';
        $pdo->prepare('DELETE FROM pets WHERE id = ?')->execute([$petId]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw new RuntimeException('Paso «' . $currentStep . '»: ' . $e->getMessage(), 0, $e);
    }

    return [
        'stored' => array_values(array_filter($storedFiles, 'is_string')),
        'photo'  => !empty($pet['photo_path']) ? (string)$pet['photo_path'] : null,
    ];
}

/**
 * Quita del disco los archivos devueltos por delete_pet_cascade().
 * Solo toca archivos que estén realmente dentro de la carpeta de uploads.
 */
function delete_pet_files(array $result): void {
    $root = realpath(ensure_upload_dir());
    if ($root === false) {
        return;
    }
    $prefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    $paths = $result['stored'] ?? [];
    if (!empty($result['photo'])) {
        $paths[] = $result['photo'];
    }
    foreach ($paths as $relative) {
        $candidate = realpath($root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string)$relative));
        if ($candidate !== false && is_file($candidate) && str_starts_with(strtolower($candidate), strtolower($prefix))) {
            @unlink($candidate);
        }
    }
}
