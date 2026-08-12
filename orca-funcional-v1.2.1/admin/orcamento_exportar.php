<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/app.php';
requireAdmin();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    redirect(APP_URL . '/admin/orcamentos.php');
}

$db = getDB();
$statement = $db->prepare(
    'SELECT o.titulo, ob.nome AS obra, c.razao_social FROM orcamentos o '
    . 'JOIN obras ob ON ob.id = o.obra_id JOIN clientes c ON c.id = o.cliente_id WHERE o.id = ?'
);
$statement->execute([$id]);
$orcamento = $statement->fetch();
if (!$orcamento) {
    redirect(APP_URL . '/admin/orcamentos.php');
}

$itemsStatement = $db->prepare(
    'SELECT oi.obs AS codigo, oi.descricao, oi.unidade, oi.quantidade, oi.preco_unitario, '
    . 'oi.preco_total, oi.preco_cotado, oi.total_cotado, cat.nome AS categoria '
    . 'FROM orcamento_itens oi LEFT JOIN categorias cat ON cat.id = oi.categoria_id '
    . 'WHERE oi.orcamento_id = ? ORDER BY oi.id'
);
$itemsStatement->execute([$id]);

$safeTitle = preg_replace('/[^a-z0-9_-]+/i', '-', (string) $orcamento['titulo']) ?: 'orcamento';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . trim($safeTitle, '-') . '.csv"');
header('Cache-Control: no-store');

$output = fopen('php://output', 'wb');
fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, ['Orçamento', $orcamento['titulo']], ';');
fputcsv($output, ['Obra', $orcamento['obra']], ';');
fputcsv($output, ['Cliente', $orcamento['razao_social']], ';');
fputcsv($output, [], ';');
fputcsv($output, ['Código', 'Descrição', 'Categoria', 'Unidade', 'Quantidade', 'Preço unitário', 'Total estimado', 'Preço cotado', 'Total cotado'], ';');
foreach ($itemsStatement->fetchAll() as $item) {
    fputcsv($output, [
        $item['codigo'], $item['descricao'], $item['categoria'], $item['unidade'],
        number_format((float) $item['quantidade'], 3, ',', ''),
        number_format((float) $item['preco_unitario'], 2, ',', ''),
        number_format((float) $item['preco_total'], 2, ',', ''),
        $item['preco_cotado'] === null ? '' : number_format((float) $item['preco_cotado'], 2, ',', ''),
        number_format((float) $item['total_cotado'], 2, ',', ''),
    ], ';');
}
fclose($output);
exit;
