<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$context = pet_boot_api();

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_response(['ok' => false, 'erro' => 'Metodo nao permitido.'], 405);
    }

    $type = pet_text($_POST['tipo'] ?? '', 30);
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $map = [
        'tutor' => ['table' => 'pet_tutores', 'category' => 'tutores', 'permission' => 'editar_cadastros'],
        'animal' => ['table' => 'pet_animais', 'category' => 'animais', 'permission' => 'editar_cadastros'],
        'veterinario' => ['table' => 'pet_veterinarios', 'category' => 'veterinarios', 'permission' => 'gerenciar_equipe'],
    ];

    if (!isset($map[$type]) || !$id) {
        pet_validation_error(['foto' => 'Destino da foto invalido.']);
    }

    $target = $map[$type];
    pet_require_permission($context, $target['permission']);

    $stmt = pet_execute(
        "SELECT foto_caminho FROM {$target['table']} WHERE id = ? LIMIT 1",
        'i',
        [(int) $id]
    );
    $record = $stmt->get_result()->fetch_assoc();
    if (!$record) {
        json_response(['ok' => false, 'erro' => 'Cadastro nao encontrado.'], 404);
    }

    $newPath = pet_upload_photo($_FILES['foto'] ?? [], $target['category']);
    pet_execute(
        "UPDATE {$target['table']} SET foto_caminho = ? WHERE id = ?",
        'si',
        [$newPath, (int) $id]
    );
    pet_remove_photo($record['foto_caminho'] ?? null);

    pet_audit($context, 'atualizar_foto', $type, (int) $id);
    json_response([
        'ok' => true,
        'mensagem' => 'Foto atualizada.',
        'data' => ['foto_caminho' => $newPath],
    ]);
} catch (Throwable $exception) {
    pet_api_exception($exception, 'Nao foi possivel salvar a foto.');
}
