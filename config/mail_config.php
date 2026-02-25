<?php
// config/mail_config.php
// PHPMailer configuration for sending emails

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load PHPMailer (adjust path as needed)
require_once __DIR__ . '/../vendor/autoload.php';

class MailConfig {
    private static $instance = null;
    private $mail;
    
    private function __construct() {
        $this->mail = new PHPMailer(true);
        $this->configure();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->mail;
    }
    
    private function configure() {
        // Server settings
        $this->mail->SMTPDebug = 0; // Set to 2 for debugging
        $this->mail->isSMTP();
        $this->mail->Host       = 'smtp.gmail.com';
        $this->mail->SMTPAuth   = true;
        
        // !!! IMPORTANT: Replace with your actual Gmail credentials !!!
        // Use App Password, not your regular Gmail password
        $this->mail->Username   = 'stephenviray12@gmail.com';  // Your Gmail
        $this->mail->Password   = 'bubr nckn tgqf lvus';       // Your Gmail App Password
        
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port       = 587;
        
        // Default sender
        $this->mail->setFrom('hr@freightmanagement.com', 'Freight Management HR');
        $this->mail->addReplyTo('hr@freightmanagement.com', 'HR Department');
        
        // Default settings
        $this->mail->isHTML(true);
        $this->mail->CharSet = 'UTF-8';
    }
}

/**
 * Send interview email notification to applicant
 * 
 * @param string $applicant_email Applicant's email address
 * @param string $applicant_name Applicant's full name
 * @param array $interview_data Interview details
 * @param int $interview_id Interview ID for calendar attachment
 * @return array Result with success status and message
 */
function sendInterviewEmail($applicant_email, $applicant_name, $interview_data, $interview_id = null) {
    try {
        $mail = MailConfig::getInstance();
        
        // Clear previous recipients and attachments
        $mail->clearAddresses();
        $mail->clearAttachments();
        $mail->clearCustomHeaders();
        
        // Recipient
        $mail->addAddress($applicant_email, $applicant_name);
        
        // Add BCC to HR
        $mail->addBCC('hr@freightmanagement.com', 'HR Department');
        
        // Subject
        $mail->Subject = "📅 Interview Schedule: {$interview_data['position']} - Freight Management";
        
        // Generate meeting link if not provided and online interview
        $meeting_link = $interview_data['meeting_link'] ?? '';
        if ($interview_data['interview_type'] === 'Online' && empty($meeting_link)) {
            $meeting_link = generateMeetingLink($interview_data['interview_round']);
        }
        
        // Store the meeting link for later use
        $interview_data['meeting_link'] = $meeting_link;
        
        // Email body with modern design
        $body = buildInterviewEmailHTML($applicant_name, $interview_data);
        
        $mail->Body = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '</p>', '</div>'], ["\n", "\n\n", "\n"], $body));
        
        // Generate and attach calendar invite
        if ($interview_id) {
            $ics_content = generateCalendarInvite($interview_data, $applicant_email, $interview_id);
            
            // Create temp file for calendar invite
            $temp_file = sys_get_temp_dir() . '/interview_' . $interview_id . '_' . time() . '.ics';
            file_put_contents($temp_file, $ics_content);
            $mail->addAttachment($temp_file, 'interview_invite.ics', 'base64', 'text/calendar');
        }
        
        // Send email
        $mail->send();
        
        // Clean up temp file
        if (isset($temp_file) && file_exists($temp_file)) {
            unlink($temp_file);
        }
        
        return ['success' => true, 'message' => 'Email sent successfully', 'meeting_link' => $meeting_link];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => "Email could not be sent. Error: {$mail->ErrorInfo}"];
    }
}

/**
 * Build HTML email template for interview invitation
 */
