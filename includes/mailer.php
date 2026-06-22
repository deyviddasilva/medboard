<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function enviar_email_recuperacao(string $email_destino, string $nome, string $link): bool {
    $mail = new PHPMailer(true);

    try {
        // configurações do servidor SMTP
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        // remetente e destinatário
        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mail->addAddress($email_destino, $nome);

        // conteúdo
        $mail->isHTML(true);
        $mail->Subject = 'MedBoard — Recuperação de senha';
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; max-width: 480px; margin: 0 auto; padding: 24px;'>
                <h2 style='color: #2A9D8F;'>🩺 MedBoard</h2>
                <p>Olá, <strong>{$nome}</strong>!</p>
                <p>Recebemos uma solicitação para redefinir sua senha.</p>
                <p>Clique no botão abaixo para criar uma nova senha. Este link expira em <strong>10 minutos</strong>.</p>
                <p style='margin: 24px 0;'>
                    <a href='{$link}' 
                       style='background: #2A9D8F; color: #fff; padding: 12px 24px; 
                              border-radius: 8px; text-decoration: none; font-weight: 600;'>
                        Redefinir minha senha
                    </a>
                </p>
                <p style='color: #6D7F88; font-size: 13px;'>
                    Se você não solicitou isso, pode ignorar este e-mail com segurança.
                </p>
            </div>
        ";
        $mail->AltBody = "Olá {$nome}, acesse este link para redefinir sua senha: {$link}";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log('Erro ao enviar e-mail: ' . $mail->ErrorInfo);
        return false;
    }
}