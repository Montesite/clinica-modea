<?php
// Recebe o formulário comercial do IEAPM e repassa por e-mail.
// Não grava nada em disco/banco de dados: apenas envia e responde.

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/phpmailer/Exception.php';
require __DIR__ . '/phpmailer/PHPMailer.php';
require __DIR__ . '/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

function responder($sucesso, $mensagem) {
    echo json_encode(['sucesso' => $sucesso, 'mensagem' => $mensagem]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    responder(false, 'Método não permitido.');
}

// Honeypot: campo invisível que só bots preenchem.
if (!empty($_POST['website'])) {
    responder(true, 'Solicitação enviada com sucesso.');
}

$nome          = trim($_POST['nome'] ?? '');
$cargo         = trim($_POST['cargo'] ?? '');
$empresa       = trim($_POST['empresa'] ?? '');
$cnpj          = trim($_POST['cnpj'] ?? '');
$telefone      = trim($_POST['telefone'] ?? '');
$email         = trim($_POST['email'] ?? '');
$colaboradores = trim($_POST['colaboradores'] ?? '');
$unidades      = trim($_POST['unidades'] ?? '');
$cidades       = trim($_POST['cidades'] ?? '');
$interesse     = trim($_POST['interesse'] ?? '');
$mensagem      = trim($_POST['mensagem'] ?? '');
$consentimento = !empty($_POST['consentimento']);

$interesseLabels = [
    'pericias'             => 'Perícias Médicas',
    'gestao-ocupacional'   => 'Gestão Ocupacional',
    'ergonomia'            => 'Ergonomia',
    'e-ocupacional'        => 'E Ocupacional',
    'atendimento-nacional' => 'Atendimento Nacional',
    'solucao-integrada'    => 'Solução Integrada',
    'orientacao'           => 'Preciso de orientação',
];

if ($nome === '' || $empresa === '' || $telefone === '' || $email === '' || $interesse === '') {
    http_response_code(422);
    responder(false, 'Preencha todos os campos obrigatórios.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    responder(false, 'E-mail inválido.');
}

if (!$consentimento) {
    http_response_code(422);
    responder(false, 'É necessário autorizar o tratamento dos dados (LGPD).');
}

$config = require __DIR__ . '/config.php';

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = $config['smtp_host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $config['smtp_user'];
    $mail->Password   = $config['smtp_pass'];
    $mail->SMTPSecure = $config['smtp_secure'];
    $mail->Port       = $config['smtp_port'];
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom($config['from_email'], $config['from_name']);
    $mail->addAddress($config['to_email'], $config['to_name']);
    $mail->addReplyTo($email, $nome);

    $mail->Subject = 'Solicitação de proposta IEAPM - ' . $nome . ' (' . $empresa . ')';
    $mail->Body =
        "Nova solicitação de proposta recebida pelo site do IEAPM:\n\n" .
        "Nome: {$nome}\n" .
        "Cargo: " . ($cargo !== '' ? $cargo : '(não informado)') . "\n" .
        "Empresa: {$empresa}\n" .
        "CNPJ: " . ($cnpj !== '' ? $cnpj : '(não informado)') . "\n" .
        "Telefone/WhatsApp: {$telefone}\n" .
        "E-mail: {$email}\n" .
        "Número de colaboradores: " . ($colaboradores !== '' ? $colaboradores : '(não informado)') . "\n" .
        "Quantidade de unidades: " . ($unidades !== '' ? $unidades : '(não informado)') . "\n" .
        "Cidades/Estados de atuação: " . ($cidades !== '' ? $cidades : '(não informado)') . "\n" .
        "Interesse: " . ($interesseLabels[$interesse] ?? $interesse) . "\n\n" .
        "Mensagem:\n" . ($mensagem !== '' ? $mensagem : '(não informado)') . "\n";

    $mail->send();

    responder(true, 'Solicitação enviada com sucesso! Nosso time vai analisar as informações e retornar em breve.');
} catch (PHPMailerException $e) {
    http_response_code(500);
    responder(false, 'Não foi possível enviar sua solicitação no momento. Tente novamente mais tarde.');
}