function buildInterviewEmailHTML($applicant_name, $data) {
    $interview_date = new DateTime($data['interview_date'] . ' ' . $data['interview_time']);
    $formatted_date = $interview_date->format('F d, Y');
    $formatted_time = $interview_date->format('h:i A');
    $formatted_day = $interview_date->format('l');
    
    $meeting_html = '';
    if ($data['interview_type'] === 'Online' && !empty($data['meeting_link'])) {
        $meeting_html = '
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 15px; padding: 25px; margin: 25px 0; text-align: center;">
            <div style="margin-bottom: 15px;">
                <span style="background: rgba(255,255,255,0.2); padding: 8px 16px; border-radius: 30px; color: white; font-size: 14px; font-weight: 500;">
                    <i class="fas fa-video" style="margin-right: 5px;"></i> Virtual Interview
                </span>
            </div>
            <h3 style="color: white; margin: 10px 0 15px; font-size: 20px;">🔗 Your Meeting Link</h3>
            <a href="' . $data['meeting_link'] . '" target="_blank" style="background: white; color: #667eea; padding: 15px 30px; border-radius: 50px; text-decoration: none; font-weight: 600; display: inline-block; margin: 10px 0; box-shadow: 0 10px 20px rgba(0,0,0,0.2);">
                <i class="fas fa-door-open"></i> Click to Join Interview
            </a>
            <p style="color: rgba(255,255,255,0.9); margin-top: 15px; font-size: 13px;">
                <i class="fas fa-link"></i> Link: ' . $data['meeting_link'] . '
            </p>
        </div>';
    } else {
        $meeting_html = '
        <div style="background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%); border-radius: 15px; padding: 25px; margin: 25px 0; text-align: center;">
            <h3 style="color: white; margin: 0 0 15px; font-size: 20px;">📍 Location</h3>
            <p style="color: white; font-size: 16px; line-height: 1.6; margin: 0;">
                <strong>' . ($data['location'] ?? 'Main Office') . '</strong>
            </p>
            <p style="color: rgba(255,255,255,0.8); margin-top: 10px; font-size: 14px;">
                <i class="fas fa-clock"></i> Please arrive 15 minutes before your schedule
            </p>
        </div>';
    }
    
    $panel_html = '';
    if (!empty($data['interview_panel'])) {
        $panel_html = '
        <div style="margin: 20px 0;">
            <p style="font-size: 14px; color: #64748b; margin-bottom: 5px;">
                <i class="fas fa-users" style="color: #667eea;"></i> Interview Panel:
            </p>
            <p style="font-size: 15px; font-weight: 500; color: #2c3e50;">' . $data['interview_panel'] . '</p>
        </div>';
    }
    
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            @import url("https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap");
            
            body {
                font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                line-height: 1.6;
                margin: 0;
                padding: 0;
                background: linear-gradient(135deg, #f5f7fa 0%, #e9edf5 100%);
            }
            
            .container {
                max-width: 600px;
                margin: 20px auto;
                background: white;
                border-radius: 30px;
                overflow: hidden;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            }
            
            .header {
                background: linear-gradient(135deg, #0e4c92 0%, #2a6eb0 100%);
                padding: 40px 30px;
                text-align: center;
                position: relative;
            }
            
            .header::before {
                content: "";
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: url("data:image/svg+xml,...") center/cover;
                opacity: 0.1;
            }
            
            .header h1 {
                margin: 20px 0 10px;
                font-size: 32px;
                font-weight: 700;
                color: white;
                letter-spacing: -0.5px;
            }
            
            .header p {
                color: rgba(255, 255, 255, 0.9);
                font-size: 16px;
                margin: 0;
            }
            
            .header-icon {
                width: 80px;
                height: 80px;
                background: rgba(255, 255, 255, 0.2);
                border-radius: 30px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto;
                backdrop-filter: blur(10px);
                border: 2px solid rgba(255, 255, 255, 0.3);
            }
            
            .header-icon i {
                font-size: 40px;
                color: white;
            }
            
            .content {
                padding: 40px 30px;
            }
            
            .greeting {
                margin-bottom: 30px;
            }
            
            .greeting h2 {
                font-size: 24px;
                font-weight: 600;
                color: #2c3e50;
                margin: 0 0 5px;
            }
            
            .greeting p {
                color: #64748b;
                font-size: 16px;
                margin: 0;
            }
            
            .details-card {
                background: #f8fafd;
                border-radius: 20px;
                padding: 25px;
                margin: 25px 0;
                border: 1px solid #eef2f6;
            }
            
            .detail-row {
                display: flex;
                align-items: flex-start;
                margin-bottom: 15px;
                padding-bottom: 15px;
                border-bottom: 1px dashed #e2e8f0;
            }
            
            .detail-row:last-child {
                margin-bottom: 0;
                padding-bottom: 0;
                border-bottom: none;
            }
            
            .detail-icon {
                width: 40px;
                height: 40px;
                background: white;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-right: 15px;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            }
            
            .detail-icon i {
                font-size: 20px;
                color: #0e4c92;
            }
            
            .detail-content {
                flex: 1;
            }
            
            .detail-label {
                font-size: 12px;
                font-weight: 600;
                color: #64748b;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin-bottom: 2px;
            }
            
            .detail-value {
                font-size: 16px;
                font-weight: 600;
                color: #2c3e50;
            }
            
            .badge {
                display: inline-block;
                padding: 5px 12px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border-radius: 30px;
                font-size: 13px;
                font-weight: 500;
            }
            
            .preparation-list {
                background: white;
                border-radius: 15px;
                padding: 20px;
                margin: 25px 0;
                border: 2px solid #eef2f6;
            }
            
            .preparation-list h4 {
                font-size: 16px;
                font-weight: 600;
                color: #2c3e50;
                margin: 0 0 15px;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            
            .preparation-list h4 i {
                color: #27ae60;
            }
            
            .preparation-list ul {
                margin: 0;
                padding-left: 20px;
            }
            
            .preparation-list li {
                color: #64748b;
                font-size: 14px;
                margin-bottom: 8px;
            }
            
            .footer {
                background: #f8fafd;
                padding: 30px;
                text-align: center;
                border-top: 1px solid #eef2f6;
            }
            
            .footer p {
                color: #64748b;
                font-size: 14px;
                margin: 5px 0;
            }
            
            .footer-logo {
                margin-bottom: 15px;
            }
            
            .footer-logo i {
                font-size: 30px;
                color: #0e4c92;
            }
            
            .btn {
                display: inline-block;
                padding: 12px 30px;
                border-radius: 30px;
                text-decoration: none;
                font-weight: 600;
                font-size: 14px;
                transition: all 0.3s;
            }
            
            .btn-primary {
                background: linear-gradient(135deg, #0e4c92 0%, #2a6eb0 100%);
                color: white;
                box-shadow: 0 10px 20px rgba(14, 76, 146, 0.2);
            }
            
            .btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 15px 30px rgba(14, 76, 146, 0.3);
            }
            
            .btn-outline {
                border: 2px solid #0e4c92;
                color: #0e4c92;
                background: white;
            }
            
            .btn-outline:hover {
                background: #0e4c92;
                color: white;
            }
            
            .meeting-link-box {
                background: #e8f4fd;
                border-radius: 12px;
                padding: 15px;
                margin: 15px 0;
                display: flex;
                align-items: center;
                gap: 10px;
                border: 1px solid #0e4c92;
            }
            
            .meeting-link-box input {
                flex: 1;
                border: none;
                background: transparent;
                color: #0e4c92;
                font-size: 13px;
                padding: 5px;
                outline: none;
            }
            
            .copy-btn {
                background: white;
                border: none;
                width: 35px;
                height: 35px;
                border-radius: 8px;
                color: #0e4c92;
                cursor: pointer;
                transition: all 0.3s;
            }
            
            .copy-btn:hover {
                background: #0e4c92;
                color: white;
            }
            
            @media (max-width: 600px) {
                .container {
                    margin: 10px;
                    border-radius: 20px;
                }
                
                .header {
                    padding: 30px 20px;
                }
                
                .content {
                    padding: 30px 20px;
                }
                
                .detail-row {
                    flex-direction: column;
                    align-items: flex-start;
                }
                
                .detail-icon {
                    margin-bottom: 10px;
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <div class="header-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h1>Interview Invitation</h1>
                <p>You\'re one step closer to joining our team!</p>
            </div>
            
            <div class="content">
                <div class="greeting">
                    <h2>Dear ' . htmlspecialchars($applicant_name) . ',</h2>
                    <p>We were impressed by your application and would like to invite you for an interview.</p>
                </div>
                
                <div class="details-card">
                    <div class="detail-row">
                        <div class="detail-icon">
                            <i class="fas fa-briefcase"></i>
                        </div>
                        <div class="detail-content">
                            <div class="detail-label">Position</div>
                            <div class="detail-value">' . htmlspecialchars($data['position']) . '</div>
                        </div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="detail-content">
                            <div class="detail-label">Date</div>
                            <div class="detail-value">' . $formatted_date . ' (' . $formatted_day . ')</div>
                        </div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="detail-content">
                            <div class="detail-label">Time</div>
                            <div class="detail-value">' . $formatted_time . '</div>
                        </div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-icon">
                            <i class="fas fa-comment-dots"></i>
                        </div>
                        <div class="detail-content">
                            <div class="detail-label">Interview Round</div>
                            <div class="detail-value">
                                <span class="badge">' . $data['interview_round'] . '</span>
                            </div>
                        </div>
                    </div>
                    
                    ' . ($panel_html) . '
                </div>
                
                ' . $meeting_html . '
                
                <div class="preparation-list">
                    <h4><i class="fas fa-clipboard-check"></i> What to Prepare:</h4>
                    <ul>
                        <li>✅ Valid government-issued ID</li>
                        <li>📄 Updated resume/CV (2 copies)</li>
                        <li>🎓 Certificates of employment (if any)</li>
                        <li>🏆 Portfolio (if applicable)</li>
                        <li>📝 List of professional references</li>
                    </ul>
                </div>
                
                <div style="text-align: center; margin: 30px 0 20px;">
                    <a href="mailto:hr@freightmanagement.com?subject=Interview%20Inquiry" class="btn btn-outline" style="margin: 0 5px;">
                        <i class="fas fa-question-circle"></i> Need Help?
                    </a>
                </div>
            </div>
            
            <div class="footer">
                <div class="footer-logo">
                    <i class="fas fa-truck"></i>
                </div>
                <p><strong>Freight Management Inc.</strong></p>
                <p>123 Business Avenue, Quezon City</p>
                <p>📞 (02) 1234-5678 | ✉️ hr@freightmanagement.com</p>
                <p style="margin-top: 20px; font-size: 12px;">
                    This is an automated message. Please do not reply directly to this email.<br>
                    © ' . date('Y') . ' Freight Management Inc. All rights reserved.
                </p>
            </div>
        </div>
        
        <script>
            function copyToClipboard(text) {
                navigator.clipboard.writeText(text).then(function() {
                    alert("Meeting link copied to clipboard!");
                });
            }
        </script>
    </body>
    </html>';
}

/**
 * Generate Google Meet link
 */
function generateMeetingLink($interview_round) {
    $prefixes = [
        'initial' => 'meet',
        'technical' => 'tech',
        'hr' => 'hr',
        'final' => 'final'
    ];
    
    $prefix = $prefixes[strtolower(str_replace(' ', '_', $interview_round))] ?? 'meet';
    $unique_id = bin2hex(random_bytes(4));
    
    return "https://meet.google.com/{$prefix}-{$unique_id}";
}

/**
 * Generate Zoom-like link
 */
function generateZoomLink() {
    $meeting_id = random_int(100000000, 999999999);
    $password = strtoupper(substr(md5(uniqid()), 0, 6));
    
    return "https://zoom.us/j/{$meeting_id}?pwd={$password}";
}

/**
 * Generate calendar invite (ICS file)
 */
function generateCalendarInvite($data, $applicant_email, $interview_id) {
    $start = strtotime($data['interview_date'] . ' ' . $data['interview_time']);
    $end = $start + 3600; // 1 hour interview
    
    $dtstart = gmdate('Ymd\THis\Z', $start);
    $dtend = gmdate('Ymd\THis\Z', $end);
    $dtstamp = gmdate('Ymd\THis\Z');
    
    $location = $data['interview_type'] === 'Online' 
        ? ($data['meeting_link'] ?? 'Online Interview') 
        : ($data['location'] ?? 'Main Office');
    
    $description = "Interview for {$data['position']} position.\n";
    $description .= "Interview Round: {$data['interview_round']}\n";
    $description .= "Panel: {$data['interview_panel']}\n\n";
    $description .= "Please come prepared with your resume and valid ID.\n";
    
    if ($data['interview_type'] === 'Online' && !empty($data['meeting_link'])) {
        $description .= "\nMeeting Link: {$data['meeting_link']}";
    }
    
    $uid = $interview_id . '-' . uniqid() . '@freightmanagement.com';
    
    return "BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Freight Management//HR System//EN
CALSCALE:GREGORIAN
METHOD:REQUEST
BEGIN:VEVENT
UID:{$uid}
DTSTAMP:{$dtstamp}
DTSTART:{$dtstart}
DTEND:{$dtend}
SUMMARY:Interview: {$data['position']}
DESCRIPTION:" . str_replace("\n", "\\n", $description) . "
LOCATION:" . str_replace(',', '\,', $location) . "
ORGANIZER;CN=HR Department:mailto:hr@freightmanagement.com
ATTENDEE;CUTYPE=INDIVIDUAL;ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;CN=" . $data['applicant_name'] . ":mailto:{$applicant_email}
CLASS:PUBLIC
STATUS:CONFIRMED
SEQUENCE:0
BEGIN:VALARM
TRIGGER:-PT15M
ACTION:DISPLAY
DESCRIPTION:Reminder: Interview in 15 minutes
END:VALARM
END:VEVENT
END:VCALENDAR";
}
?>