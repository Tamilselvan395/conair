<?php

namespace App\Controllers;
use Slim\Exception\HttpNotFoundException;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class PageController
{
    private $twig;
    private $pdo;
    private $basePath;

    public function __construct($twig, $pdo, $basePath)
    {
        $this->twig = $twig;
        $this->pdo = $pdo;
        $this->basePath = $basePath;
    }

    public function contact($request, $response)
    {
        return $this->twig->render($response, 'contact.twig', [
            'title' => 'Contact Us',
            'base_url' => $this->basePath
        ]);
    }

    /* =========================
       CONTACT SUBMIT - WITH COUNTRY CODE
    ========================== */
    public function contactSubmit($request, $response)
    {
        $data = $request->getParsedBody();

        $name    = trim($data['name'] ?? '');
        $email   = trim($data['email'] ?? '');
        $country_code = trim($data['country_code'] ?? ''); // Get country code
        $phone   = trim($data['phone'] ?? '');
        $service = trim($data['service'] ?? '');
        $message = trim($data['message'] ?? '');

        // Combine country code and phone number
        $full_phone = $country_code . ' ' . $phone;
        $full_phone = trim($full_phone);

        // Save to database (add country_code and service columns)
        $stmt = $this->pdo->prepare("
            INSERT INTO contacts (name, email, country_code, phone, service, message, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $name, 
            $email, 
            $country_code, 
            $phone, 
            $service, 
            $message
        ]);

        // Send email
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'tamilselvangopi395@gmail.com';
            $mail->Password   = 'caefwgojheeaexcs'; // Gmail App Password
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;

            $mail->setFrom($email, $name);
            $mail->addAddress('saitamil395@gmail.com'); // receive email here

            $mail->isHTML(true);
            $mail->Subject = 'New Contact Form Message - ' . $service;
            
            // Build service display name
            $service_name = '';
            switch($service) {
                case 'ac-installation': $service_name = 'AC Installation'; break;
                case 'ac-repair': $service_name = 'AC Repair'; break;
                case 'waterproofing': $service_name = 'Waterproofing'; break;
                case 'painting': $service_name = 'Painting'; break;
                case 'maintenance': $service_name = 'General Maintenance'; break;
                default: $service_name = 'Other';
            }

            $mail->Body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 30px; background: #f8fafc; border-radius: 16px;'>
                    <div style='background: linear-gradient(135deg, #393186 0%, #4a3aa8 100%); padding: 30px; border-radius: 12px; margin-bottom: 30px;'>
                        <h2 style='color: white; margin: 0; font-size: 24px;'>📬 New Contact Message</h2>
                    </div>
                    
                    <div style='background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);'>
                        <table style='width: 100%; border-collapse: collapse;'>
                            <tr>
                                <td style='padding: 12px 0; color: #64748b; width: 120px;'><strong>Name:</strong></td>
                                <td style='padding: 12px 0; color: #0f172a; font-weight: 600;'>{$name}</td>
                            </tr>
                            <tr>
                                <td style='padding: 12px 0; color: #64748b;'><strong>Email:</strong></td>
                                <td style='padding: 12px 0;'><a href='mailto:{$email}' style='color: #393186; text-decoration: none;'>{$email}</a></td>
                            </tr>
                            <tr>
                                <td style='padding: 12px 0; color: #64748b;'><strong>Phone:</strong></td>
                                <td style='padding: 12px 0; color: #0f172a;'>{$full_phone}</td>
                            </tr>
                            <tr>
                                <td style='padding: 12px 0; color: #64748b;'><strong>Country Code:</strong></td>
                                <td style='padding: 12px 0; color: #0f172a;'>{$country_code}</td>
                            </tr>
                            <tr>
                                <td style='padding: 12px 0; color: #64748b;'><strong>Service:</strong></td>
                                <td style='padding: 12px 0;'><span style='background: #f0eff7; color: #393186; padding: 6px 16px; border-radius: 30px; font-size: 14px; font-weight: 600;'>{$service_name}</span></td>
                            </tr>
                            <tr>
                                <td style='padding: 12px 0; color: #64748b; vertical-align: top;'><strong>Message:</strong></td>
                                <td style='padding: 12px 0; color: #1e293b; line-height: 1.6;'>{$message}</td>
                            </tr>
                        </table>
                        
                        <div style='margin-top: 30px; padding-top: 20px; border-top: 2px solid #f1f5f9; text-align: center; color: #64748b; font-size: 14px;'>
                            <p>📅 Received: " . date('F j, Y, g:i a') . "</p>
                        </div>
                    </div>
                </div>
            ";

            $mail->AltBody = "Name: {$name}\nEmail: {$email}\nPhone: {$full_phone}\nCountry Code: {$country_code}\nService: {$service_name}\nMessage: {$message}";

            $mail->send();

        } catch (Exception $e) {
            // Optional: log error
            error_log("Email sending failed: " . $mail->ErrorInfo);
        }

        $_SESSION['success'] = "Message sent successfully! We'll get back to you within 24 hours.";

        return $response
            ->withHeader('Location', $this->basePath . '/contact')
            ->withStatus(302);
    }

    public function about($request, $response)
    {
        return $this->twig->render($response, 'about.twig', [
            'title' => 'About Us',
            'base_url' => $this->basePath
        ]);
    }

    public function services($request, $response)
    {
        return $this->twig->render($response, 'services/index.twig', [
            'title' => 'Our Services',
            'base_url' => $this->basePath
        ]);
    }

     public function serviceDetail($request, $response, $args)
    {
        $allowedServices = [
            'electrical-maintenance-installation-company-uae',
            'mechanical-mep-contractors-uae',
            'plumbing-services-dubai'
        ];

        $slug = $args['slug'];

        if (!in_array($slug, $allowedServices)) {
            throw new HttpNotFoundException($request);
        }

        return $this->twig->render($response, "services/{$slug}.twig", [
            'title' => ucwords(str_replace('-', ' ', $slug))
        ]);
    }
}