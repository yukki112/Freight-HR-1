<?php
// config/mail_config.php
// PHPMailer configuration for sending emails

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load PHPMailer (adjust path as needed)
require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Central place for the HR sending account.
 * Change it here and it updates everywhere.
 */
if (!defined('HR_MAIL_USERNAME')) define('HR_MAIL_USERNAME', 'humanresourcesmanagement88@gmail.com');
if (!defined('HR_MAIL_FROM_NAME')) define('HR_MAIL_FROM_NAME', 'Priority Handling Logistics HR');

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
        
        // Gmail credentials (must match the From address below)
        $this->mail->Username   = HR_MAIL_USERNAME;   // humanresourcesmanagement88@gmail.com
        $this->mail->Password   = 'dkzh vikl gafx tgmh';  // Gmail App Password
        
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port       = 587;
        
        // IMPORTANT: From MUST match the authenticated Gmail account.
        // Otherwise Gmail silently rewrites the From header back to the account
        // and emails look like they were sent "from you to you".
        $this->mail->setFrom(HR_MAIL_USERNAME, HR_MAIL_FROM_NAME);
        $this->mail->addReplyTo(HR_MAIL_USERNAME, HR_MAIL_FROM_NAME);
        
        // Default settings
        $this->mail->isHTML(true);
        $this->mail->CharSet = 'UTF-8';
    }
}

/**
 * Send interview email notification to applicant
 */
