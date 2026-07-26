<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/database.php';

const PET_DEMO_MARKER = '[DEMO_PET_V1]';
const PET_DEMO_VERSION = '1.0.1-demo.1';

function pet_demo_response(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function pet_demo_count(mysqli $conn, string $sql, ?string $filter = null): int
{
    $stmt = $conn->prepare($sql);
    if ($filter !== null) {
        $stmt->bind_param('s', $filter);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_row();
    $stmt->close();

    return (int) ($row[0] ?? 0);
}

function pet_demo_totals(mysqli $conn): array
{
    return [
        'tutores' => pet_demo_count(
            $conn,
            'SELECT COUNT(*) FROM pet_tutores WHERE observacoes LIKE ?',
            PET_DEMO_MARKER . '%'
        ),
        'animais' => pet_demo_count(
            $conn,
            'SELECT COUNT(*) FROM pet_animais WHERE microchip LIKE ?',
            'DEMO-PET-V1-%'
        ),
        'atendimentos' => pet_demo_count(
            $conn,
            'SELECT COUNT(*) FROM pet_atendimentos WHERE motivo LIKE ?',
            PET_DEMO_MARKER . ' Atendimento%'
        ),
        'internacoes' => pet_demo_count(
            $conn,
            'SELECT COUNT(*) FROM pet_internacoes WHERE motivo LIKE ?',
            PET_DEMO_MARKER . ' Internacao%'
        ),
    ];
}

function pet_global_totals(mysqli $conn): array
{
    return [
        'tutores' => pet_demo_count($conn, 'SELECT COUNT(*) FROM pet_tutores'),
        'animais' => pet_demo_count($conn, 'SELECT COUNT(*) FROM pet_animais'),
        'atendimentos' => pet_demo_count($conn, 'SELECT COUNT(*) FROM pet_atendimentos'),
        'internacoes' => pet_demo_count($conn, 'SELECT COUNT(*) FROM pet_internacoes'),
    ];
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    pet_demo_response(405, ['ok' => false, 'codigo' => 'METODO_NAO_PERMITIDO']);
}

$providedKey = (string) ($_SERVER['HTTP_X_API_KEY'] ?? '');
if (!defined('API_KEY') || API_KEY === '' || !hash_equals((string) API_KEY, $providedKey)) {
    pet_demo_response(401, ['ok' => false, 'codigo' => 'API_KEY_INVALIDA']);
}

$conn = null;
$transactionStarted = false;
$stage = 'inicializacao';

try {
    $stage = 'conexao';
    $conn = db();
    $stage = 'migracao_arquivo';
    $migrationPath = __DIR__ . '/sql/migrations/001_v1_0_0_pet_core.sql';
    $migrationSql = file_get_contents($migrationPath);
    if (!is_string($migrationSql) || trim($migrationSql) === '') {
        throw new RuntimeException('Migracao Pet indisponivel.');
    }

    $stage = 'migracao_execucao';
    if (!$conn->multi_query($migrationSql)) {
        throw new mysqli_sql_exception($conn->error, $conn->errno);
    }
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
    if ($conn->errno) {
        throw new mysqli_sql_exception($conn->error, $conn->errno);
    }

    $stage = 'inicio_transacao';
    $conn->begin_transaction();
    $transactionStarted = true;

    $stage = 'tutores';
    $firstNames = [
        'Ana', 'Bruno', 'Carla', 'Daniel', 'Elisa', 'Fabio', 'Gabriela', 'Henrique', 'Isabela', 'Joao',
        'Karen', 'Lucas', 'Mariana', 'Nicolas', 'Olivia', 'Paulo', 'Renata', 'Samuel', 'Talita', 'Vinicius'
    ];
    $lastNames = ['Almeida', 'Barbosa', 'Costa', 'Dias', 'Esteves'];

    $tutorSql = "INSERT INTO pet_tutores
        (nome, cpf, data_nascimento, email, telefone, whatsapp, cidade, uf, observacoes, ativo)
        VALUES (?, ?, ?, ?, ?, ?, 'Leme', 'SP', ?, 1)
        ON DUPLICATE KEY UPDATE
            id = LAST_INSERT_ID(id),
            nome = VALUES(nome),
            data_nascimento = VALUES(data_nascimento),
            email = VALUES(email),
            telefone = VALUES(telefone),
            whatsapp = VALUES(whatsapp),
            cidade = VALUES(cidade),
            uf = VALUES(uf),
            observacoes = VALUES(observacoes),
            ativo = 1";
    $tutorStmt = $conn->prepare($tutorSql);
    $tutorIds = [];

    for ($index = 0; $index < 100; $index++) {
        $number = $index + 1;
        $name = $firstNames[$index % count($firstNames)] . ' ' . $lastNames[intdiv($index, 20)];
        $cpf = '920260' . str_pad((string) $number, 5, '0', STR_PAD_LEFT);
        $year = 1970 + ($index % 31);
        $month = 1 + ($index % 12);
        $day = 1 + ($index % 27);
        $birthDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $email = sprintf('tutor%03d@demo.lemeinformatica.local', $number);
        $phone = '1998' . str_pad((string) (1000000 + $number), 7, '0', STR_PAD_LEFT);
        $whatsapp = $phone;
        $notes = PET_DEMO_MARKER . ' Tutor demonstrativo ' . str_pad((string) $number, 3, '0', STR_PAD_LEFT) . '.';

        $tutorStmt->bind_param('sssssss', $name, $cpf, $birthDate, $email, $phone, $whatsapp, $notes);
        $tutorStmt->execute();
        $tutorIds[] = (int) $conn->insert_id;
    }
    $tutorStmt->close();

    $stage = 'animais';
    $animalNames = [
        'Amora', 'Apolo', 'Bela', 'Bento', 'Bob', 'Cacau', 'Chico', 'Dara', 'Fred', 'Jade',
        'Kiara', 'Lola', 'Luke', 'Maya', 'Mel', 'Nina', 'Paco', 'Sol', 'Theo', 'Zeca'
    ];
    $profiles = [
        ['Cao', 'Sem raca definida', 'medio', 'Caramelo'],
        ['Cao', 'Labrador Retriever', 'grande', 'Dourado'],
        ['Cao', 'Shih-tzu', 'pequeno', 'Branco e marrom'],
        ['Cao', 'Border Collie', 'medio', 'Preto e branco'],
        ['Gato', 'Sem raca definida', 'pequeno', 'Tigrado'],
        ['Gato', 'Siames', 'pequeno', 'Creme'],
        ['Ave', 'Calopsita', 'pequeno', 'Cinza e amarelo'],
        ['Coelho', 'Mini Lop', 'pequeno', 'Branco']
    ];

    $animalSql = "INSERT INTO pet_animais
        (tutor_id, nome, especie, raca, sexo, data_nascimento, cor, peso_kg, porte, microchip, castrado, observacoes, ativo)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE
            id = LAST_INSERT_ID(id),
            tutor_id = VALUES(tutor_id),
            nome = VALUES(nome),
            especie = VALUES(especie),
            raca = VALUES(raca),
            sexo = VALUES(sexo),
            data_nascimento = VALUES(data_nascimento),
            cor = VALUES(cor),
            peso_kg = VALUES(peso_kg),
            porte = VALUES(porte),
            castrado = VALUES(castrado),
            observacoes = VALUES(observacoes),
            ativo = 1";
    $animalStmt = $conn->prepare($animalSql);
    $animalIds = [];
    $animalWeights = [];

    for ($index = 0; $index < 200; $index++) {
        $number = $index + 1;
        $tutorId = $tutorIds[intdiv($index, 2)];
        $profile = $profiles[$index % count($profiles)];
        $animalName = $animalNames[$index % count($animalNames)] . ' ' . str_pad((string) $number, 3, '0', STR_PAD_LEFT);
        $species = $profile[0];
        $breed = $profile[1];
        $size = $profile[2];
        $color = $profile[3];
        $sex = $index % 2 === 0 ? 'macho' : 'femea';
        $birthYear = 2016 + ($index % 10);
        $birthMonth = 1 + ($index % 12);
        $birthDay = 1 + ($index % 27);
        $animalBirthDate = sprintf('%04d-%02d-%02d', $birthYear, $birthMonth, $birthDay);
        $weight = match ($species) {
            'Cao' => 6.0 + (($index * 7) % 260) / 10,
            'Gato' => 2.5 + (($index * 3) % 45) / 10,
            'Ave' => 0.08 + (($index % 5) / 100),
            default => 1.2 + (($index % 20) / 10),
        };
        $microchip = 'DEMO-PET-V1-' . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
        $neutered = $index % 3 === 0 ? 1 : 0;
        $animalNotes = PET_DEMO_MARKER . ' Animal demonstrativo ' . str_pad((string) $number, 3, '0', STR_PAD_LEFT) . '.';

        $animalStmt->bind_param(
            'issssssdssis',
            $tutorId,
            $animalName,
            $species,
            $breed,
            $sex,
            $animalBirthDate,
            $color,
            $weight,
            $size,
            $microchip,
            $neutered,
            $animalNotes
        );
        $animalStmt->execute();
        $animalIds[] = (int) $conn->insert_id;
        $animalWeights[] = $weight;
    }
    $animalStmt->close();

    $stage = 'atendimentos';
    $appointmentExistsStmt = $conn->prepare('SELECT id FROM pet_atendimentos WHERE motivo = ? LIMIT 1');
    $appointmentSql = "INSERT INTO pet_atendimentos
        (animal_id, tipo, status, inicio_em, fim_em, motivo, anamnese, exame_clinico, peso_kg,
         temperatura_c, frequencia_cardiaca, frequencia_respiratoria, diagnostico, conduta, prescricao)
        VALUES (?, 'consulta', 'concluido', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $appointmentStmt = $conn->prepare($appointmentSql);
    $appointmentIds = [];

    for ($index = 0; $index < 50; $index++) {
        $number = $index + 1;
        $animalId = $animalIds[$index];
        $month = 5 + intdiv($index, 25);
        $day = 1 + ($index % 25);
        $hour = 8 + ($index % 9);
        $start = sprintf('2026-%02d-%02d %02d:00:00', $month, $day, $hour);
        $end = sprintf('2026-%02d-%02d %02d:45:00', $month, $day, $hour);
        $reason = PET_DEMO_MARKER . ' Atendimento ' . str_pad((string) $number, 3, '0', STR_PAD_LEFT);

        $appointmentExistsStmt->bind_param('s', $reason);
        $appointmentExistsStmt->execute();
        $existingResult = $appointmentExistsStmt->get_result();
        $existingRow = $existingResult->fetch_assoc();
        if ($existingRow) {
            $appointmentIds[] = (int) $existingRow['id'];
            continue;
        }

        $anamnesis = 'Tutor relata acompanhamento preventivo e boa ingestao de agua e alimento.';
        $clinicalExam = 'Paciente alerta, hidratado e com parametros vitais dentro da faixa esperada.';
        $examWeight = $animalWeights[$index];
        $temperature = 38.0 + (($index % 8) / 10);
        $heartRate = 80 + ($index % 45);
        $respiratoryRate = 18 + ($index % 15);
        $diagnosis = $index % 5 === 0 ? 'Dermatite leve em acompanhamento.' : 'Paciente clinicamente estavel.';
        $conduct = 'Orientacoes preventivas, controle de peso e retorno programado.';
        $prescription = $index % 5 === 0 ? 'Higienizacao topica conforme orientacao veterinaria.' : 'Sem medicacao no momento.';

        $appointmentStmt->bind_param(
            'isssssddiisss',
            $animalId,
            $start,
            $end,
            $reason,
            $anamnesis,
            $clinicalExam,
            $examWeight,
            $temperature,
            $heartRate,
            $respiratoryRate,
            $diagnosis,
            $conduct,
            $prescription
        );
        $appointmentStmt->execute();
        $appointmentIds[] = (int) $conn->insert_id;
    }
    $appointmentExistsStmt->close();
    $appointmentStmt->close();

    $stage = 'internacoes';
    $hospitalExistsStmt = $conn->prepare('SELECT id FROM pet_internacoes WHERE motivo = ? LIMIT 1');
    $hospitalSql = "INSERT INTO pet_internacoes
        (animal_id, atendimento_origem_id, status, entrada_em, previsao_alta_em, setor, leito,
         classificacao_risco, motivo, diagnostico_inicial, plano_cuidados)
        VALUES (?, ?, 'ativa', ?, ?, ?, ?, 'moderado', ?, ?, ?)";
    $hospitalStmt = $conn->prepare($hospitalSql);

    for ($index = 0; $index < 2; $index++) {
        $number = $index + 1;
        $animalId = $animalIds[$index];
        $appointmentId = $appointmentIds[$index] ?? null;
        $hospitalReason = PET_DEMO_MARKER . ' Internacao ' . str_pad((string) $number, 3, '0', STR_PAD_LEFT);

        $hospitalExistsStmt->bind_param('s', $hospitalReason);
        $hospitalExistsStmt->execute();
        if ($hospitalExistsStmt->get_result()->fetch_assoc()) {
            continue;
        }

        $entry = sprintf('2026-07-%02d 09:30:00', 25 + $index);
        $expectedDischarge = sprintf('2026-07-%02d 16:00:00', 27 + $index);
        $sector = 'Clinica medica';
        $bed = 'INT-' . str_pad((string) $number, 2, '0', STR_PAD_LEFT);
        $initialDiagnosis = $index === 0 ? 'Gastroenterite com desidratacao moderada.' : 'Observacao pos-operatoria.';
        $carePlan = 'Monitoramento de sinais vitais, hidratacao, analgesia e reavaliacao a cada turno.';

        $hospitalStmt->bind_param(
            'iisssssss',
            $animalId,
            $appointmentId,
            $entry,
            $expectedDischarge,
            $sector,
            $bed,
            $hospitalReason,
            $initialDiagnosis,
            $carePlan
        );
        $hospitalStmt->execute();
    }
    $hospitalExistsStmt->close();
    $hospitalStmt->close();

    $stage = 'validacao_totais';
    $demoTotals = pet_demo_totals($conn);
    $expectedTotals = ['tutores' => 100, 'animais' => 200, 'atendimentos' => 50, 'internacoes' => 2];
    if ($demoTotals !== $expectedTotals) {
        throw new RuntimeException('A carga demonstrativa nao atingiu os totais esperados.');
    }

    $auditDetails = json_encode(
        ['versao' => PET_DEMO_VERSION, 'totais_demo' => $demoTotals],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    $auditStmt = $conn->prepare(
        "INSERT INTO pet_audit_log (usuario_id, acao, entidade, detalhes_json, ip)
         VALUES (NULL, 'provisionar_demo', 'modulo_pet', ?, '')"
    );
    $auditStmt->bind_param('s', $auditDetails);
    $auditStmt->execute();
    $auditStmt->close();

    $conn->commit();
    $transactionStarted = false;

    pet_demo_response(200, [
        'ok' => true,
        'versao' => PET_DEMO_VERSION,
        'modo' => 'idempotente_sem_exclusao',
        'demo' => $demoTotals,
        'totais_banco' => pet_global_totals($conn),
    ]);
} catch (Throwable $exception) {
    if ($transactionStarted && $conn instanceof mysqli) {
        $conn->rollback();
    }
    error_log('[PET DEMO] ' . $exception->getMessage());
    pet_demo_response(500, [
        'ok' => false,
        'codigo' => 'CARGA_DEMO_FALHOU',
        'etapa' => $stage,
        'erro_tipo' => get_class($exception),
        'erro_codigo' => (int) $exception->getCode(),
    ]);
}
<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/database.php';

const PET_DEMO_MARKER = '[DEMO_PET_V1]';
const PET_DEMO_VERSION = '1.0.1-demo.1';

function pet_demo_response(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function pet_demo_count(mysqli $conn, string $sql, ?string $filter = null): int
{
    $stmt = $conn->prepare($sql);
    if ($filter !== null) {
        $stmt->bind_param('s', $filter);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_row();
    $stmt->close();

    return (int) ($row[0] ?? 0);
}

function pet_demo_totals(mysqli $conn): array
{
    return [
        'tutores' => pet_demo_count(
            $conn,
            'SELECT COUNT(*) FROM pet_tutores WHERE observacoes LIKE ?',
            PET_DEMO_MARKER . '%'
        ),
        'animais' => pet_demo_count(
            $conn,
            'SELECT COUNT(*) FROM pet_animais WHERE microchip LIKE ?',
            'DEMO-PET-V1-%'
        ),
        'atendimentos' => pet_demo_count(
            $conn,
            'SELECT COUNT(*) FROM pet_atendimentos WHERE motivo LIKE ?',
            PET_DEMO_MARKER . ' Atendimento%'
        ),
        'internacoes' => pet_demo_count(
            $conn,
            'SELECT COUNT(*) FROM pet_internacoes WHERE motivo LIKE ?',
            PET_DEMO_MARKER . ' Internacao%'
        ),
    ];
}

function pet_global_totals(mysqli $conn): array
{
    return [
        'tutores' => pet_demo_count($conn, 'SELECT COUNT(*) FROM pet_tutores'),
        'animais' => pet_demo_count($conn, 'SELECT COUNT(*) FROM pet_animais'),
        'atendimentos' => pet_demo_count($conn, 'SELECT COUNT(*) FROM pet_atendimentos'),
        'internacoes' => pet_demo_count($conn, 'SELECT COUNT(*) FROM pet_internacoes'),
    ];
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    pet_demo_response(405, ['ok' => false, 'codigo' => 'METODO_NAO_PERMITIDO']);
}

$providedKey = (string) ($_SERVER['HTTP_X_API_KEY'] ?? '');
if (!defined('API_KEY') || API_KEY === '' || !hash_equals((string) API_KEY, $providedKey)) {
    pet_demo_response(401, ['ok' => false, 'codigo' => 'API_KEY_INVALIDA']);
}

$conn = null;
$transactionStarted = false;

try {
    $conn = db();
    $migrationPath = __DIR__ . '/sql/migrations/001_v1_0_0_pet_core.sql';
    $migrationSql = file_get_contents($migrationPath);
    if (!is_string($migrationSql) || trim($migrationSql) === '') {
        throw new RuntimeException('Migracao Pet indisponivel.');
    }

    if (!$conn->multi_query($migrationSql)) {
        throw new RuntimeException('Falha ao iniciar a migracao Pet.');
    }
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
    if ($conn->errno) {
        throw new mysqli_sql_exception($conn->error, $conn->errno);
    }

    $conn->begin_transaction();
    $transactionStarted = true;

    $firstNames = [
        'Ana', 'Bruno', 'Carla', 'Daniel', 'Elisa', 'Fabio', 'Gabriela', 'Henrique', 'Isabela', 'Joao',
        'Karen', 'Lucas', 'Mariana', 'Nicolas', 'Olivia', 'Paulo', 'Renata', 'Samuel', 'Talita', 'Vinicius'
    ];
    $lastNames = ['Almeida', 'Barbosa', 'Costa', 'Dias', 'Esteves'];

    $tutorSql = "INSERT INTO pet_tutores
        (nome, cpf, data_nascimento, email, telefone, whatsapp, cidade, uf, observacoes, ativo)
        VALUES (?, ?, ?, ?, ?, ?, 'Leme', 'SP', ?, 1)
        ON DUPLICATE KEY UPDATE
            id = LAST_INSERT_ID(id),
            nome = VALUES(nome),
            data_nascimento = VALUES(data_nascimento),
            email = VALUES(email),
            telefone = VALUES(telefone),
            whatsapp = VALUES(whatsapp),
            cidade = VALUES(cidade),
            uf = VALUES(uf),
            observacoes = VALUES(observacoes),
            ativo = 1";
    $tutorStmt = $conn->prepare($tutorSql);
    $tutorIds = [];

    for ($index = 0; $index < 100; $index++) {
        $number = $index + 1;
        $name = $firstNames[$index % count($firstNames)] . ' ' . $lastNames[intdiv($index, 20)];
        $cpf = '920260' . str_pad((string) $number, 5, '0', STR_PAD_LEFT);
        $year = 1970 + ($index % 31);
        $month = 1 + ($index % 12);
        $day = 1 + ($index % 27);
        $birthDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $email = sprintf('tutor%03d@demo.lemeinformatica.local', $number);
        $phone = '1998' . str_pad((string) (1000000 + $number), 7, '0', STR_PAD_LEFT);
        $whatsapp = $phone;
        $notes = PET_DEMO_MARKER . ' Tutor demonstrativo ' . str_pad((string) $number, 3, '0', STR_PAD_LEFT) . '.';

        $tutorStmt->bind_param('sssssss', $name, $cpf, $birthDate, $email, $phone, $whatsapp, $notes);
        $tutorStmt->execute();
        $tutorIds[] = (int) $conn->insert_id;
    }
    $tutorStmt->close();

    $animalNames = [
        'Amora', 'Apolo', 'Bela', 'Bento', 'Bob', 'Cacau', 'Chico', 'Dara', 'Fred', 'Jade',
        'Kiara', 'Lola', 'Luke', 'Maya', 'Mel', 'Nina', 'Paco', 'Sol', 'Theo', 'Zeca'
    ];
    $profiles = [
        ['Cao', 'Sem raca definida', 'medio', 'Caramelo'],
        ['Cao', 'Labrador Retriever', 'grande', 'Dourado'],
        ['Cao', 'Shih-tzu', 'pequeno', 'Branco e marrom'],
        ['Cao', 'Border Collie', 'medio', 'Preto e branco'],
        ['Gato', 'Sem raca definida', 'pequeno', 'Tigrado'],
        ['Gato', 'Siames', 'pequeno', 'Creme'],
        ['Ave', 'Calopsita', 'pequeno', 'Cinza e amarelo'],
        ['Coelho', 'Mini Lop', 'pequeno', 'Branco']
    ];

    $animalSql = "INSERT INTO pet_animais
        (tutor_id, nome, especie, raca, sexo, data_nascimento, cor, peso_kg, porte, microchip, castrado, observacoes, ativo)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE
            id = LAST_INSERT_ID(id),
            tutor_id = VALUES(tutor_id),
            nome = VALUES(nome),
            especie = VALUES(especie),
            raca = VALUES(raca),
            sexo = VALUES(sexo),
            data_nascimento = VALUES(data_nascimento),
            cor = VALUES(cor),
            peso_kg = VALUES(peso_kg),
            porte = VALUES(porte),
            castrado = VALUES(castrado),
            observacoes = VALUES(observacoes),
            ativo = 1";
    $animalStmt = $conn->prepare($animalSql);
    $animalIds = [];
    $animalWeights = [];

    for ($index = 0; $index < 200; $index++) {
        $number = $index + 1;
        $tutorId = $tutorIds[intdiv($index, 2)];
        $profile = $profiles[$index % count($profiles)];
        $animalName = $animalNames[$index % count($animalNames)] . ' ' . str_pad((string) $number, 3, '0', STR_PAD_LEFT);
        $species = $profile[0];
        $breed = $profile[1];
        $size = $profile[2];
        $color = $profile[3];
        $sex = $index % 2 === 0 ? 'macho' : 'femea';
        $birthYear = 2016 + ($index % 10);
        $birthMonth = 1 + ($index % 12);
        $birthDay = 1 + ($index % 27);
        $animalBirthDate = sprintf('%04d-%02d-%02d', $birthYear, $birthMonth, $birthDay);
        $weight = match ($species) {
            'Cao' => 6.0 + (($index * 7) % 260) / 10,
            'Gato' => 2.5 + (($index * 3) % 45) / 10,
            'Ave' => 0.08 + (($index % 5) / 100),
            default => 1.2 + (($index % 20) / 10),
        };
        $microchip = 'DEMO-PET-V1-' . str_pad((string) $number, 4, '0', STR_PAD_LEFT);
        $neutered = $index % 3 === 0 ? 1 : 0;
        $animalNotes = PET_DEMO_MARKER . ' Animal demonstrativo ' . str_pad((string) $number, 3, '0', STR_PAD_LEFT) . '.';

        $animalStmt->bind_param(
            'issssssdssis',
            $tutorId,
            $animalName,
            $species,
            $breed,
            $sex,
            $animalBirthDate,
            $color,
            $weight,
            $size,
            $microchip,
            $neutered,
            $animalNotes
        );
        $animalStmt->execute();
        $animalIds[] = (int) $conn->insert_id;
        $animalWeights[] = $weight;
    }
    $animalStmt->close();

    $appointmentExistsStmt = $conn->prepare('SELECT id FROM pet_atendimentos WHERE motivo = ? LIMIT 1');
    $appointmentSql = "INSERT INTO pet_atendimentos
        (animal_id, tipo, status, inicio_em, fim_em, motivo, anamnese, exame_clinico, peso_kg,
         temperatura_c, frequencia_cardiaca, frequencia_respiratoria, diagnostico, conduta, prescricao)
        VALUES (?, 'consulta', 'concluido', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $appointmentStmt = $conn->prepare($appointmentSql);
    $appointmentIds = [];

    for ($index = 0; $index < 50; $index++) {
        $number = $index + 1;
        $animalId = $animalIds[$index];
        $month = 5 + intdiv($index, 25);
        $day = 1 + ($index % 25);
        $hour = 8 + ($index % 9);
        $start = sprintf('2026-%02d-%02d %02d:00:00', $month, $day, $hour);
        $end = sprintf('2026-%02d-%02d %02d:45:00', $month, $day, $hour);
        $reason = PET_DEMO_MARKER . ' Atendimento ' . str_pad((string) $number, 3, '0', STR_PAD_LEFT);

        $appointmentExistsStmt->bind_param('s', $reason);
        $appointmentExistsStmt->execute();
        $existingResult = $appointmentExistsStmt->get_result();
        $existingRow = $existingResult->fetch_assoc();
        if ($existingRow) {
            $appointmentIds[] = (int) $existingRow['id'];
            continue;
        }

        $anamnesis = 'Tutor relata acompanhamento preventivo e boa ingestao de agua e alimento.';
        $clinicalExam = 'Paciente alerta, hidratado e com parametros vitais dentro da faixa esperada.';
        $examWeight = $animalWeights[$index];
        $temperature = 38.0 + (($index % 8) / 10);
        $heartRate = 80 + ($index % 45);
        $respiratoryRate = 18 + ($index % 15);
        $diagnosis = $index % 5 === 0 ? 'Dermatite leve em acompanhamento.' : 'Paciente clinicamente estavel.';
        $conduct = 'Orientacoes preventivas, controle de peso e retorno programado.';
        $prescription = $index % 5 === 0 ? 'Higienizacao topica conforme orientacao veterinaria.' : 'Sem medicacao no momento.';

        $appointmentStmt->bind_param(
            'isssssddiisss',
            $animalId,
            $start,
            $end,
            $reason,
            $anamnesis,
            $clinicalExam,
            $examWeight,
            $temperature,
            $heartRate,
            $respiratoryRate,
            $diagnosis,
            $conduct,
            $prescription
        );
        $appointmentStmt->execute();
        $appointmentIds[] = (int) $conn->insert_id;
    }
    $appointmentExistsStmt->close();
    $appointmentStmt->close();

    $hospitalExistsStmt = $conn->prepare('SELECT id FROM pet_internacoes WHERE motivo = ? LIMIT 1');
    $hospitalSql = "INSERT INTO pet_internacoes
        (animal_id, atendimento_origem_id, status, entrada_em, previsao_alta_em, setor, leito,
         classificacao_risco, motivo, diagnostico_inicial, plano_cuidados)
        VALUES (?, ?, 'ativa', ?, ?, ?, ?, 'moderado', ?, ?, ?)";
    $hospitalStmt = $conn->prepare($hospitalSql);

    for ($index = 0; $index < 2; $index++) {
        $number = $index + 1;
        $animalId = $animalIds[$index];
        $appointmentId = $appointmentIds[$index] ?? null;
        $hospitalReason = PET_DEMO_MARKER . ' Internacao ' . str_pad((string) $number, 3, '0', STR_PAD_LEFT);

        $hospitalExistsStmt->bind_param('s', $hospitalReason);
        $hospitalExistsStmt->execute();
        if ($hospitalExistsStmt->get_result()->fetch_assoc()) {
            continue;
        }

        $entry = sprintf('2026-07-%02d 09:30:00', 25 + $index);
        $expectedDischarge = sprintf('2026-07-%02d 16:00:00', 27 + $index);
        $sector = 'Clinica medica';
        $bed = 'INT-' . str_pad((string) $number, 2, '0', STR_PAD_LEFT);
        $initialDiagnosis = $index === 0 ? 'Gastroenterite com desidratacao moderada.' : 'Observacao pos-operatoria.';
        $carePlan = 'Monitoramento de sinais vitais, hidratacao, analgesia e reavaliacao a cada turno.';

        $hospitalStmt->bind_param(
            'iisssssss',
            $animalId,
            $appointmentId,
            $entry,
            $expectedDischarge,
            $sector,
            $bed,
            $hospitalReason,
            $initialDiagnosis,
            $carePlan
        );
        $hospitalStmt->execute();
    }
    $hospitalExistsStmt->close();
    $hospitalStmt->close();

    $demoTotals = pet_demo_totals($conn);
    $expectedTotals = ['tutores' => 100, 'animais' => 200, 'atendimentos' => 50, 'internacoes' => 2];
    if ($demoTotals !== $expectedTotals) {
        throw new RuntimeException('A carga demonstrativa nao atingiu os totais esperados.');
    }

    $auditDetails = json_encode(
        ['versao' => PET_DEMO_VERSION, 'totais_demo' => $demoTotals],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    $auditStmt = $conn->prepare(
        "INSERT INTO pet_audit_log (usuario_id, acao, entidade, detalhes_json, ip)
         VALUES (NULL, 'provisionar_demo', 'modulo_pet', ?, '')"
    );
    $auditStmt->bind_param('s', $auditDetails);
    $auditStmt->execute();
    $auditStmt->close();

    $conn->commit();
    $transactionStarted = false;

    pet_demo_response(200, [
        'ok' => true,
        'versao' => PET_DEMO_VERSION,
        'modo' => 'idempotente_sem_exclusao',
        'demo' => $demoTotals,
        'totais_banco' => pet_global_totals($conn),
    ]);
} catch (Throwable $exception) {
    if ($transactionStarted && $conn instanceof mysqli) {
        $conn->rollback();
    }
    error_log('[PET DEMO] ' . $exception->getMessage());
    pet_demo_response(500, [
        'ok' => false,
        'codigo' => 'CARGA_DEMO_FALHOU',
        'erro_tipo' => get_class($exception),
        'erro_codigo' => (int) $exception->getCode(),
    ]);
}
