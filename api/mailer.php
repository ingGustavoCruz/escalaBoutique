<?php
/**
 * api/mailer.php
 * Configuración de PHPMailer para OUTLOOK / OFFICE 365
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

function enviarCorreo($destinatario, $asunto, $cuerpoHTML) {
    $mail = new PHPMailer(true);

    try {
        // --- CONFIGURACIÓN PARA OUTLOOK / OFFICE 365 ---
        $mail->isSMTP();
        $mail->Host       = 'smtp.office365.com'; 
        $mail->SMTPAuth   = true;
        $mail->Username   = 'gustavo.cruz@escala-inc.com';      // <--- TU CORREO COMPLETO
        $mail->Password   = '22Alambr3$';        // <--- TU PASS (o App Password)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // --- REMITENTE ---
        // En Outlook es OBLIGATORIO que el 'From' sea igual al 'Username' de arriba
        $mail->setFrom('gustavo.cruz@escala-inc.com', 'Escala Boutique');
        
        $mail->addAddress($destinatario);

        // --- CONTENIDO ---
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $asunto;
        $mail->Body    = $cuerpoHTML;

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Guardar error en log para revisar si falla
        file_put_contents('log_mail_error.txt', date('Y-m-d H:i:s') . " - Error: {$mail->ErrorInfo}\n", FILE_APPEND);
        return false;
    }
}
?>