function sendInterviewEmail($applicant_email, $applicant_name, $interview_data, $interview_id = null) {
    try {
        $mail = MailConfig::getInstance();
        
        $mail->clearAddresses();
        $mail->clearAttachments();
        $mail->clearCustomHeaders();
        
        // Applicant receives the email
        $mail->addAddress($applicant_email, $applicant_name);
        
        // HR gets a BCC copy (use the real Gmail, not a fake domain)
        $mail->addBCC(HR_MAIL_USERNAME, 'HR Department');
        
        // Subject
        $mail->Subject = "📅 Interview Schedule: {$interview_data['position']} - Priority Handling Logistics";
        
        // Generate meeting link if not provided and online interview
        $meeting_link = $interview_data['meeting_link'] ?? '';
        if ($interview_data['interview_type'] === 'Online' && empty($meeting_link)) {
            $meeting_link = generateMeetingLink($interview_data['interview_round']);
        }
        $interview_data['meeting_link'] = $meeting_link;
        
        // Email body
        $body = buildInterviewEmailHTML($applicant_name, $interview_data);
        $mail->Body = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '</p>', '</div>'], ["\n", "\n\n", "\n"], $body));
        
        // Calendar invite
        if ($interview_id) {
            $ics_content = generateCalendarInvite($interview_data, $applicant_email, $interview_id);
            $temp_file = sys_get_temp_dir() . '/interview_' . $interview_id . '_' . time() . '.ics';
            file_put_contents($temp_file, $ics_content);
            $mail->addAttachment($temp_file, 'interview_invite.ics', 'base64', 'text/calendar');
        }
        
        $mail->send();
        
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
            body { font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; line-height: 1.6; margin: 0; padding: 0; background: linear-gradient(135deg, #f5f7fa 0%, #e9edf5 100%); }
            .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 30px; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); }
            .header { background: linear-gradient(135deg, #0e4c92 0%, #2a6eb0 100%); padding: 40px 30px; text-align: center; position: relative; }
            .header h1 { margin: 20px 0 10px; font-size: 32px; font-weight: 700; color: white; letter-spacing: -0.5px; }
            .header p { color: rgba(255, 255, 255, 0.9); font-size: 16px; margin: 0; }
            .header-icon { width: 80px; height: 80px; background: rgba(255, 255, 255, 0.2); border-radius: 30px; display: flex; align-items: center; justify-content: center; margin: 0 auto; backdrop-filter: blur(10px); border: 2px solid rgba(255, 255, 255, 0.3); }
            .header-icon i { font-size: 40px; color: white; }
            .content { padding: 40px 30px; }
            .greeting { margin-bottom: 30px; }
            .greeting h2 { font-size: 24px; font-weight: 600; color: #2c3e50; margin: 0 0 5px; }
            .greeting p { color: #64748b; font-size: 16px; margin: 0; }
            .details-card { background: #f8fafd; border-radius: 20px; padding: 25px; margin: 25px 0; border: 1px solid #eef2f6; }
            .detail-row { display: flex; align-items: flex-start; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px dashed #e2e8f0; }
            .detail-row:last-child { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }
            .detail-icon { width: 40px; height: 40px; background: white; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-right: 15px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); }
            .detail-icon i { font-size: 20px; color: #0e4c92; }
            .detail-content { flex: 1; }
            .detail-label { font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px; }
            .detail-value { font-size: 16px; font-weight: 600; color: #2c3e50; }
            .badge { display: inline-block; padding: 5px 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 30px; font-size: 13px; font-weight: 500; }
            .preparation-list { background: white; border-radius: 15px; padding: 20px; margin: 25px 0; border: 2px solid #eef2f6; }
            .preparation-list h4 { font-size: 16px; font-weight: 600; color: #2c3e50; margin: 0 0 15px; display: flex; align-items: center; gap: 8px; }
            .preparation-list h4 i { color: #27ae60; }
            .preparation-list ul { margin: 0; padding-left: 20px; }
            .preparation-list li { color: #64748b; font-size: 14px; margin-bottom: 8px; }
            .footer { background: #f8fafd; padding: 30px; text-align: center; border-top: 1px solid #eef2f6; }
            .footer p { color: #64748b; font-size: 14px; margin: 5px 0; }
            .footer-logo { margin-bottom: 15px; }
            .footer-logo i { font-size: 30px; color: #0e4c92; }
            .btn { display: inline-block; padding: 12px 30px; border-radius: 30px; text-decoration: none; font-weight: 600; font-size: 14px; }
            .btn-outline { border: 2px solid #0e4c92; color: #0e4c92; background: white; }
            @media (max-width: 600px) { .container { margin: 10px; border-radius: 20px; } .header { padding: 30px 20px; } .content { padding: 30px 20px; } .detail-row { flex-direction: column; align-items: flex-start; } .detail-icon { margin-bottom: 10px; } }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <div class="header-icon"><i class="fas fa-calendar-check"></i></div>
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
                        <div class="detail-icon"><i class="fas fa-briefcase"></i></div>
                        <div class="detail-content"><div class="detail-label">Position</div><div class="detail-value">' . htmlspecialchars($data['position']) . '</div></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-icon"><i class="fas fa-calendar-alt"></i></div>
                        <div class="detail-content"><div class="detail-label">Date</div><div class="detail-value">' . $formatted_date . ' (' . $formatted_day . ')</div></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-icon"><i class="fas fa-clock"></i></div>
                        <div class="detail-content"><div class="detail-label">Time</div><div class="detail-value">' . $formatted_time . '</div></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-icon"><i class="fas fa-comment-dots"></i></div>
                        <div class="detail-content"><div class="detail-label">Interview Round</div><div class="detail-value"><span class="badge">' . $data['interview_round'] . '</span></div></div>
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
                    <a href="mailto:' . HR_MAIL_USERNAME . '?subject=Interview%20Inquiry" class="btn btn-outline" style="margin: 0 5px;">
                        <i class="fas fa-question-circle"></i> Need Help?
                    </a>
                </div>
            </div>
            <div class="footer">
                <div class="footer-logo"><i class="fas fa-truck"></i></div>
                <p><strong>Priority Handling Logistics, Inc.</strong></p>
                <p>✉️ ' . HR_MAIL_USERNAME . '</p>
                <p style="margin-top: 20px; font-size: 12px;">
                    This is an automated message. Please do not reply directly to this email.<br>
                    © ' . date('Y') . ' Priority Handling Logistics, Inc. All rights reserved.
                </p>
            </div>
        </div>
    </body>
    </html>';
}

/**
 * Generate Google Meet link
 */
function generateMeetingLink($interview_round) {
    $prefixes = ['initial' => 'meet', 'technical' => 'tech', 'hr' => 'hr', 'final' => 'final'];
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
    $end = $start + 3600;
    
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
    
    $uid = $interview_id . '-' . uniqid() . '@priorityhandling.com';
    
    return "BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Priority Handling Logistics//HR System//EN
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
ORGANIZER;CN=HR Department:mailto:" . HR_MAIL_USERNAME . "
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

/* =========================================================================
   SCREENING PASSED EMAILS
   ========================================================================= */

function sendScreeningPassedWithAssessmentEmail($applicant_email, $applicant_name, $data) {
    try {
        $mail = MailConfig::getInstance();
        $mail->clearAddresses();
        $mail->clearAttachments();
        $mail->clearCustomHeaders();

        $mail->addAddress($applicant_email, $applicant_name);
        $mail->addBCC(HR_MAIL_USERNAME, 'HR Department');

        $mail->Subject = "🎉 You Passed Screening – Take Your Assessment | " . $data['job_title'];

        $body = buildScreeningPassedWithAssessmentHTML($applicant_name, $data);
        $mail->Body = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '</p>', '</div>'], ["\n", "\n\n", "\n"], $body));

        $mail->send();
        return ['success' => true, 'message' => 'Assessment email sent successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => "Email could not be sent. Error: {$mail->ErrorInfo}"];
    }
}

function buildScreeningPassedWithAssessmentHTML($applicant_name, $data) {
    $appNo          = htmlspecialchars($data['application_number'] ?? '');
    $jobTitle       = htmlspecialchars($data['job_title'] ?? '');
    $department     = htmlspecialchars(ucwords(str_replace('_', ' ', $data['department'] ?? '')));
    $assessmentType = htmlspecialchars($data['assessment_type'] ?? 'General Aptitude Assessment');
    $assessmentLink = htmlspecialchars($data['assessment_link'] ?? '#');

    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            body { font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; line-height: 1.6; margin: 0; padding: 0; background: linear-gradient(135deg, #f5f7fa 0%, #e9edf5 100%); }
            .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 30px; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); }
            .header { background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%); padding: 40px 30px; text-align: center; }
            .header h1 { margin: 20px 0 10px; font-size: 30px; font-weight: 700; color: white; }
            .header p { color: rgba(255,255,255,0.95); font-size: 16px; margin: 0; }
            .header-icon { width: 80px; height: 80px; background: rgba(255,255,255,0.2); border-radius: 30px; display: flex; align-items: center; justify-content: center; margin: 0 auto; border: 2px solid rgba(255,255,255,0.3); }
            .header-icon i { font-size: 40px; color: white; }
            .content { padding: 40px 30px; }
            .greeting h2 { font-size: 22px; font-weight: 600; color: #2c3e50; margin: 0 0 5px; }
            .greeting p { color: #64748b; font-size: 15px; margin: 0; }
            .info-card { background: #f8fafd; border-radius: 20px; padding: 25px; margin: 25px 0; border: 1px solid #eef2f6; }
            .info-row { display: flex; align-items: flex-start; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px dashed #e2e8f0; }
            .info-row:last-child { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }
            .info-icon { width: 40px; height: 40px; background: white; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-right: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
            .info-icon i { font-size: 18px; color: #0e4c92; }
            .info-label { font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px; }
            .info-value { font-size: 16px; font-weight: 600; color: #2c3e50; }
            .cta-box { background: #fff8e1; border-left: 4px solid #f39c12; padding: 18px; border-radius: 10px; margin: 25px 0; }
            .cta-box strong { color: #7a5c00; display: block; font-size: 15px; margin-bottom: 5px; }
            .cta-box p { color: #7a5c00; font-size: 14px; margin: 0; }
            .btn-wrap { text-align: center; margin: 30px 0; }
            .btn-primary { display: inline-block; background: linear-gradient(135deg, #0e4c92 0%, #4086e4 100%); color: white !important; text-decoration: none; padding: 15px 36px; border-radius: 12px; font-weight: 600; font-size: 15px; box-shadow: 0 10px 20px rgba(14, 76, 146, 0.25); }
            .link-text { font-size: 12px; color: #718096; word-break: break-all; text-align: center; margin-top: 15px; }
            .link-text a { color: #0e4c92; }
            .footer { background: #f8fafd; padding: 25px; text-align: center; border-top: 1px solid #eef2f6; }
            .footer p { color: #64748b; font-size: 13px; margin: 5px 0; }
            .footer i { font-size: 26px; color: #0e4c92; margin-bottom: 10px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <div class="header-icon"><i class="fas fa-trophy"></i></div>
                <h1>Congratulations, ' . htmlspecialchars($applicant_name) . '!</h1>
                <p>You passed the initial screening 🎉</p>
            </div>
            <div class="content">
                <div class="greeting">
                    <h2>Great news!</h2>
                    <p>Your application has successfully passed our initial screening. You are now one step closer to joining our team.</p>
                </div>
                <div class="info-card">
                    <div class="info-row">
                        <div class="info-icon"><i class="fas fa-hashtag"></i></div>
                        <div><div class="info-label">Application Number</div><div class="info-value">' . $appNo . '</div></div>
                    </div>
                    <div class="info-row">
                        <div class="info-icon"><i class="fas fa-briefcase"></i></div>
                        <div><div class="info-label">Position</div><div class="info-value">' . $jobTitle . '</div></div>
                    </div>
                    <div class="info-row">
                        <div class="info-icon"><i class="fas fa-building"></i></div>
                        <div><div class="info-label">Department</div><div class="info-value">' . $department . '</div></div>
                    </div>
                    <div class="info-row">
                        <div class="info-icon"><i class="fas fa-file-alt"></i></div>
                        <div><div class="info-label">Required Assessment</div><div class="info-value">' . $assessmentType . '</div></div>
                    </div>
                </div>
                <div class="cta-box">
                    <strong>📝 Next Step: Take the Online Assessment</strong>
                    <p>Before we can schedule your initial interview, you must first complete the <strong>' . $assessmentType . '</strong>.</p>
                </div>
                <div class="btn-wrap">
                    <a href="' . $assessmentLink . '" class="btn-primary">Start Assessment &nbsp;→</a>
                </div>
                <div class="link-text">
                    If the button above does not work, copy and paste this link into your browser:<br>
                    <a href="' . $assessmentLink . '">' . $assessmentLink . '</a>
                </div>
                <p style="font-size: 13px; color: #718096; margin-top: 20px;">
                    Best regards,<br>
                    <strong>Priority Handling Logistics HR Team</strong>
                </p>
            </div>
            <div class="footer">
                <i class="fas fa-truck"></i>
                <p><strong>Priority Handling Logistics, Inc.</strong></p>
                <p>✉️ ' . HR_MAIL_USERNAME . '</p>
                <p style="margin-top: 15px; font-size: 11px;">This is an automated message. Please do not reply directly to this email.</p>
            </div>
        </div>
    </body>
    </html>';
}

function sendScreeningPassedNoAssessmentEmail($applicant_email, $applicant_name, $data) {
    try {
        $mail = MailConfig::getInstance();
        $mail->clearAddresses();
        $mail->clearAttachments();
        $mail->clearCustomHeaders();

        $mail->addAddress($applicant_email, $applicant_name);
        $mail->addBCC(HR_MAIL_USERNAME, 'HR Department');

        $mail->Subject = "🎉 You Passed Screening – Initial Interview Next | " . $data['job_title'];

        $body = buildScreeningPassedNoAssessmentHTML($applicant_name, $data);
        $mail->Body = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '</p>', '</div>'], ["\n", "\n\n", "\n"], $body));

        $mail->send();
        return ['success' => true, 'message' => 'Congratulations email sent successfully'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => "Email could not be sent. Error: {$mail->ErrorInfo}"];
    }
}

function buildScreeningPassedNoAssessmentHTML($applicant_name, $data) {
    $appNo      = htmlspecialchars($data['application_number'] ?? '');
    $jobTitle   = htmlspecialchars($data['job_title'] ?? '');
    $department = htmlspecialchars(ucwords(str_replace('_', ' ', $data['department'] ?? '')));

    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            body { font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; line-height: 1.6; margin: 0; padding: 0; background: linear-gradient(135deg, #f5f7fa 0%, #e9edf5 100%); }
            .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 30px; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); }
            .header { background: linear-gradient(135deg, #0e4c92 0%, #2a6eb0 100%); padding: 40px 30px; text-align: center; }
            .header h1 { margin: 20px 0 10px; font-size: 30px; font-weight: 700; color: white; }
            .header p { color: rgba(255,255,255,0.95); font-size: 16px; margin: 0; }
            .header-icon { width: 80px; height: 80px; background: rgba(255,255,255,0.2); border-radius: 30px; display: flex; align-items: center; justify-content: center; margin: 0 auto; border: 2px solid rgba(255,255,255,0.3); }
            .header-icon i { font-size: 40px; color: white; }
            .content { padding: 40px 30px; }
            .greeting h2 { font-size: 22px; font-weight: 600; color: #2c3e50; margin: 0 0 5px; }
            .greeting p { color: #64748b; font-size: 15px; margin: 0; }
            .info-card { background: #f8fafd; border-radius: 20px; padding: 25px; margin: 25px 0; border: 1px solid #eef2f6; }
            .info-row { display: flex; align-items: flex-start; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px dashed #e2e8f0; }
            .info-row:last-child { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }
            .info-icon { width: 40px; height: 40px; background: white; border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-right: 15px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
            .info-icon i { font-size: 18px; color: #0e4c92; }
            .info-label { font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 2px; }
            .info-value { font-size: 16px; font-weight: 600; color: #2c3e50; }
            .success-box { background: #e8fff1; border-left: 4px solid #27ae60; padding: 18px; border-radius: 10px; margin: 25px 0; }
            .success-box strong { color: #155724; display: block; font-size: 15px; margin-bottom: 5px; }
            .success-box p { color: #155724; font-size: 14px; margin: 0; }
            .footer { background: #f8fafd; padding: 25px; text-align: center; border-top: 1px solid #eef2f6; }
            .footer p { color: #64748b; font-size: 13px; margin: 5px 0; }
            .footer i { font-size: 26px; color: #0e4c92; margin-bottom: 10px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <div class="header-icon"><i class="fas fa-award"></i></div>
                <h1>Congratulations, ' . htmlspecialchars($applicant_name) . '!</h1>
                <p>You passed the initial screening 🎉</p>
            </div>
            <div class="content">
                <div class="greeting">
                    <h2>Great news!</h2>
                    <p>Your application has successfully passed our initial screening. You are now one step closer to joining our team.</p>
                </div>
                <div class="info-card">
                    <div class="info-row">
                        <div class="info-icon"><i class="fas fa-hashtag"></i></div>
                        <div><div class="info-label">Application Number</div><div class="info-value">' . $appNo . '</div></div>
                    </div>
                    <div class="info-row">
                        <div class="info-icon"><i class="fas fa-briefcase"></i></div>
                        <div><div class="info-label">Position</div><div class="info-value">' . $jobTitle . '</div></div>
                    </div>
                    <div class="info-row">
                        <div class="info-icon"><i class="fas fa-building"></i></div>
                        <div><div class="info-label">Department</div><div class="info-value">' . $department . '</div></div>
                    </div>
                </div>
                <div class="success-box">
                    <strong>✅ Next Step: Initial Interview</strong>
                    <p>No written assessment is required for this role. Your profile has been shortlisted, and our HR team will reach out shortly to schedule your initial interview.</p>
                </div>
                <p style="font-size: 14px; color: #4a5568; line-height: 1.6;">
                    Please keep your phone and email available. We will send the interview schedule and details very soon.
                </p>
                <p style="font-size: 13px; color: #718096; margin-top: 25px;">
                    Best regards,<br>
                    <strong>Priority Handling Logistics HR Team</strong>
                </p>
            </div>
            <div class="footer">
                <i class="fas fa-truck"></i>
                <p><strong>Priority Handling Logistics, Inc.</strong></p>
                <p>✉️ ' . HR_MAIL_USERNAME . '</p>
                <p style="margin-top: 15px; font-size: 11px;">This is an automated message. Please do not reply directly to this email.</p>
            </div>
        </div>
    </body>
    </html>';
}

/* =========================================================================
   ASSESSMENT RESULT EMAILS
   ========================================================================= */

function sendAssessmentResultEmail($applicant_email, $applicant_name, $data) {
    try {
        $mail = MailConfig::getInstance();
        $mail->clearAddresses();
        $mail->clearAttachments();
        $mail->clearCustomHeaders();
        $mail->addAddress($applicant_email, $applicant_name);
        $mail->addBCC(HR_MAIL_USERNAME, 'HR Department');

        $passed = ($data['result'] === 'passed');
        $mail->Subject = $passed
            ? "🎉 Assessment Passed — {$data['job_title']} | Priority Handling Logistics"
            : "Assessment Result — {$data['job_title']} | Priority Handling Logistics";

        $body = buildAssessmentResultHTML($applicant_name, $data, $passed);
        $mail->Body = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>','</p>','</div>'], ["\n","\n\n","\n"], $body));

        $mail->send();
        return ['success' => true, 'message' => 'Assessment result email sent'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => "Email could not be sent. Error: {$mail->ErrorInfo}"];
    }
}

function buildAssessmentResultHTML($applicant_name, $data, $passed) {
    $appNo    = htmlspecialchars($data['application_number']);
    $jobTitle = htmlspecialchars($data['job_title']);
    $score    = number_format((float)$data['score'], 2);
    $correct  = (int)$data['correct'];
    $total    = (int)$data['total'];

    $headerBg    = $passed ? 'linear-gradient(135deg,#27ae60,#2ecc71)' : 'linear-gradient(135deg,#e74c3c,#c0392b)';
    $headerIcon  = $passed ? 'fa-trophy' : 'fa-info-circle';
    $headerTitle = $passed ? 'Congratulations, ' . htmlspecialchars($applicant_name) . '!' : 'Assessment Completed';
    $headerSub   = $passed ? 'You passed the assessment 🎉' : 'Here is your assessment result';

    $nextBox = $passed
        ? '<div style="background:#e8fff1;border-left:4px solid #27ae60;padding:18px;border-radius:10px;margin:25px 0;">
             <strong style="color:#155724;display:block;font-size:15px;margin-bottom:5px;">✅ Next Step: Initial Interview</strong>
             <p style="color:#155724;font-size:14px;margin:0;">Your assessment has been submitted and reviewed. Our HR team will contact you shortly to schedule your initial interview.</p>
           </div>'
        : '<div style="background:#fff8e1;border-left:4px solid #f39c12;padding:18px;border-radius:10px;margin:25px 0;">
             <strong style="color:#7a5c00;display:block;font-size:15px;margin-bottom:5px;">📋 What Happens Next</strong>
             <p style="color:#7a5c00;font-size:14px;margin:0;">Thank you for completing our assessment. Unfortunately, your score did not meet the passing requirement for this position.</p>
           </div>';

    return '<!DOCTYPE html>
    <html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
      body{font-family:"Inter",Arial,sans-serif;line-height:1.6;margin:0;padding:0;background:linear-gradient(135deg,#f5f7fa,#e9edf5);}
      .container{max-width:600px;margin:20px auto;background:#fff;border-radius:30px;overflow:hidden;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);}
      .header{background:' . $headerBg . ';padding:40px 30px;text-align:center;}
      .header h1{margin:20px 0 10px;font-size:28px;font-weight:700;color:#fff;}
      .header p{color:rgba(255,255,255,0.95);font-size:15px;margin:0;}
      .header-icon{width:80px;height:80px;background:rgba(255,255,255,0.2);border-radius:30px;display:flex;align-items:center;justify-content:center;margin:0 auto;border:2px solid rgba(255,255,255,0.3);}
      .header-icon i{font-size:40px;color:#fff;}
      .content{padding:40px 30px;}
      .info-card{background:#f8fafd;border-radius:20px;padding:25px;margin:25px 0;border:1px solid #eef2f6;}
      .info-row{display:flex;align-items:flex-start;margin-bottom:15px;padding-bottom:15px;border-bottom:1px dashed #e2e8f0;}
      .info-row:last-child{margin-bottom:0;padding-bottom:0;border-bottom:none;}
      .info-icon{width:40px;height:40px;background:#fff;border-radius:12px;display:flex;align-items:center;justify-content:center;margin-right:15px;box-shadow:0 4px 6px rgba(0,0,0,0.05);}
      .info-icon i{font-size:18px;color:#0e4c92;}
      .info-label{font-size:12px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:2px;}
      .info-value{font-size:16px;font-weight:600;color:#2c3e50;}
      .score-box{text-align:center;background:#f8fafd;border-radius:20px;padding:25px;margin:25px 0;}
      .score-big{font-size:48px;font-weight:800;color:' . ($passed ? '#27ae60' : '#e74c3c') . ';line-height:1;}
      .score-label{font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-top:5px;}
      .score-detail{font-size:14px;color:#64748b;margin-top:10px;}
      .footer{background:#f8fafd;padding:25px;text-align:center;border-top:1px solid #eef2f6;}
      .footer p{color:#64748b;font-size:13px;margin:5px 0;}
      .footer i{font-size:26px;color:#0e4c92;margin-bottom:10px;}
    </style></head>
    <body>
      <div class="container">
        <div class="header">
          <div class="header-icon"><i class="fas ' . $headerIcon . '"></i></div>
          <h1>' . $headerTitle . '</h1>
          <p>' . $headerSub . '</p>
        </div>
        <div class="content">
          <div class="info-card">
            <div class="info-row">
              <div class="info-icon"><i class="fas fa-hashtag"></i></div>
              <div><div class="info-label">Application Number</div><div class="info-value">' . $appNo . '</div></div>
            </div>
            <div class="info-row">
              <div class="info-icon"><i class="fas fa-briefcase"></i></div>
              <div><div class="info-label">Position</div><div class="info-value">' . $jobTitle . '</div></div>
            </div>
          </div>
          <div class="score-box">
            <div class="score-big">' . $score . '%</div>
            <div class="score-label">Your Score</div>
            <div class="score-detail">' . $correct . ' out of ' . $total . ' correct answers</div>
          </div>
          ' . $nextBox . '
        </div>
        <div class="footer">
          <i class="fas fa-truck"></i>
          <p><strong>Priority Handling Logistics, Inc.</strong></p>
          <p>✉️ ' . HR_MAIL_USERNAME . '</p>
          <p style="margin-top:15px;font-size:11px;">This is an automated message. Please do not reply directly.</p>
        </div>
      </div>
    </body></html>';
}