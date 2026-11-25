<?php
// Recebe o formulário do site e envia por e-mail.
// Os destinatários ficam em contato-config.php, somente no servidor.
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function responder(int $codigo, bool $ok): void
{
    http_response_code($codigo);
    echo json_encode(['ok' => $ok]);
    exit;
}

function campo(string $nome, int $limite, bool $linhas = false): string
{
    $v = $_POST[$nome] ?? '';
    if (!is_string($v) || strlen($v) > $limite * 4 || preg_match('//u', $v) !== 1) {
        return '';
    }
    $v = str_replace(["\r\n", "\r"], "\n", $v);
    $v = preg_replace($linhas ? '/[^\P{C}\n]/u' : '/\p{C}+/u', ' ', $v) ?? '';
    preg_match('/^.{0,' . $limite . '}/us', trim($v), $m);
    return $m[0] ?? '';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    responder(405, false);
}
if (($_SERVER['HTTP_ORIGIN'] ?? '') !== 'https://maf3tech.com.br') {
    responder(403, false);
}
// Campo invisível: só robôs preenchem.
if (campo('site', 200) !== '') {
    responder(200, true);
}

$nome = campo('nome', 120);
$email = campo('email', 200);
if ($nome === '' || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
    responder(422, false);
}

// Limites por hora: 5 por visitante e 60 no total.
function permitido(string $chave, int $maximo): bool
{
    $arquivo = sys_get_temp_dir() . '/maf3_' . hash('sha256', __DIR__ . $chave);
    $f = @fopen($arquivo, 'c+');
    if (!$f || !flock($f, LOCK_EX)) {
        return false;
    }
    $corte = time() - 3600;
    $itens = json_decode((string) stream_get_contents($f), true);
    $itens = array_values(array_filter(is_array($itens) ? $itens : [], fn($t) => $t > $corte));
    $ok = count($itens) < $maximo;
    if ($ok) {
        $itens[] = time();
    }
    ftruncate($f, 0);
    rewind($f);
    fwrite($f, json_encode($itens));
    fclose($f);
    return $ok;
}
if (!permitido($_SERVER['REMOTE_ADDR'] ?? '', 5) || !permitido('total', 60)) {
    responder(429, false);
}

$config = __DIR__ . '/contato-config.php';
$destinos = is_file($config) ? require $config : null;
if (!is_array($destinos) || !$destinos) {
    responder(500, false);
}

$corpo = "Novo contato pelo formulário do site\n\n"
    . "Nome: $nome\n"
    . 'Empresa: ' . campo('empresa', 160) . "\n"
    . "E-mail: $email\n"
    . 'Solução de interesse: ' . campo('solucao', 120) . "\n"
    . "Mensagem:\n" . campo('mensagem', 4000, true) . "\n\n"
    . "O visitante também foi direcionado ao WhatsApp.\n";

$assunto = '=?UTF-8?B?' . base64_encode('Novo contato pelo site') . '?=';
$cabecalhos = [
    'From' => 'MAF3 Tech <falecom@maf3tech.com.br>',
    'MIME-Version' => '1.0',
    'Content-Type' => 'text/plain; charset=utf-8',
    'Content-Transfer-Encoding' => 'base64',
];
if ($email !== '') {
    $cabecalhos['Reply-To'] = $email;
}

$ok = true;
foreach ($destinos as $destino) {
    $ok = mail($destino, $assunto, chunk_split(base64_encode($corpo)), $cabecalhos, '-ffalecom@maf3tech.com.br') && $ok;
}
responder($ok ? 200 : 500, $ok);
