<?php

namespace Framework;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

class Mail
{
    /**
     * Initializing sendmail protocol
     * sendmail protocol used by linux operating system
     * @return configured PHPMailer
     */
    public static function sp()
    {
        $smtp_config = Config::get_config()["smtp"];
        $mail = new PHPMailer();
		$mail->isSendmail();                             // send email used by Sendmail
		$mail->SMTPAuth = $smtp_config["auth"];      
		$mail->Username = $smtp_config["username"];
		$mail->Password = $smtp_config["password"];
		$mail->setFrom($smtp_config["from"], $smtp_config["alias"]);
        $mail->CharSet = $smtp_config["charset"];
		return $mail;
    }

    /**
     * Initializing SMTP protocol
     * @return configured PHPMailer
     */
    public static function smtp()
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP(); // Set mailer to use SMTP
        $smtp_config = Config::get_config()["smtp"];
        //Server settings
        $mail->SMTPDebug = $smtp_config["debug"];
        $mail->Host = $smtp_config["host"];
        $mail->SMTPAuth = $smtp_config["auth"]; 
        $mail->Username = $smtp_config["username"];
        $mail->Password = $smtp_config["password"];
        $mail->SMTPSecure = $smtp_config["secure"];
        $mail->Port = $smtp_config["port"];

        //Char set
        $mail->CharSet = $smtp_config["charset"];

        //Mail alias
        $mail->setFrom($smtp_config["from"], $smtp_config["alias"]);
        return $mail;
    }

    /**
     * Send a message
     * @param string $to Recipient
     * @param string $subject Subject
     * @param string $text Text-only content of the message
     * @param string $html HTML content of the message
     */
    public static function sendHtmlMessage($to, $subject, $html): bool | string
    {
        $smtp_config = Config::get_config()["smtp"];
        if ($smtp_config["enabled"]) {
            if ($smtp_config['method'] == 0){
                $mail = Mail::smtp();
            } else {
                $mail = Mail::sp();
            }
            
            try {
                $to_arr = explode(',', $to);
                foreach($to_arr as $key => $value){
                    $mail->addAddress($value); // Add a recipient    
                }                
                //$mail->addReplyTo('info@example.com', 'Information');
                //$mail->addCC('cc@example.com');
                //$mail->addBCC('bcc@example.com');

                $mail->isHTML(true); // Set email format to HTML
                $mail->Subject = $subject;
                $mail->Body = $html;

                $mail->send();
                return true;
            } catch (Exception $e) {
                if ($smtp_config["debug"] > 0) {
                    echo 'Message could not be sent. Mailer Error: ', $mail->ErrorInfo;
                }
                return $mail->ErrorInfo;
            }
        }
    }
    /**
     * Send a message
     *
     * @param string $to Recipient
     * @param string $subject Subject
     * @param string $text Text-only content of the message
     * @param string $html HTML content of the message
     *
     * @return mixed
     */
    public static function send($to, $subject, $text, $html)
    {
        $smtp_config = Config::get_config()["smtp"];
        if ($smtp_config["enabled"]) {
            if ($smtp_config['method'] == 0){
                $mail = Mail::smtp();
            } else {
                $mail = Mail::sp();
            }

            try {
                $to_arr = explode(',', $to);
                foreach($to_arr as $key => $value){
                    $mail->addAddress($value); // Add a recipient    
                }
                //$mail->addReplyTo('info@example.com', 'Information');
                //$mail->addCC('cc@example.com');
                //$mail->addBCC('bcc@example.com');

                $mail->isHTML(true); // Set email format to HTML
                $mail->Subject = $subject;
                $mail->Body = $html;
                $mail->AltBody = $text;

                $mail->send();
                if ($smtp_config["enabled"] > 0) {
                    echo 'Message has been sent';
                }
            } catch (Exception $e) {
                if ($smtp_config["enabled"] > 0) {
                    echo 'Message could not be sent. Mailer Error: ', $mail->ErrorInfo;
                }
            }
        }
    }

}
